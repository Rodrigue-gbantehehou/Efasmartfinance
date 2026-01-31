<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Tontine;
use App\Service\ActivityLogger;
use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Entity\PlatformFee;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/caisse')]
#[IsGranted('ROLE_CAISSIER')]
class CaisseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private TontineRepository $tontineRepository,
        private ActivityLogger $activityLogger,
        private \App\Service\PdfService $pdfService,
        private \App\Service\NumerotationFactureService $numerotationFactureService
    ) {}

    #[Route('/', name: 'admin_caisse_index')]
    public function index(): Response
    {
        return $this->render('admin/pages/caisse/index.html.twig');
    }

    #[Route('/search-users', name: 'admin_caisse_search_users', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->query->get('q');
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $users = $this->userRepository->createQueryBuilder('u')
            ->where('u.firstname LIKE :q OR u.lastname LIKE :q OR u.phoneNumber LIKE :q OR u.email LIKE :q')
            ->setParameter('q', '%'.$query.'%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $data = array_map(function(User $user) {
            return [
                'id' => $user->getId(),
                'text' => sprintf('%s %s (%s)', $user->getFirstname(), $user->getLastname(), $user->getPhoneNumber()),
                'fullName' => $user->getFirstname() . ' ' . $user->getLastname(),
                'phone' => $user->getPhoneNumber(),
            ];
        }, $users);

        return new JsonResponse($data);
    }

    #[Route('/user/{id}/tontines', name: 'admin_caisse_user_tontines')]
    public function userTontines(User $user): Response
    {
        $tontines = $this->tontineRepository->findBy(['utilisateur' => $user, 'statut' => 'active']);

        return $this->render('admin/pages/caisse/user_tontines.html.twig', [
            'user' => $user,
            'tontines' => $tontines,
        ]);
    }

    #[Route('/payment/{id}', name: 'admin_caisse_payment_form')]
    public function paymentForm(Tontine $tontine): Response
    {
        if ($tontine->getStatut() !== 'active') {
             $this->addFlash('error', 'Cette tontine n\'est plus active.');
             return $this->redirectToRoute('admin_caisse_index');
        }

        return $this->render('admin/pages/caisse/payment.html.twig', [
            'tontine' => $tontine,
            'user' => $tontine->getUtilisateur(),
        ]);
    }

    #[Route('/process-payment/{id}', name: 'admin_caisse_process_payment', methods: ['POST'])]
    public function processPayment(Request $request, Tontine $tontine): Response
    {
        $amount = (int) $request->request->get('amount');
        $baseAmount = $tontine->getAmountPerPoint();

        if ($amount <= 0) {
            $this->addFlash('error', 'Montant invalide.');
            return $this->redirectToRoute('admin_caisse_payment_form', ['id' => $tontine->getId()]);
        }

        if ($amount % $baseAmount !== 0) {
            $this->addFlash('error', sprintf('Le montant doit être un multiple de %d FCFA.', $baseAmount));
            return $this->redirectToRoute('admin_caisse_payment_form', ['id' => $tontine->getId()]);
        }

        $remainingAmount = ($tontine->getTotalPoints() * $baseAmount) - $tontine->getTotalPay();
        if ($amount > $remainingAmount) {
            $this->addFlash('error', sprintf('Le montant dépasse le reste à payer (%d FCFA).', $remainingAmount));
            return $this->redirectToRoute('admin_caisse_payment_form', ['id' => $tontine->getId()]);
        }

        $this->entityManager->beginTransaction();
        try {
            // 1. Créer la transaction
            $transaction = new Transaction();
            $transaction->setTontine($tontine);
            $transaction->setUtilisateur($tontine->getUtilisateur());
            $transaction->setAmount((string)$amount);
            $transaction->setType('contribution');
            $transaction->setPaymentMethod('cash'); // Important: Cash
            $transaction->setStatut('completed');
            $transaction->setCreatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($transaction);

            // 2. Appliquer le paiement à la tontine
            $tontine->applyPayment($amount, $transaction, 'cash');

            // 3. Générer le reçu PDF
            $pdfPath = $this->pdfService->generateInvoice(
                [
                    'transaction' => $transaction,
                    'user' => $tontine->getUtilisateur(),
                    'tontine' => $tontine,
                ],
                'emails/recu_caisse_pdf.html.twig',
                'recu-caisse-' . $transaction->getId()
            );

            // 4. Mettre à jour la transaction avec le chemin du reçu
            $transaction->setInvoicePath('factures/' . basename($pdfPath));

            $this->entityManager->flush();
            $this->entityManager->commit();

            // 5. Log l'activité
            $this->activityLogger->log(
                $this->getUser(),
                'CASH_PAYMENT',
                'Tontine',
                $tontine->getId(),
                sprintf('Encaissement physique de %d FCFA pour %s. Reçu: %s', $amount, $tontine->getUtilisateur()->getFullName(), basename($pdfPath))
            );

            $this->addFlash('success', 'Le paiement a été enregistré avec succès.');
            
            // On redirige vers une page de confirmation avec option d'impression
            return $this->render('admin/pages/caisse/success.html.twig', [
                'transaction' => $transaction,
                'user' => $tontine->getUtilisateur(),
                'tontine' => $tontine,
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('admin_caisse_payment_form', ['id' => $tontine->getId()]);
        }
    }

    #[Route('/fee/{id}', name: 'admin_caisse_fee_form')]
    public function feeForm(Tontine $tontine): Response
    {
        if ($tontine->isFraisPreleves()) {
            $this->addFlash('info', 'Les frais pour cette tontine sont déjà réglés.');
            return $this->redirectToRoute('admin_caisse_user_tontines', ['id' => $tontine->getUtilisateur()->getId()]);
        }

        // Calcul des frais (Reuse logic from TontineFeesController)
        $months = $tontine->getTotalPoints() ?? 1;
        $montantParPoint = $tontine->getAmountPerPoint(); 
        $feePerMonth = 0;

        if($tontine->getfrequency() == 'daily') {
            $feePerMonth = $montantParPoint * $months / 30;
            $months = $months / 30;
        } elseif($tontine->getfrequency() == 'weekly') {
            $feePerMonth = $montantParPoint * 4 / 30;
            $months = $months / 4;
        } elseif($tontine->getfrequency() == 'monthly') {
            $feePerMonth = $montantParPoint / 30;
        }
        
        $totalFee = (int) ($months * $feePerMonth);

        return $this->render('admin/pages/caisse/fee_payment.html.twig', [
            'tontine' => $tontine,
            'user' => $tontine->getUtilisateur(),
            'totalFee' => $totalFee
        ]);
    }

    #[Route('/fee/process/{id}', name: 'admin_caisse_process_fees', methods: ['POST'])]
    public function processFees(Request $request, Tontine $tontine): Response
    {
        if ($tontine->isFraisPreleves()) {
            $this->addFlash('error', 'Les frais sont déjà réglés.');
            return $this->redirectToRoute('admin_caisse_user_tontines', ['id' => $tontine->getUtilisateur()->getId()]);
        }

        $amount = (int) $request->request->get('amount');
        if ($amount <= 0) {
            $this->addFlash('error', 'Montant invalide.');
            return $this->redirectToRoute('admin_caisse_fee_form', ['id' => $tontine->getId()]);
        }

        $this->entityManager->beginTransaction();
        try {
            $user = $tontine->getUtilisateur();

            // 1. Créer la transaction pour les frais
            $transaction = new Transaction();
            $transaction->setTontine($tontine);
            $transaction->setUtilisateur($user);
            $transaction->setAmount((string)$amount);
            $transaction->setType('frais_service');
            $transaction->setPaymentMethod('cash');
            $transaction->setStatut('completed');
            $transaction->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($transaction);

            // 2. Créer la facture
            $facture = new \App\Entity\Facture();
            $facture->setNumero($this->numerotationFactureService->genererNumero());
            $facture->setDateEmission(new \DateTime());
            $facture->setMontantHT((string)$amount);
            $facture->setTva('0.00');
            $facture->setMontantTTC((string)$amount);
            $facture->setStatut('payee');
            $facture->setClient($user);
            $facture->setDescription('Paiement espèces des frais de service pour la tontine : ' . $tontine->getName());
            $this->entityManager->persist($facture);

            // 3. Créer l'enregistrement PlatformFee pour le dashboard admin
            $platformFee = new PlatformFee();
            $platformFee->setUser($user);
            $platformFee->setTontine($tontine);
            $platformFee->setAmount($amount);
            $platformFee->setType('service_fee');
            $platformFee->setStatus('collected');
            $platformFee->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($platformFee);

            // 4. Marquer la tontine
            $tontine->setFraisPreleves(true);

            // 4. Générer le PDF
            $pdfPath = $this->pdfService->generateInvoice(
                [
                    'payment' => $transaction,
                    'user' => $user,
                    'facture' => $facture,
                    'hasPdf' => true
                ],
                'emails/facture_frais_pdf.html.twig',
                'facture-frais-' . $facture->getNumero()
            );

            $facture->setFichier('factures/' . basename($pdfPath));
            $transaction->setInvoicePath('factures/' . basename($pdfPath));

            $this->entityManager->flush();
            $this->entityManager->commit();

            // 5. Log activity
            $this->activityLogger->log(
                $this->getUser(),
                'FEES_PAID_CASH',
                'Tontine',
                $tontine->getId(),
                sprintf('Encaissement espèces des frais de service (%d FCFA) pour %s', $amount, $user->getFullName())
            );

            $this->addFlash('success', 'Les frais ont été encaissés avec succès.');
            
            return $this->render('admin/pages/caisse/fee_success.html.twig', [
                'transaction' => $transaction,
                'facture' => $facture,
                'user' => $user,
                'tontine' => $tontine
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->addFlash('error', 'Erreur lors de l\'encaissement : ' . $e->getMessage());
            return $this->redirectToRoute('admin_caisse_fee_form', ['id' => $tontine->getId()]);
        }
    }

    #[Route('/receipt/{id}/download', name: 'admin_caisse_receipt_download')]
    public function downloadReceipt(Transaction $transaction): Response
    {
        if (!$transaction->getInvoicePath()) {
            $this->addFlash('error', 'Aucun reçu disponible pour cette transaction.');
            return $this->redirectToRoute('admin_caisse_index');
        }

        $pdfPath = $this->getParameter('kernel.project_dir') . '/var/' . $transaction->getInvoicePath();
        
        if (!file_exists($pdfPath)) {
            $this->addFlash('error', 'Le fichier du reçu est introuvable.');
            return $this->redirectToRoute('admin_caisse_index');
        }

        return $this->file($pdfPath, basename($pdfPath));
    }
}
