<?php

namespace App\Controller\Admin;

use App\Repository\TransactionRepository;
use App\Repository\PlatformFeeRepository;
use App\Repository\FactureRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/financial')]
#[IsGranted('ROLE_SUPPORT')]
class FinancialDashboardController extends AbstractController
{
    public function __construct(
        private TransactionRepository $transactionRepo,
        private PlatformFeeRepository $feeRepo,
        private FactureRepository $factureRepo
    ) {}

    #[Route('', name: 'admin_financial_dashboard')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'financial');
        $today = new \DateTimeImmutable();
        $startOfMonth = new \DateTimeImmutable('first day of this month');
        $startOfYear = new \DateTimeImmutable('first day of January this year');

        // KPI - Revenus du mois
        $monthlyRevenue = $this->transactionRepo->createQueryBuilder('t')
            ->select('SUM(t.amount) as total')
            ->where('t.createdAt >= :start')
            ->andWhere('t.statut = :status')
            ->setParameter('start', $startOfMonth)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // KPI - Frais collectés ce mois
        $monthlyFees = $this->feeRepo->createQueryBuilder('f')
            ->select('SUM(f.amount) as total')
            ->where('f.createdAt >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // KPI - Factures en attente
        $pendingInvoices = $this->factureRepo->count(['statut' => 'impayee']);

        // KPI - Total factures émises ce mois
        $monthlyInvoices = $this->factureRepo->createQueryBuilder('f')
            ->select('COUNT(f.id) as total')
            ->where('f.dateEmission >= :start')
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Graphique - Évolution des revenus (12 derniers mois)
        $revenueChart = $this->getMonthlyRevenueChart();

        // Dernières transactions
        $recentTransactions = $this->transactionRepo->findBy(
            ['statut' => 'completed'],
            ['createdAt' => 'DESC'],
            10
        );

        // Statistiques des frais par type
        $feesByType = $this->feeRepo->createQueryBuilder('f')
            ->select('f.type, SUM(f.amount) as total, COUNT(f.id) as count')
            ->where('f.createdAt >= :start')
            ->setParameter('start', $startOfMonth)
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();

        return $this->render('admin/pages/financial/dashboard.html.twig', [
            'monthlyRevenue' => $monthlyRevenue,
            'monthlyFees' => $monthlyFees,
            'pendingInvoices' => $pendingInvoices,
            'monthlyInvoices' => $monthlyInvoices,
            'revenueChart' => $revenueChart,
            'recentTransactions' => $recentTransactions,
            'feesByType' => $feesByType,
        ]);
    }

    private function getMonthlyRevenueChart(): array
    {
        $labels = [];
        $data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $date = new \DateTimeImmutable("-$i months");
            $startOfMonth = new \DateTimeImmutable($date->format('Y-m-01'));
            $endOfMonth = new \DateTimeImmutable($date->format('Y-m-t 23:59:59'));
            
            $labels[] = $date->format('M Y');
            
            $revenue = $this->transactionRepo->createQueryBuilder('t')
                ->select('SUM(t.amount) as total')
                ->where('t.createdAt >= :start')
                ->andWhere('t.createdAt <= :end')
                ->andWhere('t.statut = :status')
                ->setParameter('start', $startOfMonth)
                ->setParameter('end', $endOfMonth)
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $data[] = (float) $revenue;
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
