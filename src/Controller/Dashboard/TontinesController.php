<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Form\TontineType;
use App\Repository\TontineRepository;
use App\Service\PinAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard/tontines')]
final class TontinesController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route(name: 'app_tontines_index', methods: ['GET'])]
    public function index(TontineRepository $tontineRepository): Response
    {

        // Récupérer l'utilisateur connecté
        $user = $this->getUser();

        // Vérifier si l'utilisateur est connecté
        if (!$user) {
            // Rediriger vers la page de connexion si l'utilisateur n'est pas connecté
            return $this->redirectToRoute('app_login');
        }

        // Récupérer uniquement les tontines de l'utilisateur connecté, triées par statut (actives en premier)
        $userTontines = $tontineRepository->findBy(['utilisateur' => $user], ['statut' => 'ASC', 'id' => 'DESC']);

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
    public function show(Tontine $tontine, $id): Response
    {
        $tontine = $this->entityManager->getRepository(Tontine::class)->find($id);
        $startDate = $tontine->getStartDate();
        $endDate = null;

        if ($startDate) {
            $endDate = clone $startDate;
            $totalPoints = $tontine->getTotalPoints() ?? 0;
            $frequency = $tontine->getFrequency() ?? 'monthly';

            // Ensure we have a positive number of points
            $totalPoints = max(1, (int)$totalPoints);

            // Calculate the interval based on frequency
            $interval = null;
            switch ($frequency) {
                case 'daily':
                    $interval = new \DateInterval("P{$totalPoints}D");
                    break;
                case 'weekly':
                    $interval = new \DateInterval("P{$totalPoints}W");
                    break;
                case 'monthly':
                default:
                    $interval = new \DateInterval("P{$totalPoints}M");
            }

            if ($interval) {
                $endDate->add($interval);
            }
        }
        $uiState = match (true) {
            $tontine->getStatut() === 'active' => 'ACTIVE',
            $tontine->getStatut() === 'completed' || $tontine->getStatut() === 'terminated' => match (true) {
                $tontine->isFullyWithdrawn() => 'COMPLETED_FULL_WITHDRAWAL',
                $tontine->isPartiallyWithdrawn() => 'PARTIAL_WITHDRAWAL',
                default => 'COMPLETED_NO_WITHDRAWAL',
            },
            default => 'INACTIVE',
        };



        return $this->render('dashboard/pages/tontines/show.html.twig', [
            'tontine' => $tontine,
            'endDate' => $endDate,
            'uiState' => $uiState,
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

    #[Route('/{id}/terminate/request', name: 'app_tontine_terminate_request', methods: ['GET'])]
    public function terminateRequest(Tontine $tontine, Request $request): Response
    {
        $user = $this->getUser();
        if ($tontine->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException();
        }

        // Stocker l'intention en session
        $request->getSession()->set('pending_tontine_termination', $tontine->getId());
        $request->getSession()->set('_security.main.target_path', $this->generateUrl('app_tontine_terminate', ['id' => $tontine->getId()]));

        return $this->redirectToRoute('pin_verify');
    }

    #[Route('/{id}/terminate', name: 'app_tontine_terminate', methods: ['GET'])]
    public function terminate(Tontine $tontine, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $session = $request->getSession();

        // Vérifier la propriété
        if ($tontine->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier si le PIN a été validé (via le flag mis par PinVerificationController)
        if (!$session->get('pin_verified')) {
            return $this->redirectToRoute('app_tontine_terminate_request', ['id' => $tontine->getId()]);
        }

        // Vérifier si c'est bien la tontine attendue
        if ($session->get('pending_tontine_termination') !== $tontine->getId()) {
            $session->remove('pin_verified');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        // Nettoyer la session
        $session->remove('pending_tontine_termination');
        $session->remove('pin_verified');

        // Si elle est déjà terminée, on ne fait rien
        if ($tontine->getStatut() === 'completed' || $tontine->getStatut() === 'terminated') {
            $this->addFlash('warning', 'Cette tontine est déjà clôturée.');
            return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
        }

        // Marquer la tontine comme terminée ou la supprimer si aucun versement
        if ($tontine->getTotalPay() === 0) {
            $entityManager->remove($tontine);
            $entityManager->flush();
            $this->addFlash('success', 'La tontine a été supprimée car aucun versement n\'avait été effectué.');
            return $this->redirectToRoute('app_tontines_index');
        }

        $tontine->setStatut('terminated');
        $tontine->setEndedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'La tontine a été clôturée avec succès. Vous pouvez maintenant retirer votre solde disponible.');

        return $this->redirectToRoute('app_tontines_show', ['id' => $tontine->getId()]);
    }
}
