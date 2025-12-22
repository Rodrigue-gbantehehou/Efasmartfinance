<?php

namespace App\Controller;

use App\Entity\Tontine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TontineController extends AbstractController
{
    #[Route('/tontine', name: 'app_tontine')]
    public function index(): Response
    {
        return $this->render('tontine/index.html.twig', [
            'controller_name' => 'TontineController',
        ]);
    }
#[Route('/api/tontine/create', name: 'app_tontine_create', methods: ['POST'])]
public function createTontine(
    Request $request,
    EntityManagerInterface $entityManager
): JsonResponse {

    $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    $user = $this->getUser();

    $data = json_decode($request->getContent(), true);

    if (!$data) {
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
    $startDate = \DateTime::createFromFormat('Y-m-d', $data['startDate']);
    if (!$startDate || $startDate < new \DateTime('today')) {
        return $this->json([
            'success' => false,
            'message' => 'Date de début invalide.'
        ], Response::HTTP_BAD_REQUEST);
    }

    // 🔢 Calcul du nombre total de versements
    $totalPoints = match ($data['period']) {
        'daily'   => $data['duration'] * 30,
        'weekly'  => $data['duration'] * 4,
        'monthly' => $data['duration'],
    };

    // 💰 Commission 10 %
    $commission = ($totalPoints * $data['amount']) * 0.10;

    try {
        $tontine = new Tontine();
        $tontine->setTontineCode($this->generateTontineCode());
        $tontine->setAmountPerPoint((float) $data['amount']);
        $tontine->setTotalPoints($totalPoints);
        $tontine->setFrequency($data['period']);
        $tontine->setStartDate($startDate);
        $tontine->setStatut('active');
        $tontine->setCreatedAt(new \DateTimeImmutable());
        $tontine->setUtilisateur($user);

        $entityManager->persist($tontine);
        $entityManager->flush();

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


private function generateTontineCode(): string
{
    return bin2hex(random_bytes(5));
}

}
