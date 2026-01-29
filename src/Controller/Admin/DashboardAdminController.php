<?php
// src/Controller/DashboardAdmin/DashboardAdminController.php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Entity\ActivityLog;
use App\Service\ActivityLogger;
use App\Repository\UserRepository;
use App\Service\UniqueCodeGenerator;
use App\Repository\TontineRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin')]
//#[IsGranted('ROLE_ADMIN')]
class DashboardAdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TontineRepository $tontineRepository,
        private TransactionRepository $transactionRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UniqueCodeGenerator $uniqueCodeGenerator,
        private ActivityLogger $activityLogger,
    ) {
    }
    
    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response {
        // Données pour le graphique des revenus (7 derniers jours)
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-6 days');
        
        $revenueData = [];
        $labels = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $labels[] = $currentDate->format('d M');
            // Récupérer le revenu pour cette date
            $revenue = $this->transactionRepository->getRevenueForDate($currentDate);
            $revenueData[] = $revenue ?? 0;
            $currentDate->modify('+1 day');
        }

        // Get statistics
        $stats = [
            'total_users' => $this->userRepository->count([]),
            'new_users_today' => $this->userRepository->countNewUsersToday(),
            'user_growth' => $this->calculateUserGrowth($this->userRepository),
            'active_tontines' => $this->tontineRepository->countActive(),
            'completed_tontines' => $this->tontineRepository->countCompleted(),
            'pending_tontines' => $this->tontineRepository->count(['statut' => 'pending']),
            'total_transactions' => $this->transactionRepository->count([]),
            'today_transactions' => $this->transactionRepository->countTodayTransactions(),
            'total_revenue' => $this->transactionRepository->getTotalRevenue(),
            'revenue_chart' => [
                'labels' => $labels,
                'data' => $revenueData,
            ],
            'revenue_growth' => $this->calculateRevenueGrowth($this->transactionRepository),
            'avg_transaction' => $this->transactionRepository->getAverageTransactionAmount(),
            'new_users_month' => $this->userRepository->countNewUsersThisMonth(),
            'new_tontines_month' => $this->tontineRepository->countNewTontinesThisMonth(),
            'transaction_volume_month' => $this->transactionRepository->getMonthlyVolume(),
            'satisfaction_rate' => $this->calculateSatisfactionRate(),
            'active_tontines_percentage' => $this->calculateActiveTontinesPercentage(),
            'success_rate' => 98.7, // Taux de succès des transactions
            'tontines_today' => $this->tontineRepository->countTontinesToday(),
        ];
        
        // Get recent activities
        $recentActivities = $this->getRecentActivities();
        
        // Données pour le graphique de performance financière (12 derniers mois)
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-12 months');
        $revenueData = $this->transactionRepository->getMonthlyRevenue($startDate, $endDate);
        
        // Données pour la répartition des tontines
        $tontineStats = [
            'labels' => ['Actives', 'Terminées', 'En attente'],
            'data' => [
                $stats['active_tontines'],
                $stats['completed_tontines'],
                $stats['pending_tontines']
            ],
            'colors' => ['#008040', '#3B82F6', '#F59E0B']
        ];
        
        // Dernières transactions
        $recentTransactions = $this->transactionRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            5
        );
        
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_activities' => $recentActivities,
            'recent_transactions' => $recentTransactions,
            'revenue_data' => $revenueData,
            'tontine_stats' => json_encode($tontineStats),
            'user_count' => $stats['total_users'],
            'tontine_count' => $stats['active_tontines'] + $stats['completed_tontines'] + $stats['pending_tontines'],
            'transaction_count' => $stats['total_transactions'],
            'support_ticket_count' => $this->getSupportTicketCount(),
            'online_users' => $this->getOnlineUsersCount(),
        ]);
    }
    
    #[Route('/users', name: 'admin_users')]
    public function usersList(): Response
    {
        $users = $this->userRepository->findAll();
        
        return $this->render('admin/pages/users/list.html.twig', [
            'users' => $users,
        ]);
    }
    
    #[Route('/users/{id}/details', name: 'admin_users_details')]
    public function userDetails(int $id): Response
    {
        $user = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.tontines', 't')
            ->leftJoin('u.transactions', 'tr')
            ->leftJoin('u.wallets', 'w')
            ->leftJoin('u.contactSupports', 'cs')
            ->leftJoin('u.userTermsAcceptances', 'uta')
            ->leftJoin('u.activityLogs', 'al')
            ->leftJoin('u.securityLogs', 'sl')
            ->leftJoin('u.withdrawals', 'wd')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        // Récupérer les statistiques
        $stats = [
            'tontines_count' => $user->getTontines()->count(),
            'transactions_count' => $user->getTransactions()->count(),
            'wallets_count' => $user->getWallets()->count(),
            'withdrawals_count' => $user->getWithdrawals()->count(),
            'activity_logs_count' => $user->getActivityLogs()->count(),
            'security_logs_count' => $user->getSecurityLogs()->count(),
        ];
        
        return $this->render('admin/pages/users/details.html.twig', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }
    
    #[Route('/users/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    public function createUser(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            
            // Générer un UUID unique
            $user->setUuid($this->uniqueCodeGenerator->generateUserCode());
            
            // Définir la date de création
            $user->setCreatedAt(new \DateTimeImmutable());
            
            // Activer le compte par défaut
            $user->setIsActive(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->activityLogger->log(
                $this->getUser(),  // The admin performing the update
                'USER_CREATED',    // Action type
                'User',            // Entity type
                $user->getId(),    // Updated user's ID
                sprintf(
                    'creation  de l\'utilisateur %s (ID: %d) par %s (ID: %s)',
                    $user->getEmail() ?? 'N/A',
                    $user->getId(),
                    $this->getUser() ? $this->getUser()->getEmail() : 'système',
                    $this->getUser() ? $this->getUser()->getUuid() : 'système'
                )
            );
            $this->addFlash('success', 'L\'utilisateur a été créé avec succès.');
            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/pages/users/create.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }
    
    #[Route('/users/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, int $id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Si un nouveau mot de passe a été fourni
            if ($form->get('plainPassword')->getData()) {
                $user->setPassword(
                    $this->passwordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );
            }
            
            $this->entityManager->flush();

            $this->activityLogger->log(
                $this->getUser(),  // The admin performing the update
                'USER_UPDATED',    // Action type
                'User',            // Entity type
                $user->getId(),    // Updated user's ID
                sprintf(
                    'Mise à jour des informations de l\'utilisateur %s (ID: %d) par %s (ID: %s)',
                    $user->getEmail() ?? 'N/A',
                    $user->getId(),
                    $this->getUser() ? $this->getUser()->getEmail() : 'système',
                    $this->getUser() ? $this->getUser()->getUuid() : 'système'
                )
            );
    
            
            $this->addFlash('success', 'L\'utilisateur a été mis à jour avec succès.');
            return $this->redirectToRoute('admin_users');
        }
        
        return $this->render('admin/pages/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }
    
    #[Route('/tontines', name: 'admin_tontines')]
    public function tontines(TontineRepository $tontineRepository): Response
    {
        $tontines = $tontineRepository->findAll();
        
        return $this->render('admin/pages/tontines/index.html.twig', [
            'tontines' => $tontines,
            'tontine_count' => count($tontines),
        ]);
    }
    
    #[Route('/transactions', name: 'admin_transactions')]
    public function transactions(TransactionRepository $transactionRepository): Response
    {
        $transactions = $transactionRepository->findAll();
        
        return $this->render('admin/pages/transactions/index.html.twig', [
            'transactions' => $transactions,
            'transaction_count' => count($transactions),
        ]);
    }
    
    #[Route('/reports', name: 'admin_reports')]
    public function reports(): Response
    {
        return $this->render('admin/pages/reports/index.html.twig');
    }
    
    #[Route('/support', name: 'admin_support')]
    public function support(): Response
    {
        return $this->render('admin/pages/support/index.html.twig');
    }
    
    #[Route('/audit/logs', name: 'admin_audit_logs')]
    public function auditLogs(Request $request): Response
    {
        $logs = $this->entityManager->getRepository(ActivityLog::class)
            ->createQueryBuilder('a')
            ->leftJoin('a.utilisateur', 'u')
            ->addSelect('u')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
            
        // Gestion de l'export CSV
        if ($request->query->get('export') === 'csv') {
            return $this->exportLogsToCsv($logs);
        }
            
        return $this->render('admin/pages/audit/logs.html.twig', [
            'logs' => $logs,
        ]);
    }
    
    private function exportLogsToCsv(array $logs): Response
    {
        $filename = 'logs-audit-' . date('Y-m-d') . '.csv';
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        
        $handle = fopen('php://output', 'w+');
        
        // En-têtes CSV
        fputcsv($handle, [
            'Date',
            'Utilisateur',
            'Email',
            'Action',
            'Détails',
            'Adresse IP'
        ], ';');
        
        // Données
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->getCreatedAt() ? $log->getCreatedAt()->format('d/m/Y H:i:s') : '',
                $log->getUtilisateur() ? ($log->getUtilisateur()->getFirstName() . ' ' . $log->getUtilisateur()->getLastName()) : 'Système',
                $log->getUtilisateur() ? ($log->getUtilisateur()->getEmail() ?? 'N/A') : 'Système',
                $log->getActions(),
                $log->getDescription(),
                $log->getIpAdress()
            ], ';');
        }
        
        fclose($handle);
        
        return $response;
    }
    
    #[Route('/settings', name: 'admin_settings')]
    public function settings(): Response
    {
        return $this->render('admin/pages/settings/index.html.twig');
    }
    
    #[Route('/backup', name: 'admin_backup')]
    public function backup(): Response
    {
        return $this->render('admin/pages/backup/index.html.twig');
    }
    
    #[Route('/profile', name: 'admin_profile')]
    public function profile(): Response
    {
        return $this->render('admin/pages/profile/index.html.twig');
    }
    
    private function calculateUserGrowth(UserRepository $userRepository): float
    {
        $currentMonthCount = $userRepository->countThisMonth();
        $lastMonthCount = $userRepository->countLastMonth();
        
        if ($lastMonthCount === 0) {
            return $currentMonthCount > 0 ? 100.0 : 0.0;
        }
        
        return round((($currentMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1);
    }
    
    private function calculateRevenueGrowth(TransactionRepository $transactionRepository): float
    {
        $currentMonthRevenue = $transactionRepository->getCurrentMonthRevenue();
        $lastMonthRevenue = $transactionRepository->getLastMonthRevenue();
        
        if ($lastMonthRevenue === 0) {
            return $currentMonthRevenue > 0 ? 100.0 : 0.0;
        }
       if ($lastMonthRevenue == 0) {
        return $currentMonthRevenue > 0 ? -100.0 : 0.0;
       } else {
        return round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1);
       }
    }
    
    private function getRecentActivities(): array
    {
        // This should come from your activity log system

        $recentActivities = $this->entityManager->getRepository(ActivityLog::class)->findBy([], ['createdAt' => 'DESC'], 5);
        
        return $recentActivities;
    }
    
    private function getOnlineUsersCount(): int
    {
        // Compte les utilisateurs actifs dans les 5 dernières minutes
        $fiveMinutesAgo = new \DateTimeImmutable('-5 minutes');
        
        return $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT a.utilisateur) as onlineCount')
            ->from(ActivityLog::class, 'a')
            ->where('a.createdAt >= :fiveMinutesAgo')
            ->setParameter('fiveMinutesAgo', $fiveMinutesAgo)
            ->getQuery()
            ->getSingleScalarResult() ?: 0;
    }
    
    private function calculateSatisfactionRate(): float
    {
        // Implémentez la logique pour calculer le taux de satisfaction
        // Par exemple, à partir d'avis ou de retours utilisateurs
        // Pour l'instant, retournons une valeur aléatoire entre 85 et 100
        return round(rand(850, 1000) / 10, 1);
    }
    
    private function calculateActiveTontinesPercentage(): float
    {
        $activeTontines = $this->tontineRepository->countActive();
        $totalTontines = $this->tontineRepository->count([]);
        
        if ($totalTontines === 0) {
            return 0.0;
        }
        
        return round(($activeTontines / $totalTontines) * 100, 1);
    }
    
    private function getSupportTicketCount(): int
    {
        // Compte les tickets de support non résolus
        return $this->entityManager->createQueryBuilder()
            ->select('COUNT(t)')
            ->from('App\Entity\ContactSupport', 't')
            ->getQuery()
            ->getSingleScalarResult() ?: 0;
    }
    
    private function getSystemStatus(): array
    {
        // Mémoire
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $memoryLimit = ini_get('memory_limit');
        $memoryPercent = round(($memoryUsage / $this->convertToBytes($memoryLimit)) * 100);
        $memoryStatus = $memoryPercent > 80 ? 'critique' : ($memoryPercent > 60 ? 'élevé' : 'optimal');

        // Stockage
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100) : 0;
        $diskStatus = $diskPercent > 90 ? 'critique' : ($diskPercent > 70 ? 'élevé' : 'stable');

        // Base de données
        $connection = $this->entityManager->getConnection();
        $dbStatus = $connection->isConnected() ? 'connecté' : 'déconnecté';

        // Version PHP
        $phpVersion = PHP_VERSION;
        $phpStatus = version_compare(PHP_VERSION, '8.1.0', '>=') ? 'à jour' : 'mise à jour nécessaire';

        return [
            'memory' => [
                'used' => $memoryUsage . ' MB',
                'total' => $memoryLimit,
                'percent' => $memoryPercent,
                'status' => $memoryStatus
            ],
            'disk' => [
                'used' => $this->formatBytes($diskUsed),
                'total' => $this->formatBytes($diskTotal),
                'free' => $this->formatBytes($diskFree),
                'percent' => $diskPercent,
                'status' => $diskStatus
            ],
            'database' => [
                'status' => $dbStatus,
                'version' => $connection->getWrappedConnection()->getServerVersion(),
                'driver' => $connection->getDriver()->getDatabasePlatform()->getName()
            ],
            'php' => [
                'version' => $phpVersion,
                'status' => $phpStatus,
                'os' => PHP_OS
            ],
            'server' => [
                'name' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
                'uptime' => $this->getServerUptime(),
                'load' => $this->getServerLoad()
            ]
        ];
    }

    private function convertToBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size)-1]);
        $size = (int) $size;

        switch($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getServerUptime(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 'N/A sur Windows';
        }

        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime === false) {
            return 'Inconnu';
        }

        $uptime = explode(' ', $uptime);
        $uptime = $uptime[0];
        $days = floor($uptime / 60 / 60 / 24);
        $hours = $uptime / 60 / 60 % 24;
        $mins = $uptime / 60 % 60;
        $secs = $uptime % 60;

        return sprintf('%dj %02dh %02dm %02ds', $days, $hours, $mins, $secs);
    }

    private function getServerLoad(): string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $wmi = new \COM('Winmgmts:\\\\.');
            $cpus = $wmi->execquery('SELECT LoadPercentage FROM Win32_Processor');
            $load = 0;
            $i = 0;
            foreach ($cpus as $cpu) {
                $load += $cpu->loadpercentage;
                $i++;
            }
            return $i > 0 ? round($load / $i) . '%' : 'N/A';
        }

        if (is_readable('/proc/loadavg')) {
            $load = sys_getloadavg();
            return $load[0] . ' (1min) ' . $load[1] . ' (5min) ' . $load[2] . ' (15min)';
        }

        return 'N/A';
    }

    private function getRecentTransactions(int $limit = 5): array
    {
        return $this->transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
        if ($this->isCsrfTokenValid('deactivate'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsActive(false);
            $this->entityManager->flush();
            
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
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'L\'utilisateur a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide, impossible de supprimer l\'utilisateur.');
        }

        return $this->redirectToRoute('admin_users');
    }
}