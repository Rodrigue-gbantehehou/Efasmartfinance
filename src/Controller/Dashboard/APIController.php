<?php

namespace App\Controller\Dashboard;

use App\Entity\Tontine;
use App\Entity\Transaction;
use App\Repository\TontineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
#[Route('/dashboard')]
final class APIController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,

    ) {}

    #[Route(path: '/tontines_payment', name: 'app_tontines_payment', methods: ['GET'])]
    public function index(TontineRepository $tontineRepository): Response
    {
        return $this->render('dashboard/pages/tontines/tontines.html.twig', [
            'tontines' => $tontineRepository->findAll(),
        ]);
    }
    // Dans votre TontineController.php

    // Dans TontineController.php

    #[Route('/tontine/payment/{id}', name: 'app_tontine_payment')]
    public function paymentPage($id): Response
    {
        // Récupérer la tontine
        $tontine = $this->entityManager->getRepository(Tontine::class)->find($id);

        if (!$tontine) {
            throw $this->createNotFoundException('Tontine non trouvée');
        }

        // Calculer les jours restants
        $today = new \DateTime();
        $nextDueDate = $tontine->getNextDueDate();
        if ($nextDueDate === null) {
            $interval = $tontine->getStartDate()->diff($today);
        } else {
            $interval = $today->diff($nextDueDate) ;
        }
        $daysRemaining = $interval->days;

        // Calculer la progression
        $progress = 0;
        if ($tontine->getTotalPoints() > 0) {
            $progress = ($tontine->getAmountPerPoint() / $tontine->getTotalPoints()) * 100;
        }

        return $this->render('dashboard/pages/payements/payment.html.twig', [
            'tontine' => $tontine,
            'daysRemaining' => $daysRemaining,
            'progress' => round($progress, 1)
        ]);
    }



    // Dans TontineController.php

    #[Route('/tontine/cash-payment', name: 'app_tontine_cash_payment', methods: ['POST'])]
    public function processCashPayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation
        if (!isset($data['tontine_id']) || !isset($data['amount'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides']);
        }

        // Enregistrer le paiement en espèces
        $payment = new Transaction();
        $payment->setTontine($this->entityManager->getRepository(Tontine::class)->find($data['tontine_id']));
        $payment->setAmount($data['amount']);
        $payment->setPaymentMethod('cash');
        $payment->setStatut('pending');
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Paiement en espèces enregistré',
            'redirect_url' => $this->generateUrl('app_tontine_payment_confirmation', ['id' => $payment->getId()])
        ]);
    }

    #[Route('/tontine/save-payment', name: 'app_tontine_save_payment', methods: ['POST'])]
    public function savePayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Valider et enregistrer le paiement
        $payment = new Transaction();
        $payment->setTontine($this->entityManager->getRepository(Tontine::class)->find($data['tontine_id']));
        $payment->setAmount($data['amount']);
        $payment->setPaymentMethod($data['method']);
        $payment->setExternalReference($data['transaction_id'] ?? null);
        $payment->setStatut('completed');
        $payment->setCreatedAt(new \DateTimeImmutable());

        // Mettre à jour les points payés de la tontine
        $tontine = $payment->getTontine();
     //   $newPaidPoints = $tontine->getPaidPoints() + 1;
      //  $tontine->setPaidPoints($newPaidPoints);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Paiement enregistré avec succès',
            'redirect_url' => $this->generateUrl('app_tontine_payment_confirmation', ['id' => $payment->getId()])
        ]);
    }

    #[Route('/tontine/payment/confirmation/{id}', name: 'app_tontine_payment_confirmation')]
    public function paymentConfirmation($id): Response
    {
        $payment = $this->entityManager->getRepository(Transaction::class)->find($id);

        if (!$payment) {
            throw $this->createNotFoundException('Paiement non trouvé');
        }

        return $this->render('dashboard/payment_confirmation.html.twig', [
            'payment' => $payment
        ]);
    }
}
