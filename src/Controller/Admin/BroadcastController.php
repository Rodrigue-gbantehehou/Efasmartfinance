<?php

namespace App\Controller\Admin;

use App\Entity\BroadcastMessage;
use App\Entity\Notification;
use App\Repository\BroadcastMessageRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/broadcast')]
#[IsGranted('ROLE_SUPPORT')]
class BroadcastController extends AbstractController
{
    public function __construct(
        private BroadcastMessageRepository $broadcastRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private EmailService $emailService,
        private ActivityLogger $activityLogger
    ) {}

    #[Route('', name: 'admin_broadcast')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'broadcast');
        $broadcasts = $this->broadcastRepo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/pages/broadcast/index.html.twig', [
            'broadcasts' => $broadcasts,
        ]);
    }

    #[Route('/search-users', name: 'admin_broadcast_search_users')]
    public function searchUsers(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'broadcast');
        $q = $request->query->get('q', '');

        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $users = $this->userRepo->createQueryBuilder('u')
            ->where('u.email LIKE :q')
            ->orWhere('u.firstname LIKE :q')
            ->orWhere('u.lastname LIKE :q')
            ->orWhere('u.id LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'text' => sprintf('%s %s (%s)', $user->getFirstname(), $user->getLastname(), $user->getEmail()),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/create', name: 'admin_broadcast_create')]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'broadcast');
        if ($request->isMethod('POST')) {
            $broadcast = new BroadcastMessage();
            $broadcast->setTitle($request->request->get('title'));
            $broadcast->setContent($request->request->get('content'));
            $broadcast->setType($request->request->get('type', 'email'));
            $broadcast->setTargetAudience($request->request->get('target_audience', 'all'));
            
            if ($broadcast->getTargetAudience() === 'single_user') {
                $recipientId = $request->request->get('recipient_id');
                if ($recipientId) {
                    $recipient = $this->userRepo->find($recipientId);
                    $broadcast->setRecipient($recipient);
                }
            }

            $broadcast->setCreatedBy($this->getUser());

            $scheduledDate = $request->request->get('scheduled_date');
            if ($scheduledDate) {
                $broadcast->setScheduledAt(new \DateTimeImmutable($scheduledDate));
                $broadcast->setStatus('scheduled');
            } else {
                $broadcast->setStatus('draft');
            }

            $this->em->persist($broadcast);
            $this->em->flush();

            $this->activityLogger->log(
                $this->getUser(),
                'broadcast_created',
                'BroadcastMessage',
                $broadcast->getId(),
                "Broadcast créé: {$broadcast->getTitle()}"
            );

            $this->addFlash('success', 'Broadcast créé avec succès.');
            return $this->redirectToRoute('admin_broadcast');
        }

        return $this->render('admin/pages/broadcast/create.html.twig');
    }

    #[Route('/{id}/send', name: 'admin_broadcast_send', methods: ['POST'])]
    public function send(BroadcastMessage $broadcast, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'broadcast');
        if (!$this->isCsrfTokenValid('send' . $broadcast->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_broadcast');
        }

        try {
            $recipients = $this->getRecipients($broadcast);
            
            if (empty($recipients)) {
                $this->addFlash('error', 'Aucun destinataire trouvé pour cette audience.');
                return $this->redirectToRoute('admin_broadcast');
            }

            $sentCount = 0;
            $failedCount = 0;

            foreach ($recipients as $user) {
                try {
                    if ($broadcast->getType() === 'email' || $broadcast->getType() === 'both') {
                        $emailContent = $this->renderView('emails/broadcast.html.twig', [
                            'broadcast' => $broadcast,
                            'user' => $user,
                        ]);

                        $this->emailService->send(
                            $user->getEmail(),
                            $broadcast->getTitle(),
                            $emailContent
                        );
                    }

                    if ($broadcast->getType() === 'notification' || $broadcast->getType() === 'both') {
                        $notification = new Notification();
                        $notification->setTitle($broadcast->getTitle());
                        $notification->setMessage($broadcast->getContent());
                        $notification->setType('info');
                        $notification->setSource('Équipe Efa Smart Finance'); // Set explicit source
                        $notification->setUser($user);
                        $this->em->persist($notification);
                    }

                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                }
            }

            $broadcast->setStatus('sent');
            $broadcast->setSentAt(new \DateTimeImmutable());
            $broadcast->setStats([
                'sent' => $sentCount,
                'failed' => $failedCount,
                'total_recipients' => count($recipients),
            ]);

            $this->em->flush();

            $this->activityLogger->log(
                $this->getUser(),
                'broadcast_sent',
                'BroadcastMessage',
                $broadcast->getId(),
                "Broadcast envoyé: {$broadcast->getTitle()} - {$sentCount} envoyés, {$failedCount} échecs"
            );

            $this->addFlash('success', "Broadcast envoyé avec succès à {$sentCount} destinataires.");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_broadcast');
    }

    #[Route('/{id}', name: 'admin_broadcast_show')]
    public function show(BroadcastMessage $broadcast): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'broadcast');
        return $this->render('admin/pages/broadcast/show.html.twig', [
            'broadcast' => $broadcast,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_broadcast_delete', methods: ['POST'])]
    public function delete(BroadcastMessage $broadcast, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'broadcast');
        if (!$this->isCsrfTokenValid('delete' . $broadcast->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_broadcast');
        }

        $this->em->remove($broadcast);
        $this->em->flush();

        $this->activityLogger->log(
            $this->getUser(),
            'broadcast_deleted',
            'BroadcastMessage',
            $broadcast->getId(),
            "Broadcast supprimé: {$broadcast->getTitle()}"
        );

        $this->addFlash('success', 'Broadcast supprimé avec succès.');
        return $this->redirectToRoute('admin_broadcast');
    }

    private function getRecipients(BroadcastMessage $broadcast): array
    {
        if ($broadcast->getTargetAudience() === 'single_user') {
            return $broadcast->getRecipient() ? [$broadcast->getRecipient()] : [];
        }

        $qb = $this->userRepo->createQueryBuilder('u');
        $targetAudience = $broadcast->getTargetAudience();

        switch ($targetAudience) {
            case 'verified':
                $qb->where('u.isVerified = :verified')
                   ->setParameter('verified', true);
                break;

            case 'unverified':
                $qb->where('u.isVerified = :verified')
                   ->setParameter('verified', false);
                break;

            case 'role_support':
                $qb->where('u.roles LIKE :role')
                   ->setParameter('role', '%ROLE_SUPPORT%');
                break;

            case 'role_finance':
                $qb->where('u.roles LIKE :role')
                   ->setParameter('role', '%ROLE_FINANCE%');
                break;

            case 'role_admin':
                $qb->where('u.roles LIKE :role')
                   ->setParameter('role', '%ROLE_ADMIN%');
                break;

            case 'all':
            default:
                // Tous les utilisateurs
                break;
        }

        return $qb->getQuery()->getResult();
    }
}
