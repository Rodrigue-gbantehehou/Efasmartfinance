<?php

namespace App\Controller\Admin;

use App\Entity\PlatformFee;
use App\Repository\PlatformFeeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/platform-fees')]
#[IsGranted('ROLE_ADMIN')]
class PlatformFeeController extends AbstractController
{
    #[Route('/', name: 'admin_platform_fees_index', methods: ['GET'])]
    public function index(PlatformFeeRepository $platformFeeRepository): Response
    {
        $fees = $platformFeeRepository->findBy([], ['createdAt' => 'DESC']);
        $now = new \DateTime();
        
        // Calcul du total des frais
        $totalFees = array_reduce($fees, function($carry, $fee) {
            return $carry + $fee->getAmount();
        }, 0);
        
        // Calcul des frais du mois en cours
        $monthlyFees = array_reduce($fees, function($carry, $fee) use ($now) {
            if ($fee->getCreatedAt()->format('Y-m') === $now->format('Y-m')) {
                return $carry + $fee->getAmount();
            }
            return $carry;
        }, 0);

        return $this->render('admin/platform_fee/index.html.twig', [
            'fees' => $fees,
            'totalFees' => $totalFees,
            'monthlyFees' => $monthlyFees,
        ]);
    }
}
