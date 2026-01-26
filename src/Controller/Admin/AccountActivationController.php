<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\SecurityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/admin/account-activations')]
class AccountActivationController extends AbstractController
{
    public function __construct(
        private SecurityLogger $securityLogger
    ) {
    }
    
    #[Route('/', name: 'admin_account_activations')]
    public function index(EntityManagerInterface $em): Response
    {
        $activations = $em->getRepository(User::class)->findBy(["verificationStatut" => "pending"], ['createdAt' => 'DESC']);

        return $this->render('admin/pages/account_activations/index.html.twig', [
            'activations' => $activations,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_approve_activation', methods: ['POST'])]
    public function approve(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('approve'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(true);
            $user->setVerificationStatut('verified');
            $user->setVerificationSubmittedAt(null);
            $em->flush();

            //log
            $this->securityLogger->log(
                    $user,
                    'USER_APPROVE',
                    'User',
                    $user->getId(),
                    'Utilisateur ' . $user->getUuid() . ' approuvé PAR '.$this->getUser()->getUuid()
                );
            
        }

        return $this->redirectToRoute('admin_account_activations');
    }

    #[Route('/{id}/reject', name: 'admin_reject_activation', methods: ['POST'])]
    public function reject(User $user, EntityManagerInterface $em, Request $request): Response
    {
        if ($this->isCsrfTokenValid('reject'.$user->getId(), $request->request->get('_token'))) {
            $user->setVerificationStatut('rejected');
            $user->setVerificationSubmittedAt(null);
            $em->flush();

            //log
            $this->securityLogger->log(
                    $user,
                    'USER_REJECT',
                    'User',
                    $user->getId(),
                    'Utilisateur ' . $user->getUuid() . ' rejeté PAR '.$this->getUser()->getUuid()
                );  
        }

        return $this->redirectToRoute('admin_account_activations');
    }

    public function countPendingActivations(EntityManagerInterface $em): Response
    {
        $count = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = false')
            ->andWhere('u.verificationSubmittedAt IS NOT NULL')
            ->andWhere('u.verificationStatut = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        return new Response($count);
    }
    
    #[Route('/{id}', name: 'admin_activation_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        
        if (!$user) {
            throw new NotFoundHttpException('Utilisateur non trouvé');
        }
        
        // Vérifier que l'utilisateur a bien une demande d'activation en attente
        if ($user->getVerificationStatut() !== 'pending' || $user->isActive()) {
            $this->addFlash('warning', 'Cette demande d\'activation n\'est plus en attente.');
            return $this->redirectToRoute('admin_account_activations');
        }
        
        
        // Décoder le document d'identité s'il existe
        $identityData = [];
        if ($user->getIdentityDocument()) {
            $identityData = json_decode($user->getIdentityDocument(), true) ?: [];
        }

        return $this->render('admin/pages/account_activations/show.html.twig', [
            'user' => $user,
            'identityData' => $identityData,
        ]);
    }
}
