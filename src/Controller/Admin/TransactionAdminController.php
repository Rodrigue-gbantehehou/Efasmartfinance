<?php 
// src/Controller/Admin/TransactionAdminController.php
namespace App\Controller\Admin;

use App\Repository\TransactionRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/transactions')]
class TransactionAdminController extends AbstractController
{
    #[Route('', name: 'admin_transactions')]
    public function index(TransactionRepository $transactionRepository): Response
    {
        $transactions = $transactionRepository->findBy([], ['createdAt' => 'DESC']);
        
        // Calculate statistics
        $totalEntrees = 0;
        $totalSorties = 0;
        $transactionsCount = count($transactions);
        $transactionsMois = 0;
        
        foreach ($transactions as $transaction) {
            $amount = (float) $transaction->getAmount();
            $paymentMethod = $transaction->getPaymentMethod();
            
            // Assuming 'deposit' or similar indicates an incoming transaction
            // Adjust these conditions based on your actual payment methods
            if (str_contains(strtolower($paymentMethod ?? ''), 'deposit') || 
                str_contains(strtolower($paymentMethod ?? ''), 'entree') ||
                $transaction->getAmount() > 0) {
                $totalEntrees += $amount;
            } else {
                $totalSorties += abs($amount); // Use absolute value to ensure positive numbers
            }
            
            // Count transactions from the last 30 days
            if ($transaction->getCreatedAt() > new \DateTime('-30 days')) {
                $transactionsMois++;
            }
        }
        
        return $this->render('admin/pages/transactions/index.html.twig', [
            'transactions' => $transactions,
            'totalEntrees' => $totalEntrees,
            'totalSorties' => $totalSorties,
            'transactionsCount' => $transactionsCount,
            'transactionsMois' => $transactionsMois,
        ]);
    }

    #[Route('/{id}', name: 'admin_transaction_show', methods: ['GET'])]
    public function show($id, TransactionRepository $transactionRepository): Response
    {
        $transaction = $transactionRepository->find($id);
        
        if (!$transaction) {
            throw $this->createNotFoundException('Transaction non trouvée');
        }
        
        return $this->render('admin/pages/transactions/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }
}