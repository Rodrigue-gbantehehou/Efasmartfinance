<?php 
// src/Controller/Admin/TransactionAdminController.php
namespace App\Controller\Admin;

use App\Repository\TransactionRepository;
use App\Repository\PlatformFeeRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/transactions')]
#[IsGranted('ROLE_SUPPORT')]
class TransactionAdminController extends AbstractController
{
    #[Route('', name: 'admin_transactions')]
    public function index(TransactionRepository $transactionRepository, \App\Repository\WithdrawalsRepository $withdrawalsRepository, PlatformFeeRepository $feeRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'transactions');
        $transactions = $transactionRepository->findBy([], ['createdAt' => 'DESC']);
        $fees = $feeRepository->findBy([], ['createdAt' => 'DESC']);
        
        // Calculate statistics
        $totalEntrees = 0;
        $totalPending = 0;
        $totalSorties = $withdrawalsRepository->getTotalApprovedAmount();
        $transactionsCount = count($transactions);
        $transactionsMois = 0;
        
        // Calculate total fees
        $totalFees = array_reduce($fees, function($carry, $fee) {
            return $carry + $fee->getAmount();
        }, 0);
        
        $now = new \DateTime();
        $monthlyFees = array_reduce($fees, function($carry, $fee) use ($now) {
            if ($fee->getCreatedAt()->format('Y-m') === $now->format('Y-m')) {
                return $carry + $fee->getAmount();
            }
            return $carry;
        }, 0);
        
        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->getAmount();
            $statut = strtolower($transaction->getStatut() ?? '');
            
            // Only count successful transactions for Entrees
            if (in_array($statut, ['completed', 'validé', 'success'])) {
                if ($amount > 0) {
                    $totalEntrees += $amount;
                }
            } 
            // Count pending transactions separately
            elseif (in_array($statut, ['pending', 'en attente', 'in_progress'])) {
                if ($amount > 0) {
                    $totalPending += $amount;
                }
            }
            
            // Count transactions from the last 30 days
            if ($transaction->getCreatedAt() > new \DateTime('-30 days')) {
                $transactionsMois++;
            }
        }
        
        return $this->render('admin/pages/transactions/index.html.twig', [
            'transactions' => $transactions,
            'fees' => $fees,
            'totalEntrees' => $totalEntrees,
            'totalPending' => $totalPending,
            'totalSorties' => $totalSorties,
            'totalFees' => $totalFees,
            'monthlyFees' => $monthlyFees,
            'transactionsCount' => $transactionsCount,
            'transactionsMois' => $transactionsMois,
        ]);
    }

    #[Route('/{id}', name: 'admin_transaction_show', methods: ['GET'])]
    public function show($id, TransactionRepository $transactionRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'transactions');
        $transaction = $transactionRepository->find($id);
        
        if (!$transaction) {
            throw $this->createNotFoundException('Transaction non trouvée');
        }
        
        return $this->render('admin/pages/transactions/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }
}