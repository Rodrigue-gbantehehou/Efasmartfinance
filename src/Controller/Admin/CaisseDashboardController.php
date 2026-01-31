<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Repository\TransactionRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/caisse/dashboard')]
#[IsGranted('ROLE_CAISSIER')]
class CaisseDashboardController extends AbstractController
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private UserRepository $userRepository,
        private TontineRepository $tontineRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'admin_caisse_dashboard')]
    public function index(): Response
    {
        // Stats du jour pour le guichet
        $today = new \DateTime();
        $todayRevenue = $this->transactionRepository->getRevenueForDate($today);
        
        // Dernières transactions encaissées (global pour l'instant)
        $recentTransactions = $this->transactionRepository->findBy(
            ['paymentMethod' => 'cash'],
            ['createdAt' => 'DESC'],
            5
        );

        // Nombre de clients servis aujourd'hui (approximatif via transactions)
        $clientsServedToday = $this->transactionRepository->createQueryBuilder('t')
            ->select('COUNT(DISTINCT t.utilisateur)')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->andWhere('t.paymentMethod = :method')
            ->setParameter('start', (clone $today)->setTime(0, 0, 0))
            ->setParameter('end', (clone $today)->setTime(23, 59, 59))
            ->setParameter('method', 'cash')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/pages/caisse/dashboard.html.twig', [
            'today_revenue' => $todayRevenue ?? 0,
            'recent_transactions' => $recentTransactions,
            'clients_served' => $clientsServedToday,
        ]);
    }
}
