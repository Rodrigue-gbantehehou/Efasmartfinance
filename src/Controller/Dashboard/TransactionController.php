<?php

namespace App\Controller\Dashboard;

use App\Entity\Transaction;
use App\Form\TransactionType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard/transaction')]
final class TransactionController extends AbstractController
{
    #[Route(name: 'app_transaction_index', methods: ['GET'])]
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
        
        return $this->render('dashboard/pages/transaction/index.html.twig', [
            'transactions' => $transactionRepository->findBy(
                ['utilisateur' => $this->getUser()],
                ['createdAt' => 'DESC'],
            ),
            'totalEntrees' => $totalEntrees,
            'totalSorties' => $totalSorties,
            'transactionsCount' => $transactionsCount,
            'transactionsMois' => $transactionsMois,
        ]);
    }

    #[Route('/new', name: 'app_transaction_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $transaction = new Transaction();
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($transaction);
            $entityManager->flush();

            return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/transaction/new.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        return $this->render('dashboard/pages/transaction/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_transaction_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TransactionType::class, $transaction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/transaction/edit.html.twig', [
            'transaction' => $transaction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_transaction_delete', methods: ['POST'])]
    public function delete(Request $request, Transaction $transaction, EntityManagerInterface $entityManager): Response
    {
        // Soft delete the transaction
        $transaction->setIsDeleted(true);
        $entityManager->flush();

        // Check if it's an AJAX request
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => 'Transaction annulée avec succès'
            ]);
        }

        // For non-AJAX requests, redirect
        return $this->redirectToRoute('app_transaction_index', [], Response::HTTP_SEE_OTHER);
    }
}
