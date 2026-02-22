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
    public function __construct(
        private ActivityLogger $activityLogger,
        private \App\Service\NotificationService $notificationService,
        private \App\Service\EmailService $emailService
    ) {}
    
    #[Route('', name: 'admin_withdrawals')]
    public function index(WithdrawalsRepository $withdrawalRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW_MODULE', 'withdrawals');
        $withdrawals = $withdrawalRepository->findBy([], ['requestedAt' => 'DESC']);
        
        $approvedCount = $withdrawalRepository->count(['statut' => 'approved']);
        $totalCount = count($withdrawals);
        
        $stats = [
            'pending_count' => $withdrawalRepository->count(['statut' => 'pending']),
            'pending_amount' => $withdrawalRepository->getPendingAmount(),
            'total_approved' => $withdrawalRepository->getTotalApprovedAmount(),
            'total_count' => $totalCount,
            'success_rate' => $totalCount > 0 ? ($approvedCount / $totalCount) * 100 : 0,
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
            $this->activityLogger->log(
                $this->getUser(),
                'withdrawal_approved',
                'Withdrawals',
                $withdrawal->getId(),
                'Withdrawal #' . $withdrawal->getId() . ' a été approuvé par ' . $this->getUser()->getUuid() 
            );

            // Notification in-app pour l'utilisateur
            $this->notificationService->sendWithdrawalApprovedNotification(
                $withdrawal->getUtilisateur(),
                (float) $withdrawal->getAmount()
            );

            // Notification Email
            $this->emailService->sendWithdrawalApprovedEmail(
                $withdrawal->getUtilisateur(),
                (float) $withdrawal->getAmount()
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
            $reason = trim($request->request->get('rejection_reason', ''));
            
            if (empty($reason)) {
                $this->addFlash('error', 'Vous devez obligatoirement fournir un motif de rejet.');
                return $this->redirectToRoute('admin_withdrawal_show', ['id' => $withdrawal->getId()]);
            }

            $withdrawal->setStatut('rejected');
            $withdrawal->setRejectionReason($reason);
            $withdrawal->setProcessedAt(new \DateTimeImmutable());
            $withdrawal->setAdministrateur($this->getUser());
            
            $entityManager->flush();

            // Notify user in-app
            $this->notificationService->sendWithdrawalRejectedNotification(
                $withdrawal->getUtilisateur(),
                (float) $withdrawal->getAmount(),
                $reason
            );

            // Notify user by email
            $this->emailService->sendWithdrawalRejectedEmail(
                $withdrawal->getUtilisateur(),
                (float) $withdrawal->getAmount(),
                $reason
            );

            // Log the rejection
            $this->activityLogger->log(
                $this->getUser(),
                'withdrawal_rejected',
                'Withdrawals',
                $withdrawal->getId(),
                'Withdrawal #' . $withdrawal->getId() . ' a été rejeté par ' . $this->getUser()->getUuid() . '. Motif : ' . $reason
            );

            $this->addFlash('warning', 'Le retrait a été rejeté.');
        }

        return $this->redirectToRoute('admin_withdrawals', [], Response::HTTP_SEE_OTHER);
    }

    public function countPendingWithdrawals(WithdrawalsRepository $repository): Response
    {
        $count = $repository->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->where('w.statut = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $count = (int)$count;

        if ($count === 0) {
            return new Response('');
        }

        return new Response('<span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">' . $count . '</span>');
    }
}
