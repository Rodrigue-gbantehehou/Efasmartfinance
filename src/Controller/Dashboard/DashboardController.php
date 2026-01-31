<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {}

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Security $security): Response
    {
        $user = $security->getUser();
        $tontines = $this->entityManager->getRepository(Tontine::class)->findBy(['utilisateur' => $user]);
        
        // Fetch recent transactions
        $transactionsRepo = $this->entityManager->getRepository(\App\Entity\Transaction::class);
        $recentTransactions = $transactionsRepo->findBy(
            ['utilisateur' => $user],
            ['createdAt' => 'DESC'],
            10
        );

        // Calculate amount "payé mais non récupéré"
        // totalBalance: Net available across all tontines
        // totalPaidNotRecovered: Net available only for COMPLETED tontines
        $totalPaidNotRecovered = 0;
        $totalBalance = 0;
        foreach ($tontines as $tontine) {
            $availableAmount = (float)$tontine->getAvailableWithdrawalAmount();
            $totalBalance += $availableAmount;
            
            if ($tontine->getStatut() === 'completed') {
                $totalPaidNotRecovered += $availableAmount;
            }
        }

        $appData = [
            'user' => [
                'id' =>  $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
            'stats' => [
                'totalPaidNotRecovered' => $totalPaidNotRecovered,
                'totalBalance' => $totalBalance
            ]
        ];
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $appData['user'],
            'tontines' => $tontines,
            'recentTransactions' => $recentTransactions,
            'totalPaidNotRecovered' => $totalPaidNotRecovered,
            'totalBalance' => $totalBalance,
            'appDataJson' => json_encode($appData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT),
        ]);
    }

    #[Route('/carte-utilisateur', name: 'app_user_card')]
    public function userCard(Security $security): Response
    {
        $user = $security->getUser();
        $tontine = $this->entityManager->getRepository(Tontine::class)->findOneBy(['utilisateur' => $user]);
        
        // Generate QR code URL
        $qrCodeUrl = $this->generateUrl('app_public_tontine_card', [
            'code' => $tontine ? $tontine->getTontineCode() : ''
        ], true);

        return $this->render('dashboard/pages/user_card.html.twig', [
            'user' => $user,
            'tontine' => $tontine,
            'qrCodeUrl' => $qrCodeUrl
        ]);
    }

    #[Route('/api/tontines', name: 'api_tontines', methods: ['GET'])]
    public function getTontines(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $tontines = $this->entityManager->getRepository(Tontine::class)->findBy(['utilisateur' => $user]);
        

        
        // Formatage des données pour le frontend
        
        $formattedTontines = array_map(function($tontine) {
            $nextPayment=null;
            if($tontine->getNextDueDate() !== null) {
                $nextPayment = $tontine->getNextDueDate()->format('Y-m-d');
            }else {
                $nextPayment = $tontine->getStartDate()->format('Y-m-d');
            }
            return [
                'id' => $tontine->getId(),
                'name' => $tontine->getTontineCode() ?? 'Tontine #' . $tontine->getId(),
                'amount' => (float) $tontine->getAmountPerPoint() * (float) $tontine->getTotalPoints() - (float) $tontine->getTotalPay(),
                'period' => $tontine->getFrequency() ?? 'monthly',
                'startDate' => $tontine->getStartDate() ? $tontine->getStartDate()->format('Y-m-d') : null,
                'nextPayment' => $nextPayment,
                'progress' => $this->calculateProgress($tontine),
                'status' => $tontine->getStatut() ?? 'active',
                'totalPay'=>$tontine->getTotalPay(),
            ];
        }, $tontines);

        // Calculate statistics
        $totalBalance = array_sum(array_map(function($tontine) {
            return (float)$tontine->getAvailableWithdrawalAmount();
        }, $tontines));
        
        $activeTontines = array_filter($formattedTontines, function($t) {
            return isset($t['status']) && $t['status'] === 'active';
        });
        
        $completedTontines = array_filter($formattedTontines, function($t) {
            return isset($t['status']) && $t['status'] === 'completed';
        });
        
        $upcomingPayments = array_filter($formattedTontines, function($t) {
            if (empty($t['nextPayment'])) {
                return false;
            }
            try {
                $nextPayment = new \DateTime($t['nextPayment']);
                return $nextPayment <= (new \DateTime())->modify('+30 days');
            } catch (\Exception $e) {
                return false;
            }
        });

        // Get user data safely
        $userData = [
            'id' => method_exists($user, 'getId') ? $user->getId() : null,
            'email' => method_exists($user, 'getEmail') ? $user->getEmail() : '',
            'firstName' => method_exists($user, 'getFirstname') ? $user->getFirstname() : '',
            'lastName' => method_exists($user, 'getLastname') ? $user->getLastname() : '',
        ];
        
        // Combine first and last name for full name
        $fullName = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
        $userData['fullName'] = !empty($fullName) ? $fullName : 'Utilisateur';
        
        return $this->json([
            'user' => $userData,
            'tontines' => $formattedTontines,
            'stats' => [
                'totalBalance' => $totalBalance,
                'activeTontines' => count($activeTontines),
                'completedTontines' => count($completedTontines),
                'upcomingPayments' => count($upcomingPayments),
                'totalTontines' => count($formattedTontines)
            ]
        ]);
    }

    private function calculateProgress(Tontine $tontine): int
    {
        $totalPay = $tontine->getTotalPay() ?? 0;
        $amountPerPoint = $tontine->getAmountPerPoint() ?? 0;
        $totalPoints = $tontine->getTotalPoints() ?? 0;
        
        if ($amountPerPoint > 0 && $totalPoints > 0) {
            $totalAmount = $amountPerPoint * $totalPoints;
            $progress = min(100, (int) (($totalPay / $totalAmount) * 100));
        } else {
            $progress = 0;
        }
        
        return $progress;
    }
}
