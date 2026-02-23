<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Tontine;
use App\Service\ActivityLogger;
use App\Repository\UserRepository;
use App\Repository\TontineRepository;
use App\Entity\Facture;
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
        private \App\Service\ActivityLogger $activityLogger,
        private \App\Service\PdfService $pdfService,
        private \App\Service\NumerotationFactureService $numerotationFactureService,
        private \App\Service\EmailService $emailService,
        private \App\Service\NotificationService $notificationService,
        private \Twig\Environment $twig
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
        if ($tontine->getStatut() !== 'active') {
            $this->addFlash('error', 'Cette tontine n\'est plus active. les paiements sont bloqués.');
            return $this->redirectToRoute('admin_caisse_index');
        }

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

            // 2. Créer la facture pour avoir un numéro officiel
            $facture = new Facture();
            $facture->setNumero($this->numerotationFactureService->genererNumero());
            $facture->setDateEmission(new \DateTime());
            $facture->setMontantHT((string)$amount);
            $facture->setTva('0.00');
            $facture->setMontantTTC((string)$amount);
            $facture->setStatut('payee');
            $facture->setClient($tontine->getUtilisateur());
            $facture->setDescription('Encaissement espèces - Cotisation tontine : ' . $tontine->getName());
            $this->entityManager->persist($facture);

            // 3. Appliquer le paiement à la tontine
            $tontine->applyPayment($amount, $transaction, 'cash');

            // 4. Générer le reçu PDF
            $pdfPath = $this->pdfService->generateInvoice(
                [
                    'transaction' => $transaction,
                    'user' => $tontine->getUtilisateur(),
                    'tontine' => $tontine,
                    'facture' => $facture,
                ],
                'emails/recu_caisse_pdf.html.twig',
                'recu-caisse-' . $facture->getNumero()
            );

            // 5. Mettre à jour la transaction et la facture avec les chemins
            $facture->setFichier('factures/' . basename($pdfPath));
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

            // 6. Envoyer les notifications
            try {
                $user = $tontine->getUtilisateur();
                
                // Notification interne
                $this->notificationService->sendPaymentNotification(
                    $user,
                    (float) $amount,
                    $tontine->getName()
                );

                // Email de confirmation avec PDF
                $emailContent = $this->twig->render('emails/facture.html.twig', [
                    'user' => $user,
                    'payment' => $transaction,
                    'facture' => $facture,
                    'hasPdf' => true
                ]);

                $this->emailService->sendWithAttachment(
                    $user->getEmail(),
                    'Confirmation de votre paiement Efasmartfinance - ' . $facture->getNumero(),
                    $emailContent,
                    $pdfPath,
                    'recu-' . $facture->getNumero() . '.pdf'
                );
            } catch (\Exception $e) {
                // On ne bloque pas si l'email échoue
            }

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
