<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
        $tontines= $this->entityManager->getRepository(Tontine::class)->findBy(['utilisateur' => $user]);
        // Données mock pour le développement
        $appData = [
            'user' => [
                'id' =>  $user->getId(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
          
        ];
        
        // Pass the data to the template
        return $this->render('dashboard/index.html.twig', [
            'user' => $appData['user'] ?? null,
          
            'appDataJson' => json_encode($appData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), // For JS
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
            return [
                'id' => $tontine->getId(),
                'name' => $tontine->getTontineCode() ?? 'Tontine #' . $tontine->getId(),
                'amount' => (float) $tontine->getAmountPerPoint() * (float) $tontine->getTotalPoints(),
                'period' => $tontine->getFrequency() ?? 'monthly',
                'startDate' => $tontine->getStartDate() ? $tontine->getStartDate()->format('Y-m-d') : null,
                'nextPayment' => $tontine->getNextDueDate() ? $tontine->getNextDueDate()->format('Y-m-d') : null,
                'progress' => $this->calculateProgress($tontine),
                'status' => $tontine->getStatut() ?? 'active',
                'totalPay'=>$tontine->getTotalPay(),
            ];
        }, $tontines);

        // Calculate statistics
        $totalBalance = array_sum(array_map(function($t) {
            return is_numeric($t['totalPay'] ?? null) ? (float)$t['totalPay'] : 0;
        }, $formattedTontines));
        
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
        $start = $tontine->getStartDate();
        $nextDue = $tontine->getNextDueDate();
        
        if (!$start || !$nextDue) {
            return 0;
        }

        $now = new \DateTime();
        $total = $nextDue->diff($start)->days;
        $elapsed = $now->diff($start)->days;
        
        if ($total <= 0) {
            return 100;
        }
        
        $progress = min(100, (int) (($elapsed / $total) * 100));
        return $progress;
    }
}
