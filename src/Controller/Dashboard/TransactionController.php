<?php

namespace App\Controller\Dashboard;

use App\Entity\Transaction;
use App\Form\TransactionType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TransactionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard/transaction')]
final class TransactionController extends AbstractController
{
    #[Route(name: 'app_transaction_index', methods: ['GET'])]
    public function index(
        TransactionRepository $transactionRepository, 
        \Knp\Component\Pager\PaginatorInterface $paginator,
        Request $request
    ): Response
    {
        $user = $this->getUser();
        $allTransactions = $transactionRepository->findBy(['utilisateur' => $user], ['createdAt' => 'DESC']);
        
        // Calculate statistics based on all transactions of the user
        $totalEntrees = 0;
        $totalSorties = 0;
        $transactionsCount = count($allTransactions);
        $transactionsMois = 0;
        
        foreach ($allTransactions as $transaction) {
            $amount = (float) $transaction->getAmount();
            $paymentMethod = $transaction->getPaymentMethod();
            
            if (str_contains(strtolower($paymentMethod ?? ''), 'deposit') || 
                str_contains(strtolower($paymentMethod ?? ''), 'entree') ||
                $transaction->getAmount() > 0) {
                $totalEntrees += $amount;
            } else {
                $totalSorties += abs($amount);
            }
            
            if ($transaction->getCreatedAt() > new \DateTime('-30 days')) {
                $transactionsMois++;
            }
        }

        // Use QueryBuilder for pagination to support dynamic sorting by KnpPaginator
        $qb = $transactionRepository->createQueryBuilder('t')
            ->where('t.utilisateur = :user')
            ->setParameter('user', $user);

        // Default sort - KnpPaginator will override this if sort/direction params are present
        if (!$request->query->get('sort')) {
            $qb->orderBy('t.createdAt', 'DESC');
        }

        $transactions = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10 // items per page
        );
        
        return $this->render('dashboard/pages/transaction/index.html.twig', [
            'transactions' => $transactions,
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

    #[Route('/export', name: 'app_transaction_export', methods: ['GET'])]
    public function export(TransactionRepository $transactionRepository, Request $request): Response
    {
        $user = $this->getUser();
        $qb = $transactionRepository->createQueryBuilder('t')
            ->where('t.utilisateur = :user')
            ->setParameter('user', $user);

        // Dynamic sorting for export too
        $sort = $request->query->get('sort', 't.createdAt');
        $direction = strtoupper($request->query->get('direction', 'DESC'));
        
        // Basic validation for sort field to avoid DQL injection
        $allowedSorts = ['t.id', 't.amount', 't.createdAt', 't.statut'];
        if (in_array($sort, $allowedSorts)) {
            $qb->orderBy($sort, $direction === 'ASC' ? 'ASC' : 'DESC');
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }

        $transactions = $qb->getQuery()->getResult();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // En-têtes optimisés
        $headers = [
            'A1' => 'Référence',
            'B1' => 'Date & Heure',
            'C1' => 'Type',
            'D1' => 'Méthode',
            'E1' => 'Montant (FCFA)',
            'F1' => 'Statut',
            'G1' => 'Tontine'
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style des en-têtes (Gras)
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF0F9F1'); // bg-green-50

        $row = 2;
        foreach ($transactions as $transaction) {
            $amount = $transaction->getAmount();
            // Nettoyage du montant pour n'avoir que le chiffre (pour calculs dans Excel)
            $cleanAmount = (float) preg_replace('/[^0-9]/', '', $amount);
            
            $sheet->setCellValue('A' . $row, $transaction->getExternalReference() ?? 'TR' . $transaction->getId())
                  ->setCellValue('B' . $row, $transaction->getCreatedAt()->format('d/m/Y H:i'))
                  ->setCellValue('C' . $row, strtoupper($transaction->getType() ?? 'N/A'))
                  ->setCellValue('D' . $row, strtoupper(str_replace('_', ' ', $transaction->getPaymentMethod() ?? 'N/A')))
                  ->setCellValue('E' . $row, $cleanAmount)
                  ->setCellValue('F' . $row, ucfirst($transaction->getStatut() ?? 'Inconnu'))
                  ->setCellValue('G' . $row, $transaction->getTontine() ? $transaction->getTontine()->getName() : 'Global');
            
            // Format monétaire pour la colonne E
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0 "FCFA"');
            
            $row++;
        }

        // Ajuster la largeur des colonnes automatiquement
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $response = new Response();
        $filename = 'export_transactions_' . date('d-m-Y_H-i') . '.xlsx';
        
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $disposition);

        ob_start();
        $writer->save('php://output');
        $response->setContent(ob_get_clean());

        return $response;
    }

    #[Route('/{id}', name: 'app_transaction_show', methods: ['GET'])]
    public function show(Transaction $transaction): Response
    {
        return $this->render('dashboard/pages/transaction/show.html.twig', [
            'transaction' => $transaction,
        ]);
    }

    #[Route('/{id}/details', name: 'app_transaction_details', methods: ['GET'])]
    public function details(Transaction $transaction): Response
    {
        return $this->render('dashboard/pages/transaction/_details.html.twig', [
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
