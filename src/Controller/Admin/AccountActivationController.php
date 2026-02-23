<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserVerification;
use App\Repository\UserRepository;
use App\Repository\UserVerificationRepository;
use App\Service\ActivityLogger;
use App\Service\EmailService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activations')]
class AccountActivationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserVerificationRepository $verificationRepository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private NotificationService $notificationService,
        private ActivityLogger $activityLogger
    ) {
    }

    #[Route('/', name: 'admin_account_activations')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'users');

        $activations = $this->verificationRepository->findBy(['status' => 'pending'], ['submittedAt' => 'DESC']);

        return $this->render('admin/pages/account_activations/index.html.twig', [
            'activations' => $activations
        ]);
    }

    #[Route('/{id}', name: 'admin_activation_show')]
    public function show(UserVerification $verification): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'users');

        $user = $verification->getUser();
        $identityData = json_decode($verification->getIdentityData(), true) ?: [];

        return $this->render('admin/pages/account_activations/show.html.twig', [
            'verification' => $verification,
            'user' => $user,
            'identityData' => $identityData
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_approve_activation', methods: ['POST'])]
    public function approve(UserVerification $verification, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');

        $user = $verification->getUser();

        if ($this->isCsrfTokenValid('approve' . $user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(true);
            $user->setIsVerified(true);
            $verification->setStatus('verified');
            $verification->setRejectionReason(null);

            $this->entityManager->flush();

            $this->notificationService->sendAccountApprovedNotification($user);
            $this->emailService->sendAccountApprovedEmail($user);

            $this->activityLogger->log(
                $this->getUser(),
                'USER_APPROVE',
                'User',
                $user->getId(),
                'Compte activé et vérifié pour ' . $user->getEmail()
            );

            $this->addFlash('success', 'Le compte a été activé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('admin_account_activations');
    }

    #[Route('/{id}/reject', name: 'admin_reject_activation', methods: ['POST'])]
    public function reject(UserVerification $verification, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');

        $user = $verification->getUser();

        if ($this->isCsrfTokenValid('reject' . $user->getId(), $request->request->get('_token'))) {
            $reason = trim($request->request->get('rejection_reason', ''));

            if (empty($reason)) {
                $this->addFlash('error', 'Vous devez fournir un motif de rejet.');
                return $this->redirectToRoute('admin_activation_show', ['id' => $verification->getId()]);
            }

            $verification->setStatus('rejected');
            $verification->setRejectionReason($reason);
            $this->entityManager->flush();

            $this->notificationService->sendAccountRejectedNotification($user, $reason);
            $this->emailService->sendAccountRejectedEmail($user, $reason);

            $this->activityLogger->log(
                $this->getUser(),
                'USER_REJECT',
                'User',
                $user->getId(),
                'Demande d\'activation rejetée pour ' . $user->getEmail() . ' (Raison: ' . $reason . ')'
            );

            $this->addFlash('success', 'La demande a été rejetée.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('admin_account_activations');
    }
}
