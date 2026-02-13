<?php

namespace App\Controller;

use App\Entity\Tontine;
use App\Entity\ActivityLog;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
final class TontineController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}

    /**
     * Affiche la page principale des tontines
     */
   
    #[Route('/tontine', name: 'app_tontine')]
    public function index(EntityManagerInterface $em): Response
    {
        // Vérifier le nombre de tontines actives
        $nrbtontineactive = $em->getRepository(Tontine::class)->count(['statut' => 'active', 'utilisateur' => $this->getUser()]);
        if ($nrbtontineactive >= 3) {
            // Réponse message d'alerte
            $this->addFlash('error', 'Vous avez atteint le nombre maximum de tontines actives.');
            return $this->redirectToRoute('app_tontines_index');
        }
        return $this->render('dashboard/pages/tontines/create.html.twig', [
            'controller_name' => 'TontineController',
        ]);
    }
    #[Route('/api/tontine/create', name: 'app_tontine_create', methods: ['POST'])]
    public function createTontine(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Vérifier le nombre de tontines actives
        $nrbtontineactive = $entityManager->getRepository(Tontine::class)->count([
            'statut' => 'active', 
            'utilisateur' => $user
        ]);

        if ($nrbtontineactive >= 3) {
            error_log('Erreur : Nombre maximum de tontines actives atteint');
            return $this->json([
                'success' => false,
                'message' => 'Vous avez atteint le nombre maximum de tontines actives (3).'
            ], Response::HTTP_FORBIDDEN);
        }

        // Récupérer les données JSON de la requête
        $content = $request->getContent();
        $data = json_decode($content, true);

        // Journalisation pour le débogage
        error_log('Données reçues : ' . print_r($data, true));
        error_log('Contenu brut : ' . $content);

        if (!$data) {
            error_log('Erreur : Données JSON invalides');
            return $this->json([
                'success' => false,
                'message' => 'Données JSON invalides.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Champs obligatoires
        foreach (['name', 'amount', 'period', 'duration', 'startDate'] as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Le champ '$field' est obligatoire."
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Validation montant
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Montant invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Fréquences autorisées
        $allowedPeriods = ['daily', 'weekly', 'monthly'];

        if (!in_array($data['period'], $allowedPeriods, true)) {
            return $this->json([
                'success' => false,
                'message' => 'La fréquence sélectionnée est invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Date de début
        $startDate = \DateTime::createFromFormat('!Y-m-d', $data['startDate']);
        $today = new \DateTime('today');

        if (!$startDate || $startDate < $today) {
            return $this->json([
                'success' => false,
                'message' => 'Date de début invalide.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 🔢 Calcul du nombre total de versements
        $totalPoints = match ($data['period']) {
            'daily'   => $data['duration'] * 31,
            'weekly'  => $data['duration'] * 4,
            'monthly' => $data['duration'],
        };

        // 💰 Calcul de la commission selon la fréquence (identique à l'entité Tontine)
        $durationInMonths = (int) $data['duration'];
        $commission = match ($data['period']) {
            'daily'   => $durationInMonths * $data['amount'],
            'weekly'  => $durationInMonths * ($data['amount'] / 4),
            'monthly' => $durationInMonths * ($data['amount'] / 30),
            default   => 0,
        };
        $commission = round($commission);

        try {
            $tontine = new Tontine();
            $tontine->setTontineCode($this->generateTontineCode($entityManager));
            $tontine->setAmountPerPoint((int) $data['amount']); // Conversion en int car le champ est de type integer
            $tontine->setTotalPoints($totalPoints);
            $tontine->setFrequency($data['period']);
            $tontine->setStartDate($startDate);
            $tontine->setStatut('active');
            $tontine->setName($data['name']);
            $tontine->setCreatedAt(new \DateTimeImmutable());
            // Ne pas définir endedAt à la création
            // Ne pas définir retraitAt car cette méthode n'existe pas
            $tontine->setUtilisateur($user);
            
            // Journalisation pour le débogage
error_log('Création de la tontine : ' . print_r([
                'code' => $tontine->getTontineCode(),
                'amount' => $tontine->getAmountPerPoint(),
                'points' => $tontine->getTotalPoints(),
                'frequency' => $tontine->getFrequency(),
                'startDate' => $tontine->getStartDate() ? $tontine->getStartDate()->format('Y-m-d') : null,
                'user_identifier' => $user->getUserIdentifier()
            ], true));
            
            $entityManager->persist($tontine);
            $entityManager->flush();
            
            error_log('Tontine créée avec succès, ID : ' . $tontine->getId());
//log   
            $this->activityLogger->log(
                $user,
                'TONTINE_CREATED',
                'Tontine',
                $tontine->getId(),
                'Création d’une nouvelle tontine'
            );

            return $this->json([
                'success' => true,
                'tontineId' => $tontine->getId(),
                'totalPoints' => $totalPoints,
                'commission' => $commission
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur interne.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Compte le nombre de tontines actives de l'utilisateur
     */
    #[Route('/api/tontine/count', name: 'app_tontine_count', methods: ['GET'])]
    public function countActiveTontines(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $count = $em->getRepository(Tontine::class)->count([
            'statut' => 'active', 
            'utilisateur' => $this->getUser()
        ]);

        return $this->json([
            'count' => $count,
            'canCreate' => $count < 3
        ]);
    }

    private function generateTontineCode(EntityManagerInterface $em): int
    {
        // Générer un code des tontines unique
        do {
            $code = random_int(10000, 99999);
            $exists = $em->getRepository(Tontine::class)
                ->findOneBy(['tontineCode' => $code]);
        } while ($exists);

        return $code;
    }
}
