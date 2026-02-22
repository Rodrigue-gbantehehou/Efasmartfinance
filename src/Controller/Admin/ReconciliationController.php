<?php

namespace App\Controller\Admin;

use App\Entity\PlatformFee;
use App\Entity\Tontine;
use App\Repository\PlatformFeeRepository;
use App\Repository\TontineRepository;
use App\Repository\TransactionRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reconciliation')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class ReconciliationController extends AbstractController
{
    public function __construct(
        private TontineRepository $tontineRepository,
        private TransactionRepository $transactionRepository,
        private PlatformFeeRepository $platformFeeRepository,
        private ActivityLogger $activityLogger
    ) {
    }

    #[Route('', name: 'admin_reconciliation_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'audit');

        $tontines = $this->tontineRepository->findAll();
        $anomalies = [];
        $totalTontines = count($tontines);
        $healthyCount = 0;

        foreach ($tontines as $tontine) {
            $savingsTypes = ['tontine', 'contribution'];
            
            // 1. Somme brute des transactions de "cotisation"
            $txPotSum = $this->transactionRepository->getSumByTontineAndTypes($tontine, $savingsTypes);
            
            // 2. Somme des parties "frais" incluses dans ces cotisations (ex: les 3F sur 103F)
            $feePortionSum = $this->platformFeeRepository->getSumOfLinkedFeesByTontineAndTypes($tontine, $savingsTypes);
            
            // 3. Pot réel = Somme Brute - Portions Frais
            $potTransactions = $txPotSum - $feePortionSum;
            
            // 4. Frais réels = Somme de TOUS les PlatformFees
            $feeTransactions = $this->platformFeeRepository->getSumByTontine($tontine);
            
            $totalPay = (float) $tontine->getTotalPay();
            $paidFeesHost = (int) $tontine->getPaidFees();

            $potAnomalie = abs($potTransactions - $totalPay) > 0.1; // Seuil 0.1 pour tolérer virgule flottante
            $feeAnomalie = abs($feeTransactions - $paidFeesHost) > 0.1;

            if ($potAnomalie || $feeAnomalie) {
                $anomalies[] = [
                    'tontine' => $tontine,
                    'potTransactionSum' => $potTransactions,
                    'totalPay' => $totalPay,
                    'feeTransactionSum' => $feeTransactions,
                    'paidFees' => $paidFeesHost,
                    'diffPot' => $totalPay - $potTransactions,
                    'diffFees' => $paidFeesHost - $feeTransactions
                ];
            } else {
                $healthyCount++;
            }
        }

        return $this->render('admin/pages/reconciliation/index.html.twig', [
            'anomalies' => $anomalies,
            'stats' => [
                'total' => $totalTontines,
                'healthy' => $healthyCount,
                'anomalies' => count($anomalies)
            ]
        ]);
    }

    #[Route('/{id}', name: 'admin_reconciliation_show')]
    public function show(Tontine $tontine, \App\Repository\ActivityLogRepository $logRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'audit');

        $transactions = $this->transactionRepository->findBy([
            'Tontine' => $tontine,
            'statut' => 'completed'
        ], ['createdAt' => 'DESC']);

        $sumTransactions = 0;
        $txLogs = [];

        foreach ($transactions as $tx) {
            $sumTransactions += (float) $tx->getAmount();
            
            // Fetch potential logs for this transaction
            $logs = $logRepository->findBy([
                'entityType' => 'Transaction',
                'entityId' => $tx->getId()
            ], ['createdAt' => 'DESC']);
            
            $txLogs[$tx->getId()] = $logs;
        }

        $savingsTypes = ['tontine', 'contribution'];
        $txPotSum = $this->transactionRepository->getSumByTontineAndTypes($tontine, $savingsTypes);
        $feePortionSum = $this->platformFeeRepository->getSumOfLinkedFeesByTontineAndTypes($tontine, $savingsTypes);
        
        $potTransactions = $txPotSum - $feePortionSum;
        $feeTransactions = $this->platformFeeRepository->getSumByTontine($tontine);

        return $this->render('admin/pages/reconciliation/show.html.twig', [
            'tontine' => $tontine,
            'transactions' => $transactions,
            'transactionLogs' => $txLogs,
            'potTransactions' => $potTransactions,
            'feeTransactions' => $feeTransactions,
            'totalPay' => (float) $tontine->getTotalPay(),
            'paidFees' => (float) $tontine->getPaidFees(),
            'diffPot' => (float) $tontine->getTotalPay() - $potTransactions,
            'diffFees' => (float) $tontine->getPaidFees() - $feeTransactions
        ]);
    }

    #[Route('/{id}/sync', name: 'admin_reconciliation_sync', methods: ['POST'])]
    public function sync(Tontine $tontine, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'audit');
        
        if (!$this->isCsrfTokenValid('sync' . $tontine->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('admin_reconciliation_index');
        }

        $savingsTypes = ['tontine', 'contribution'];
        $txPotSum = $this->transactionRepository->getSumByTontineAndTypes($tontine, $savingsTypes);
        $feePortionSum = $this->platformFeeRepository->getSumOfLinkedFeesByTontineAndTypes($tontine, $savingsTypes);
        
        $potTransactions = $txPotSum - $feePortionSum;
        $feeTransactions = $this->platformFeeRepository->getSumByTontine($tontine);

        // Mettre à jour les champs cumulatifs de la tontine à partir de l'historique réel
        $tontine->setTotalPay((string)$potTransactions);
        $tontine->setPaidFees((int)$feeTransactions);

        $em->flush();

        $this->addFlash('success', sprintf('La tontine "%s" a été resynchronisée avec son historique de transactions.', $tontine->getName()));
        
        return $this->redirectToRoute('admin_reconciliation_index');
    }
}
