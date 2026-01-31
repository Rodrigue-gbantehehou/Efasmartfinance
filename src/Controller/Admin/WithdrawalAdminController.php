<?php

namespace App\Controller\Admin;

use App\Entity\Withdrawals;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\WithdrawalsRepository;
use App\Service\ActivityLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;

#[Route('/admin/withdrawals')]
#[IsGranted('ROLE_SUPPORT')]
class WithdrawalAdminController extends AbstractController
{
    public function __construct(private ActivityLogger $logger)
    {
    }
    
    #[Route('', name: 'admin_withdrawals')]
    public function index(WithdrawalsRepository $withdrawalRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'withdrawals');
        $withdrawals = $withdrawalRepository->findBy([], ['requestedAt' => 'DESC']);
        
        $stats = [
            'pending_count' => $withdrawalRepository->countPending(),
            'pending_amount' => $withdrawalRepository->getPendingAmount(),
            'total_approved' => $withdrawalRepository->getTotalApprovedAmount(),
            'total_count' => count($withdrawals),
        ];
        
        return $this->render('admin/withdrawals/index.html.twig', [
            'withdrawals' => $withdrawals,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'admin_withdrawal_show', methods: ['GET'])]
    public function show(Withdrawals $withdrawal): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'withdrawals');
        return $this->render('admin/withdrawals/show.html.twig', [
            'withdrawal' => $withdrawal,
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_withdrawal_approve', methods: ['POST'])]
    public function approve(Request $request, Withdrawals $withdrawal, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'withdrawals');
        if ($this->isCsrfTokenValid('approve'.$withdrawal->getId(), $request->request->get('_token'))) {
            $withdrawal->setStatut('approved');
            $withdrawal->setProcessedAt(new \DateTimeImmutable());
            $withdrawal->setAdministrateur($this->getUser());
            
           
            $entityManager->flush();

             // Log the approval
            $this->logger->log(
                $this->getUser(),
                'withdrawal_approved',
                'Withdrawals',
                $withdrawal->getId(),
                'Withdrawal #' . $withdrawal->getId() . ' a été approuvé par ' . $this->getUser()->getUuid() 
            );

            $this->addFlash('success', 'Le retrait a été approuvé avec succès.');
        }

        return $this->redirectToRoute('admin_withdrawals', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/reject', name: 'admin_withdrawal_reject', methods: ['POST'])]
    public function reject(Request $request, Withdrawals $withdrawal, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('EDIT_MODULE', 'withdrawals');
        if ($this->isCsrfTokenValid('reject'.$withdrawal->getId(), $request->request->get('_token'))) {
            $withdrawal->setStatut('rejected');
            $withdrawal->setProcessedAt(new \DateTimeImmutable());
            $withdrawal->setAdministrateur($this->getUser());
            
            $entityManager->flush();

            // Log the rejection
            $this->logger->log(
                $this->getUser(),
                'withdrawal_rejected',
                'Withdrawals',
                $withdrawal->getId(),
                'Withdrawal #' . $withdrawal->getId() . ' a été rejeté par ' . $this->getUser()->getUuid()  
            );

            $this->addFlash('warning', 'Le retrait a été rejeté.');
        }

        return $this->redirectToRoute('admin_withdrawals', [], Response::HTTP_SEE_OTHER);
    }
}
