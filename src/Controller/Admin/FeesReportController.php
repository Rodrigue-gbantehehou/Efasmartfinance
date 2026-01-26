<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Tontine;
use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Repository\PlatformFeeRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/admin/fees')]
class FeesReportController extends AbstractController
{
    public function __construct(
        private PlatformFeeRepository $feeRepository,
        private TontineRepository $tontineRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('', name: 'app_admin_fees')]
    public function index(): Response
    {
        $today = new \DateTimeImmutable();
        
        return $this->render('admin/pages/fees/index.html.twig', [
            'dailyFees' => $this->feeRepository->getDailySummary($today),
            'monthlyFees' => $this->feeRepository->getMonthlySummary(
                (int) $today->format('Y'),
                (int) $today->format('m')
            ),
            'annualFees' => $this->feeRepository->getAnnualSummary(
                (int) $today->format('Y')
            ),
            'currentDate' => $today,
        ]);
    }

    #[Route('/by-tontine/{id}', name: 'app_admin_fees_by_tontine')]
    public function byTontine(int $id): Response
    {
        $tontine = $this->tontineRepository->find($id);
        
        if (!$tontine) {
            throw $this->createNotFoundException('Tontine non trouvée');
        }

        $fees = $this->feeRepository->getFeesByTontine($tontine);
        
        return $this->render('admin/pages/fees/by_tontine.html.twig', [
            'tontine' => $tontine,
            'fees' => $fees,
            'total' => array_reduce($fees, fn($carry, $item) => $carry + $item['total'], 0),
        ]);
    }

    #[Route('/by-user/{id}', name: 'app_admin_fees_by_user')]
    public function byUser(int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $fees = $this->feeRepository->getFeesByUser($user);
        
        return $this->render('admin/pages/fees/by_user.html.twig', [
            'user' => $user,
            'fees' => $fees,
            'total' => array_reduce($fees, fn($carry, $item) => $carry + $item['total'], 0),
        ]);
    }
}
