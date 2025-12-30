<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Form\TontineType;
use App\Repository\TontineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/Dashboard/Tontines')]
final class TontinesController extends AbstractController
{
    #[Route(name: 'app_tontines_index', methods: ['GET'])]
    public function index(TontineRepository $tontineRepository): Response
    {

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
            return $this->redirectToRoute('app_login');
        } // Récupérer uniquement les tontines de l'utilisateur connecté
        $userTontines = $tontineRepository->findBy(['utilisateur' => $user->getId()]);

        return $this->render('dashboard/pages/tontines/tontines.html.twig', [
            'tontines' => $userTontines,
        ]);
     }

    #[Route('/new', name: 'app_tontines_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $tontine = new Tontine();
        $form = $this->createForm(TontineType::class, $tontine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($tontine);
            $entityManager->flush();

            return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/tontines/new.html.twig', [
            'tontine' => $tontine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tontines_show', methods: ['GET'])]
    public function show(Tontine $tontine): Response
    {
        return $this->render('dashboard/pages/tontines/show.html.twig', [
            'tontine' => $tontine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tontines_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TontineType::class, $tontine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dashboard/pages/tontines/edit.html.twig', [
            'tontine' => $tontine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tontines_delete', methods: ['POST'])]
    public function delete(Request $request, Tontine $tontine, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tontine->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($tontine);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_tontines_index', [], Response::HTTP_SEE_OTHER);
    }

    // Dans votre TontineController.php

    #[Route('/tontine/cash-payment', name: 'app_tontine_cash_payment')]
    public function processCashPayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Valider les données
        if (!isset($data['tontine_id']) || !isset($data['amount'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides']);
        }

        // Enregistrer le paiement en espèces
        // ... votre logique métier ici ...

        return $this->json([
            'success' => true,
            'message' => 'Paiement en espèces enregistré',
            'redirect_url' => $this->generateUrl('app_tontines_show', ['id' => $data['tontine_id']])
        ]);
    }

    #[Route('/tontine/save-payment', name: 'app_tontine_save_payment')]
    public function savePayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Valider et enregistrer le paiement
        // ... votre logique métier ici ...

        return $this->json([
            'success' => true,
            'message' => 'Paiement enregistré avec succès',
            'redirect_url' => $this->generateUrl('app_tontines_show', ['id' => $data['tontine_id']])
        ]);
    }
}
