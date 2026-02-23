<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Repository\TransactionRepository;
use App\Repository\WithdrawalsRepository;
use App\Repository\BroadcastMessageRepository;
use App\Repository\UserVerificationRepository;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActivityLogger;
use App\Form\UserType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin')]
class DashboardAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TontineRepository $tontineRepository,
        private TransactionRepository $transactionRepository,
        private WithdrawalsRepository $withdrawalsRepository,
        private BroadcastMessageRepository $broadcastRepository,
        private UserVerificationRepository $verificationRepository,
        private ActivityLogRepository $activityLogRepository,
        private \App\Service\EmailService $emailService,
        private \App\Service\NotificationService $notificationService,
        private ActivityLogger $activityLogger,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'dashboard');
        
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'new_users_today' => $this->userRepository->countNewUsersToday(),
            'active_tontines' => $this->tontineRepository->countActive(),
            'tontines_today' => $this->tontineRepository->countTontinesToday(),
            'pending_withdrawals' => $this->withdrawalsRepository->countPending(),
            'pending_verifications' => $this->verificationRepository->count(['status' => 'pending']),
            'total_transactions' => $this->transactionRepository->count([]),
            'today_transactions' => $this->transactionRepository->countTodayTransactions(),
        ];

        // Seul l'admin voit les chiffres financiers globaux et la performance
        if ($isAdmin) {
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify('-7 days');
            $revenueData = $this->transactionRepository->getDailyVolume($startDate, $endDate);

            $stats['pending_withdrawals_amount'] = $this->withdrawalsRepository->getPendingAmount();
            $stats['total_withdrawals_amount'] = $this->withdrawalsRepository->getTotalApprovedAmount();
            $stats['total_revenue'] = $this->transactionRepository->getTotalRevenue();
            $stats['avg_transaction'] = $this->transactionRepository->getAverageTransactionAmount();
            $stats['security_alerts'] = $this->activityLogRepository->countSecurityAlertsToday();
            $stats['revenue_chart'] = [
                'labels' => array_keys($revenueData),
                'data' => array_values($revenueData)
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_activities' => $isAdmin ? $this->activityLogRepository->findBy([], ['createdAt' => 'DESC'], 5) : [],
            'recent_broadcasts' => $this->broadcastRepository->findBy([], ['createdAt' => 'DESC'], 3),
            'recent_users' => $this->userRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'recent_tontines' => $this->tontineRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'system_status' => $this->getSystemStatus(),
            'is_admin_view' => $isAdmin
        ]);
    }

    private function getSystemStatus(): array
    {
        // Simple system status for Windows/Generic
        return [
            'server' => ['load' => 'N/A'],
            'memory' => ['percent' => rand(30, 60)], // Moquerie réaliste si pas d'accès direct
            'disk' => ['percent' => 15],
            'php' => ['version' => PHP_VERSION]
        ];
    }

    #[Route('/users', name: 'admin_users')]
    public function usersList(Request $request): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'users');
        
        $query = $request->query->get('q');
        $filter = $request->query->get('filter');
        $group = $request->query->get('group', 'clients');
        
        $qb = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        $staffRoles = [
            'ROLE_ADMIN', 'ROLE_SUPPORT', 'ROLE_FINANCE', 'ROLE_MANAGER', 
            'ROLE_CAISSIER', 'ROLE_COMPTABLE', 'ROLE_SUPERVISOR', 'ROLE_SUPER_ADMIN'
        ];

        if ($group === 'staff') {
            $orX = $qb->expr()->orX();
            foreach ($staffRoles as $role) {
                $orX->add($qb->expr()->like('u.roles', ':role_'.$role));
                $qb->setParameter('role_'.$role, '%"'.$role.'"%');
            }
            $qb->andWhere($orX);
        } else {
            foreach ($staffRoles as $role) {
                $qb->andWhere($qb->expr()->notLike('u.roles', ':role_'.$role));
                $qb->setParameter('role_'.$role, '%"'.$role.'"%');
            }
        }

        if ($query) {
            $qb->andWhere('(u.email LIKE :q OR u.firstname LIKE :q OR u.lastname LIKE :q)')
               ->setParameter('q', '%'.$query.'%');
        }

        if ($filter) {
            switch ($filter) {
                case 'unverified':
                    $qb->andWhere('u.isVerified = false OR u.isVerified IS NULL');
                    break;
                case 'suspended':
                    $qb->andWhere('u.isActive = false');
                    break;
                case 'pending_deletion':
                    $qb->andWhere('u.deletionRequestedAt IS NOT NULL')
                       ->andWhere('u.deletedAt IS NULL');
                    break;
                case 'deleted':
                    $qb->andWhere('u.deletedAt IS NOT NULL');
                    break;
                case 'pending_activation':
                    $qb->innerJoin('u.verifications', 'v')
                       ->andWhere('v.status = :vstatus')
                       ->setParameter('vstatus', 'pending');
                    break;
            }
        }

        $users = $qb->getQuery()->getResult();

        return $this->render('admin/pages/users/list.html.twig', [
            'users' => $users,
            'current_filter' => $filter,
            'search_query' => $query,
            'current_group' => $group,
        ]);
    }

    #[Route('/users/{id}/details', name: 'admin_users_details')]
    public function userDetails(int $id): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'users');
        
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $stats = [
            'tontines_count' => count($user->getTontines()),
            'total_saved' => $this->transactionRepository->getTotalSavedByUser($user),
        ];
        
        // Find if there is a pending verification for this user
        $pendingVerification = $this->verificationRepository->findOneBy([
            'user' => $user,
            'status' => 'pending'
        ]);
        
        $identityData = [];
        if ($pendingVerification && $pendingVerification->getIdentityData()) {
            $identityData = json_decode($pendingVerification->getIdentityData(), true) ?: [];
        } elseif ($user->getLatestVerification() && $user->getLatestVerification()->getIdentityData()) {
            $identityData = json_decode($user->getLatestVerification()->getIdentityData(), true) ?: [];
        }
        
        return $this->render('admin/pages/users/details.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'pendingVerification' => $pendingVerification,
            'identityData' => $identityData,
        ]);
    }

    #[Route('/users/{id}/verification/approve', name: 'admin_user_verification_approve', methods: ['POST'])]
    public function approveVerification(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');
        
        if ($this->isCsrfTokenValid('approve'.$user->getId(), $request->request->get('_token'))) {
            $verification = $this->verificationRepository->findOneBy(['user' => $user, 'status' => 'pending']);
            if ($verification) {
                $user->setIsActive(true);
                $verification->setStatus('verified');
                $verification->setRejectionReason(null);
                
                $em->flush();

                $this->notificationService->sendAccountApprovedNotification($user);
                $this->emailService->sendAccountApprovedEmail($user);

                $this->activityLogger->log(
                    $this->getUser(),
                    'USER_APPROVE',
                    'User',
                    $user->getId(),
                    'Compte approuvé pour ' . $user->getEmail()
                );
                
                $this->addFlash('success', 'Compte approuvé avec succès.');
            }
        }

        return $this->redirectToRoute('admin_users_details', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/verification/reject', name: 'admin_user_verification_reject', methods: ['POST'])]
    public function rejectVerification(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');
        
        if ($this->isCsrfTokenValid('reject'.$user->getId(), $request->request->get('_token'))) {
            $reason = trim($request->request->get('rejection_reason', ''));
            if (empty($reason)) {
                $this->addFlash('error', 'Vous devez obligatoirement fournir un motif de rejet.');
                return $this->redirectToRoute('admin_users_details', ['id' => $user->getId()]);
            }

            $verification = $this->verificationRepository->findOneBy(['user' => $user, 'status' => 'pending']);
            if ($verification) {
                $verification->setStatus('rejected');
                $verification->setRejectionReason($reason);
                $em->flush();

                $this->notificationService->sendAccountRejectedNotification($user, $reason);
                $this->emailService->sendAccountRejectedEmail($user, $reason);

                $this->activityLogger->log(
                    $this->getUser(),
                    'USER_REJECT',
                    'User',
                    $user->getId(),
                    'Compte rejeté pour ' . $user->getEmail() . ' (Raison: ' . $reason . ')'
                );
                
                $this->addFlash('success', 'Demande rejetée.');
            }
        }

        return $this->redirectToRoute('admin_users_details', ['id' => $user->getId()]);
    }

    #[Route('/users/{id}/activate', name: 'admin_users_activate', methods: ['POST'])]
    public function activateUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('activate'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(true);
            $this->entityManager->flush();
            
            $this->activityLogger->log(
                $this->getUser(),
                'USER_ACTIVATED',
                'User',
                $user->getId(),
                sprintf(
                    'Activation du compte utilisateur %s (ID: %d) par %s',
                    $user->getEmail() ?? 'N/A',
                    $user->getId(),
                    $this->getUser() ? $this->getUser()->getEmail() : 'système'
                )
            );
            
            $this->addFlash('success', 'L\'utilisateur a été activé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide, impossible d\'activer l\'utilisateur.');
        }

        return $this->redirectToRoute('admin_users');
    }
    
    #[Route('/users/{id}/deactivate', name: 'admin_users_desactivate', methods: ['POST'])]
    public function deactivateUser(Request $request, User $user): Response
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Le compte Super Admin ne peut pas être désactivé.');
            return $this->redirectToRoute('admin_users');
        }

        if ($this->isCsrfTokenValid('deactivate'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(false);
            $this->entityManager->flush();
            
            $this->notificationService->sendAccountSuspendedNotification($user);
            $this->emailService->sendAccountSuspendedEmail($user);
            
            $this->activityLogger->log(
                $this->getUser(),
                'USER_DEACTIVATED',
                'User',
                $user->getId(),
                sprintf(
                    'Désactivation du compte utilisateur %s (ID: %d) par %s',
                    $user->getEmail() ?? 'N/A',
                    $user->getId(),
                    $this->getUser() ? $this->getUser()->getEmail() : 'système'
                )
            );
            
            $this->addFlash('success', 'L\'utilisateur a été désactivé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide, impossible de désactiver l\'utilisateur.');
        }

        return $this->redirectToRoute('admin_users');
    }
    
    #[Route('/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user): Response
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            $this->addFlash('error', 'Le compte Super Admin ne peut pas être supprimé.');
            return $this->redirectToRoute('admin_users');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            $this->activityLogger->log(
                $this->getUser(),
                'USER_DELETED',
                'User',
                $user->getId(),
                'Utilisateur supprimé'
            );
            
            $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide, impossible de supprimer l\'utilisateur.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/activations/count', name: 'admin_count_pending_activations')]
    public function countPendingActivations(): Response
    {
        $qb = $this->verificationRepository->createQueryBuilder('uv')
            ->select('COUNT(uv.id)')
            ->innerJoin('uv.user', 'u')
            ->where('uv.status = :status')
            ->setParameter('status', 'pending');

        $staffRoles = [
            'ROLE_ADMIN', 'ROLE_SUPPORT', 'ROLE_FINANCE', 'ROLE_MANAGER', 
            'ROLE_CAISSIER', 'ROLE_COMPTABLE', 'ROLE_SUPERVISOR', 'ROLE_SUPER_ADMIN'
        ];

        foreach ($staffRoles as $role) {
            $qb->andWhere($qb->expr()->notLike('u.roles', ':role_'.$role));
            $qb->setParameter('role_'.$role, '%"'.$role.'"%');
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return $this->render('admin/partiales/_pending_count.html.twig', [
            'count' => $count
        ]);
    }

    #[Route('/audit', name: 'admin_audit_logs')]
    public function auditLogs(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'audit');
        $logs = $this->activityLogRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/pages/audit/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/audit/{id}', name: 'admin_audit_detail')]
    public function auditLogDetail(int $id): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'audit');
        $log = $this->activityLogRepository->find($id);
        
        if (!$log) {
            throw $this->createNotFoundException('Log non trouvé');
        }

        return $this->render('admin/pages/audit/show.html.twig', [
            'log' => $log,
        ]);
    }

    #[Route('/users/create', name: 'admin_user_create')]
    public function createUser(Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');
        
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }
            
            $user->setIsVerified(true);
            $user->setIsActive(true);
            $user->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->activityLogger->log(
                $this->getUser(),
                'USER_CREATE',
                'User',
                $user->getId(),
                'Nouvel utilisateur créé : ' . $user->getEmail()
            );

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            
            $staffRoles = ['ROLE_ADMIN', 'ROLE_SUPPORT', 'ROLE_FINANCE', 'ROLE_MANAGER', 'ROLE_CAISSIER', 'ROLE_COMPTABLE', 'ROLE_SUPERVISOR', 'ROLE_SUPER_ADMIN'];
            $isStaff = false;
            foreach ($user->getRoles() as $role) {
                if (in_array($role, $staffRoles)) {
                    $isStaff = true;
                    break;
                }
            }
            
            return $this->redirectToRoute('admin_users', ['group' => $isStaff ? 'staff' : 'clients']);
        }

        return $this->render('admin/pages/users/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/users/{id}/edit', name: 'admin_users_edit')]
    public function editUser(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'users');
        
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            $this->entityManager->flush();

            $this->activityLogger->log(
                $this->getUser(),
                'USER_EDIT',
                'User',
                $user->getId(),
                'Utilisateur modifié : ' . $user->getEmail()
            );

            $this->addFlash('success', 'Utilisateur mis à jour avec succès.');
            
            $staffRoles = ['ROLE_ADMIN', 'ROLE_SUPPORT', 'ROLE_FINANCE', 'ROLE_MANAGER', 'ROLE_CAISSIER', 'ROLE_COMPTABLE', 'ROLE_SUPERVISOR', 'ROLE_SUPER_ADMIN'];
            $isStaff = false;
            foreach ($user->getRoles() as $role) {
                if (in_array($role, $staffRoles)) {
                    $isStaff = true;
                    break;
                }
            }
            
            return $this->redirectToRoute('admin_users', ['group' => $isStaff ? 'staff' : 'clients']);
        }

        return $this->render('admin/pages/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
