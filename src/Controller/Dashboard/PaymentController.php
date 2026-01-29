<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Entity\Tontine;
use App\Entity\Facture;
use App\Entity\Wallets;
use App\Entity\PlatformFee;
use App\Entity\Transaction;
use App\Service\PdfService;
use App\Service\NumerotationFactureService;

use App\Entity\TontinePoint;
use Psr\Log\LoggerInterface;
use App\Service\EmailService;
use App\Service\ActivityLogger;
use App\Entity\WalletTransactions;
use App\Service\Payment\PayPalService;
use App\Service\Payment\FedaPayService;
use App\Service\Payment\KkiaPayService;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as TwigEnvironment;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[IsGranted('ROLE_USER')]
final class PaymentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private FedaPayService $fedaPayService,
        private KkiaPayService $kkiaPayService,
        private PayPalService $payPalService,
        private ActivityLogger $activityLogger,
        private EmailService $emailService,
        private TwigEnvironment $twig,
        private LoggerInterface $logger,
        private PdfService $pdfService,
        private NumerotationFactureService $numerotationFactureService
    ) {}

    #[Route('/init', name: 'app_tontine_payment_init', methods: ['POST'])]
    public function initPayment(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->security->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['success' => false, 'message' => 'JSON invalide'], 400);
        }

        $tontine = $this->em->getRepository(Tontine::class)->find($data['tontine_id'] ?? 0);
        $amount  = (int) ($data['amount'] ?? 0);
        $method  = $data['method'] ?? null;

        if (!$tontine || $amount <= 0 || !in_array($method, ['fedapay', 'kkiapay', 'paypal'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        // Vérifier si l'utilisateur est le créateur de la tontine
        $tontineOwner = $tontine->getUtilisateur();
        $currentUserId = $user->getUserIdentifier();
        $isMember = $tontineOwner && $tontineOwner->getUserIdentifier() === $currentUserId;

        // Logs de débogage
        error_log("Tontine Owner Email: " . ($tontineOwner ? $tontineOwner->getEmail() : 'null'));
        error_log("Current User Email: " . $currentUserId);
        error_log("Is Member: " . ($isMember ? 'true' : 'false'));

        if (!$isMember) {
            return $this->json([
                'success' => false,
                'message' => 'Accès refusé. Vous devez être le créateur de la tontine pour effectuer un paiement.',
                'debug' => [
                    'tontine_owner_email' => $tontineOwner ? $tontineOwner->getEmail() : null,
                    'current_user_email' => $currentUserId
                ]
            ], 403);
        }

        $payment = new Transaction();
        $payment->setTontine($tontine);
        $payment->setUtilisateur($user);

        $payment->setAmount($amount);
        $payment->setPaymentMethod($method);
        $payment->setStatut('pending');
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();

        try {
            $initData = match ($method) {
                'fedapay' => $this->fedaPayService->initPayment($payment),
                'kkiapay' => $this->kkiaPayService->initPayment($payment),
                'paypal'  => $this->payPalService->initPayment($payment),
            };

            return $this->json([
                'success' => true,
                'payment_id' => $payment->getId(),
                'method' => $method,
                ...$initData
            ]);
        } catch (\Throwable $e) {
            $payment->setStatut('failed');
            $this->em->flush();

            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Vérifie le paiement des frais de service via KkiaPay
     * Gère à la fois les requêtes POST (appel initial) et GET (callback de KkiaPay)
     */
    #[Route('/verify-fees', name: 'app_tontine_payment_verify_fees', methods: ['POST'])]
    public function verifyFeesPayment(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        // 1️⃣ Lire les données envoyées
        $data = json_decode($request->getContent(), true);

        if (
            !$data ||
            empty($data['transaction_id']) ||
            empty($data['tontine_id']) ||
            empty($data['user_id'])
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides'
            ], 400);
        }

        $transactionId = $data['transaction_id'];
        $tontineId     = $data['tontine_id'];
        $userId        = $data['user_id'];

        // 2️⃣ Récupérer les entités
        $tontine = $em->getRepository(Tontine::class)->find($tontineId);
        $user    = $em->getRepository(User::class)->find($userId);

        if (!$tontine || !$user) {
            return $this->json([
                'success' => false,
                'message' => 'Tontine ou utilisateur non trouvé'
            ], 404);
        }

        // 3️⃣ Vérifier si déjà payé
        if ($tontine->isFraisPreleves()) {
            return $this->json([
                'success' => true,
                'message' => 'Les frais ont déjà été payés',
                'already_paid' => true
            ]);
        }

        try {
            // 4️⃣ Vérification Kkiapay
            $verification = $this->kkiaPayService->verifyTransaction($transactionId);

            if (
                !$verification ||
                ($verification['status'] ?? null) !== 'SUCCESS'
            ) {
                return $this->json([
                    'success' => false,
                    'message' => 'Paiement non validé par Kkiapay'
                ], 400);
            }

            // 5️⃣ Récupération SÉCURISÉE du montant payé
            $amountPaid = 0;

            if (isset($verification['data']['amount'])) {
                $amountPaid = (float) $verification['data']['amount'];
            } elseif (isset($verification['amount'])) {
                $amountPaid = (float) $verification['amount'];
            }

            if ($amountPaid <= 0) {
                throw new \Exception('Montant payé invalide ou introuvable');
            }

            // 6️⃣ Créer la transaction
            $transaction = new Transaction();
            $transaction->setTontine($tontine);
            $transaction->setUtilisateur($user);
            $transaction->setAmount($amountPaid);
            $transaction->setType('frais_service');
            $transaction->setPaymentMethod('online');
            $transaction->setProvider('kkiapay');
            $transaction->setStatut('completed');
            $transaction->setExternalReference($transactionId);
            $transaction->setCreatedAt(new \DateTimeImmutable());

            $em->persist($transaction);

            // 7️⃣ Enregistrer dans PlatformFee
            $platformFee = new PlatformFee();
            $platformFee->setTontine($tontine);
            $platformFee->setUser($user);
            $platformFee->setAmount($amountPaid);
            $platformFee->setStatus('completed');
            $platformFee->setTransactionId($transactionId);
            $platformFee->setType('frais_service');
            $platformFee->setCreatedAt(new \DateTimeImmutable());

            $em->persist($platformFee);

            // 8️⃣ Mettre à jour la tontine
            $tontine->setFraisPreleves(true);
            $em->persist($tontine);

            $em->flush();

            // 9️⃣ Journalisation
            $this->activityLogger->log(
                $user,
                'FEES_PAID',
                'Tontine',
                $tontine->getId(),
                'Paiement des frais de service de ' . $amountPaid . ' FCFA'
            );

            return $this->json([
                'success' => true,
                'message' => 'Paiement des frais enregistré avec succès',
                'amount'  => $amountPaid
            ]);
        } catch (\Throwable $e) {

            $this->logger->error('Erreur paiement frais Kkiapay', [
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du paiement'
            ], 500);
        }
    }


    #[Route('/verify', name: 'app_tontine_payment_verify', methods: ['POST'])]
public function verifyPayment(Request $request, EntityManagerInterface $em): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $paymentId = $data['payment_id'] ?? 0;
    $transactionId = $data['transaction_id'] ?? '';

    $payment = $this->em->getRepository(Transaction::class)->find($paymentId);

    $user = $this->getUser();
    if (!$payment) {
        return new JsonResponse([
            'success' => false,
            'message' => 'Paiement non trouvé'
        ], 404);
    }

    // Vérifier si l'utilisateur est autorisé
    $currentUser = $this->security->getUser();
    if ($payment->getUtilisateur()->getId() !== $currentUser->getId()) {
        error_log("Accès refusé - Utilisateur ID: " . $currentUser->getId() . " n'est pas le propriétaire du paiement");
        return new JsonResponse([
            'success' => false,
            'message' => 'Non autorisé',
            'debug' => [
                'payment_user_id' => $payment->getUtilisateur()->getId(),
                'current_user_id' => $currentUser->getId()
            ]
        ], 403);
    }

    try {
        $verification = match ($payment->getPaymentMethod()) {
            'fedapay' => $this->fedaPayService->verifyPayment($payment, $transactionId),
            'kkiapay' => $this->kkiaPayService->verifyPayment($payment, $transactionId),
            'paypal' => $this->payPalService->verifyPayment($payment, $transactionId),
            default => throw new \Exception('Méthode non supportée')
        };

        if ($verification['success']) {
            // 1. Mettre à jour le statut du paiement d'abord
            $payment->setStatut('completed');
            $payment->setExternalReference($transactionId);
            $payment->setCreatedAt(new \DateTimeImmutable());
            
            // 2. Appliquer le paiement à la tontine
            $tontine = $payment->getTontine();
            
            // 3. Gestion du surplus et des points
            $amountPaid = $payment->getAmount();
            $amountPerPoint = $tontine->getAmountPerPoint();
            
            // Calcul du surplus
            $surplus = $amountPaid % $amountPerPoint;
            $validAmount = $amountPaid - $surplus;

            if ($validAmount <= 0) {
                throw new \Exception('Le montant payé est inférieur au montant de la tontine.');
            }

            // Gestion du surplus
            if ($surplus > 0) {
                $wallet = $this->em->getRepository(Wallets::class)->findOneBy([
                    'utilisateur' => $payment->getUtilisateur()
                ]);

                if (!$wallet) {
                    $wallet = new Wallets();
                    $wallet->setUtilisateur($payment->getUtilisateur());
                    $wallet->setBalance(0);
                }

                $wallet->setBalance($wallet->getBalance() + $surplus);
                $wallet->setUpdatedAt(new \DateTimeImmutable());

                $transactionWallet = new WalletTransactions();
                $transactionWallet->setTransactions($payment);
                $transactionWallet->setWallet($wallet);
                $transactionWallet->setAmount($surplus);
                $transactionWallet->setCreatedAt(new \DateTimeImmutable());
                $transactionWallet->setReason('Surplus de paiement tontine');

                $this->em->persist($transactionWallet);
            }

            // Appliquer le paiement à la tontine
            $tontine->applyPayment(
                $validAmount,
                $payment,
                $payment->getPaymentMethod()
            );

            // Journalisation
            $this->activityLogger->log(
                $user,
                'PAYMENT_SUCCESS',
                'Transaction',
                $payment->getId(),
                'Paiement de ' . $payment->getAmount() . ' FCFA effectué'
            );

            // Créer et enregistrer la facture avant de générer le PDF
            $facture = new Facture();
            $facture->setNumero($this->numerotationFactureService->genererNumero());
            $facture->setDateEmission(new \DateTime());
            $facture->setMontantHT($payment->getAmount() / 1.2); // Exemple avec 20% de TVA
            $facture->setTva(20.00);
            $facture->calculerMontantTTC();
            $facture->setStatut('payee');
            $facture->setClient($user);
            
            $em->persist($facture);
            $em->flush();
            
            // Préparer les données pour le PDF
            $pdfData = [
                'payment' => $payment,
                'user' => $user,
                'facture' => $facture,
                'hasPdf' => true
            ];
            
            // Générer le PDF avec les données de la facture
            $pdfPath = $this->pdfService->generateInvoice(
                $pdfData,
                'emails/facture_pdf.html.twig',
                'facture-' . $facture->getNumero()
            );
            
            // Mettre à jour la facture avec le chemin du PDF
            $facture->setFichier('factures/' . basename($pdfPath));
            $em->persist($facture);
            
            // Mettre à jour le paiement avec le chemin du PDF
            $payment->setInvoicePath('factures/' . basename($pdfPath));
            $em->persist($payment);
            $em->flush();
            
            try {
                // Générer le contenu de l'email
                $emailContent = $this->twig->render('emails/facture.html.twig', [
                    'user' => $user,
                    'payment' => $payment,
                    'facture' => $facture,
                    'hasPdf' => true
                ]);

                // Envoyer l'email avec la pièce jointe
                $this->emailService->sendWithAttachment(
                    $user->getEmail(),
                    'Votre facture Efasmartfinance ' . $facture->getNumero(),
                    $emailContent,
                    $pdfPath,
                    'facture-' . $facture->getNumero() . '.pdf'
                );
                
                $this->logger->info('Facture PDF générée, enregistrée et envoyée avec succès', [
                    'payment_id' => $payment->getId(),
                    'facture_id' => $facture->getId(),  
                    'pdf_path' => $pdfPath,
                    'user_email' => $user->getEmail()
                ]);
            } catch (\Exception $e) {
                // Journaliser l'erreur mais NE PAS interrompre le flux
                $this->logger->error('Erreur lors de la génération/envoi de la facture', [
                    'exception' => $e->getMessage(),
                    'payment_id' => $payment->getId(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Envoyer un email sans pièce jointe
                try {
                    $emailContent = $this->twig->render('emails/facture.html.twig', [
                        'user' => $payment->getUtilisateur(),
                        'payment' => $payment,
                        'hasPdf' => false
                    ]);
                    
                    $this->emailService->send(
                        $payment->getUtilisateur()->getEmail(),
                        'Confirmation de paiement #' . $payment->getId(),
                        $emailContent
                    );
                    
                    $this->logger->info('Email sans pièce jointe envoyé', [
                        'payment_id' => $payment->getId()
                    ]);
                } catch (\Exception $emailError) {
                    $this->logger->error('Échec de l\'envoi de l\'email', [
                        'exception' => $emailError->getMessage(),
                        'payment_id' => $payment->getId()
                    ]);
                }
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Paiement validé avec succès',
                'payment_id' => $payment->getId(),
                'invoice_sent' => true
            ]);
        } else {
            $payment->setStatut('failed');
            $em->flush();

            $this->activityLogger->log(
                $currentUser,
                'PAYMENT_FAILED',
                'Transaction',
                $payment->getId(),
                'Paiement de ' . $payment->getAmount() . ' FCFA échoué'
            );
            
            $payload = [
                'success' => false,
                'message' => $verification['message'] ?? 'Échec de la vérification'
            ];

            if ((bool) $this->getParameter('kernel.debug')) {
                $payload['debug'] = [
                    'provider_response' => $verification,
                ];
            }

            return new JsonResponse($payload);
        }
    } catch (\Exception $e) {
        $payment->setStatut('failed');
        $this->em->flush();

        $this->activityLogger->log(
            $currentUser,
            'PAYMENT_FAILED',
            'Transaction',
            $payment->getId(),
            'Paiement de ' . $payment->getAmount() . ' FCFA échoué'
        );

        return new JsonResponse([
            'success' => false,
            'message' => 'Erreur lors de la vérification: ' . $e->getMessage()
        ], 500);
    }
}

    #[Route('/success/{id}', name: 'app_tontine_payment_success')]
    public function paymentSuccess(Transaction $transaction): Response
    {
        // Vérifier que l'utilisateur est autorisé à voir cette transaction
        //$this->denyAccessUnlessGranted('VIEW', $transaction);

        // Afficher la page de succès
        return $this->render('dashboard/pages/payements/payment_success.html.twig', [
            'transaction' => $transaction,
            'tontine' => $transaction->getTontine()
        ]);
    }

    #[Route('/cancel', name: 'app_tontine_payment_cancel')]
    public function paymentCancel(Request $request): Response
    {
        return $this->redirectToRoute('app_tontine_payment_error_temp', [
            'method' => $request->query->get('method'),
            'payment_id' => $request->query->get('payment_id')
        ]);
    }
    // src/Controller/Payment/TontinePaymentController.php - Suite

    #[Route('/callback', name: 'app_tontine_payment_callback')]
    public function handleCallback(Request $request): Response
    {
        // LOG IMPORTANT pour debug
        error_log('=== FEDAPAY CALLBACK RECEIVED ===');
        error_log('GET Params: ' . json_encode($request->query->all()));
        error_log('POST Params: ' . json_encode($request->request->all()));
        error_log('Headers: ' . json_encode($request->headers->all()));


        $method = $request->query->get('method') ?? $request->request->get('method');
        $paymentId = $request->query->get('payment_id') ?? $request->request->get('payment_id');
        $status = $request->query->get('status') ?? $request->request->get('status');
        $transactionId = $request->query->get('transaction_id') ?? $request->request->get('transaction_id');

        if (!$method || !$paymentId) {
            return $this->render('payment/callback_error.html.twig', [
                'message' => 'Paramètres manquants'
            ]);
        }

        $payment = $this->em->getRepository(Transaction::class)->find($paymentId);

        if (!$payment) {
            return $this->render('payment/callback_error.html.twig', [
                'message' => 'Paiement non trouvé'
            ]);
        }

        try {
            // Traitement selon la méthode
            switch ($method) {
                case 'fedapay':
                    $result = $this->handleFedaPayCallback($payment, $request);
                    break;
                case 'kkiapay':
                    $result = $this->handleKkiaPayCallback($payment, $request);
                    break;
                case 'paypal':
                    $result = $this->handlePayPalCallback($payment, $request);
                    break;
                default:
                    throw new \Exception('Méthode de paiement non supportée');
            }

            if ($result['success']) {
                // Rediriger vers la page de succès
                return $this->redirectToRoute('app_tontine_payment_success', [
                    'id' => $payment->getId(),
                    'transaction_id' => $result['transaction_id']
                ]);
            } else {
                // Rediriger vers la page d'erreur
                return $this->redirectToRoute('app_tontine_payment_error', [
                    'id' => $payment->getId(),
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return $this->render('payment/callback_error.html.twig', [
                'message' => 'Erreur de traitement: ' . $e->getMessage()
            ]);
        }
    }

    private function handleFedaPayCallback(Transaction $payment, Request $request): array
    {
        // Récupérer les données de callback FedaPay
        $transactionId = $request->query->get('transaction_id');
        $status = $request->query->get('status');

        // Vérifier avec l'API FedaPay
        $verification = $this->fedaPayService->verifyCallback($transactionId);

        if ($verification['success'] && $verification['status'] === 'approved') {
            // Mettre à jour le paiement
            $payment->setStatut('completed');
            $payment->setExternalReference($transactionId);
            $payment->setCreatedAt(new \DateTimeImmutable());

            // Créditer la tontine
            $this->creditTontine($payment);

            $this->em->flush();

            return [
                'success' => true,
                'transaction_id' => $transactionId
            ];
        }

        $payment->setStatut('failed');
        $this->em->flush();

        return [
            'success' => false,
            'message' => 'Paiement échoué ou annulé'
        ];
    }

    private function handleKkiaPayCallback(Transaction $payment, Request $request): array
    {
        // KKiaPay envoie les données via POST
        $data = $request->request->all();

        if (!isset($data['status']) || !isset($data['transactionId'])) {
            $this->logger->error('Données de callback KkiaPay invalides', [
                'payment_id' => $payment->getId(),
                'data_received' => $data
            ]);

            return [
                'success' => false,
                'message' => 'Données de callback invalides',
                'code' => 'invalid_callback_data'
            ];
        }

        $transactionId = $data['transactionId'];

        try {
            $this->logger->info('Traitement du callback KkiaPay', [
                'payment_id' => $payment->getId(),
                'transaction_id' => $transactionId,
                'status' => $data['status']
            ]);

            // Vérifier la transaction même si le statut n'est pas SUCCESS
            // car le statut peut être mis à jour après le callback initial
            $verification = $this->kkiaPayService->verifyTransaction($transactionId);

            if ($verification['success']) {
                // Vérifier que le montant correspond
                $expectedAmount = $payment->getAmount();
                $paidAmount = $verification['data']['amount'] ?? null;

                if ($paidAmount !== null && (int)$paidAmount !== $expectedAmount) {
                    $this->logger->error('Montant du paiement incorrect', [
                        'payment_id' => $payment->getId(),
                        'expected_amount' => $expectedAmount,
                        'paid_amount' => $paidAmount,
                        'transaction_id' => $transactionId
                    ]);

                    $payment->setStatut('failed');
                    $this->em->flush();

                    return [
                        'success' => false,
                        'message' => 'Le montant du paiement ne correspond pas',
                        'code' => 'amount_mismatch',
                        'expected_amount' => $expectedAmount,
                        'paid_amount' => $paidAmount
                    ];
                }

                // Mettre à jour le paiement
                $payment->setStatut('completed');
                $payment->setExternalReference($transactionId);
                $payment->setCreatedAt(new \DateTimeImmutable());
                $payment->setMetadata(array_merge(
                    $payment->getMetadata() ?? [],
                    ['verification_data' => $verification['data']]
                ));

                // Créditer la tontine
                $this->creditTontine($payment);
                $this->em->flush();

                $this->logger->info('Paiement KkiaPay traité avec succès', [
                    'payment_id' => $payment->getId(),
                    'transaction_id' => $transactionId,
                    'amount' => $paidAmount
                ]);

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'amount' => $paidAmount
                ];
            } else {
                $errorMessage = $verification['message'] ?? 'Échec de la vérification du paiement';
                $this->logger->error('Échec de la vérification KkiaPay', [
                    'payment_id' => $payment->getId(),
                    'transaction_id' => $transactionId,
                    'error' => $errorMessage,
                    'verification_response' => $verification
                ]);

                $payment->setStatut('failed');
                $payment->setMetadata(array_merge(
                    $payment->getMetadata() ?? [],
                    ['error' => $errorMessage, 'verification_response' => $verification]
                ));
                $this->em->flush();

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'code' => 'verification_failed',
                    'transaction_id' => $transactionId
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors du traitement du callback KkiaPay', [
                'payment_id' => $payment->getId(),
                'transaction_id' => $transactionId ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $payment->setStatut('failed');
            $payment->setMetadata(array_merge(
                $payment->getMetadata() ?? [],
                ['error' => $e->getMessage()]
            ));
            $this->em->flush();

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors du traitement du paiement',
                'code' => 'processing_error',
                'transaction_id' => $transactionId ?? null
            ];
        }
    }

    private function handlePayPalCallback(Transaction $payment, Request $request): array
    {
        // PayPal utilise généralement des webhooks, mais pour les boutons JS:
        $orderId = $request->query->get('token') ?? $request->query->get('paymentId');

        if (!$orderId) {
            return [
                'success' => false,
                'message' => 'ID de commande manquant'
            ];
        }

        // Vérifier la commande PayPal
        $verification = $this->payPalService->verifyOrder($orderId);

        if ($verification['success'] && $verification['status'] === 'COMPLETED') {
            $payment->setStatut('completed');
            $payment->setExternalReference($orderId);
            $payment->setCreatedAt(new \DateTimeImmutable());

            $this->creditTontine($payment);
            $this->em->flush();

            return [
                'success' => true,
                'transaction_id' => $orderId
            ];
        }

        $payment->setStatut('failed');
        $this->em->flush();

        return [
            'success' => false,
            'message' => 'Paiement PayPal échoué'
        ];
    }

    private function creditTontine(Transaction $payment): void
    {
        $tontine = $payment->getTontine();
        $amount = $payment->getAmount();

        // Calculer le nombre de points
        $points = floor($amount / $tontine->getAmountPerPoint());

        // Ajouter les points au membre
        $member = $tontine->getUtilisateur()->filter(function ($member) use ($payment) {
            return $member->getId() === $payment->getUtilisateur()->getId();
        })->first();

        if ($member) {
            $currentPoints = $member->getPaidPoints() ?? 0;
            $member->setPaidPoints($currentPoints + $points);
            $member->setLastPaymentDate(new \DateTime());

            // Mettre à jour la progression globale de la tontine
            $this->updateTontineProgress($tontine);
        }
    }

    private function updateTontineProgress(Tontine $tontine): void
    {
        $totalPaidPoints = 0;
        $totalMembers = $tontine->getUtilisateur()->count();
        $pointsPerMember = $tontine->getTotalPoints();

        foreach ($tontine->getUtilisateur() as $member) {
            $totalPaidPoints += $member->getPaidPoints() ?? 0;
        }

        $totalPossiblePoints = $totalMembers * $pointsPerMember;
        $progress = $totalPossiblePoints > 0 ? ($totalPaidPoints / $totalPossiblePoints) * 100 : 0;
    }

    #[Route('/webhook/{method}', name: 'app_payment_webhook', methods: ['POST'])]
    public function handleWebhook(string $method, Request $request): JsonResponse
    {
        // Pour les webhooks (PayPal, FedaPay webhooks, etc.)
        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            $webhookService = match ($method) {
                'paypal' => $this->payPalService,
                'fedapay' => $this->fedaPayService,
                'kkiapay' => $this->kkiaPayService,
                default => throw new \Exception('Méthode non supportée')
            };

            $result = $webhookService->handleWebhook($payload, $headers);

            return new JsonResponse([
                'success' => $result['success'],
                'message' => $result['message'] ?? 'Webhook traité'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/payment/error-temp', name: 'app_tontine_payment_error_temp')]
    public function paymentErrorTemp(): Response
    {
        return $this->render('dashboard/pages/payements/error_temp.html.twig');
    }


    #[Route('/payment/process', name: 'app_payment_process')]
    public function processPaymentPage(Request $request): Response
    {
        $transactionId = $request->query->get('transaction_id');
        $status = $request->query->get('status');

        return $this->render('dashboard/pages/payements/process.html.twig', [
            'transaction_id' => $transactionId,
            'status' => $status
        ]);
    }
    #[Route('/callback/fedapay', name: 'app_fedapay_callback')]
    public function fedaPayCallback(Request $request): Response
    {
        // Log pour debug
        $logData = [
            'time' => date('Y-m-d H:i:s'),
            'ip' => $request->getClientIp(),
            'get_params' => $request->query->all(),
            'post_params' => $request->request->all(),
            'headers' => $request->headers->all()
        ];

        // Sauvegarder les logs
        file_put_contents('var/log/fedapay_callback.log', json_encode($logData, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND);

        // Récupérer les paramètres FedaPay
        $transactionId = $request->query->get('transaction_id');
        $status = $request->query->get('status');
        $reference = $request->query->get('reference');

        // Afficher une page intermédiaire avec des informations
        return $this->render('dashboard/pages/payements/callback_processing.html.twig', [
            'transaction_id' => $transactionId,
            'status' => $status,
            'reference' => $reference
        ]);
    }

    #[Route('/callback/process', name: 'app_callback_process', methods: ['POST'])]
    public function processCallback(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'message' => 'JSON invalide'
            ], 400);
        }

        $transactionId = $data['transaction_id'] ?? null;
        $paymentId = $data['payment_id'] ?? null;
        $method = $data['method'] ?? 'kkiapay'; // Par défaut à kkiapay pour la rétrocompatibilité

        if ((!$transactionId || !$paymentId) && $method !== 'kkiapay') {
            return $this->json([
                'success' => false,
                'message' => 'Données de requête incomplètes'
            ], 400);
        }

        try {
            $payment = $this->em->getRepository(Transaction::class)->find((int) $paymentId);
            if (!$payment) {
                return $this->json([
                    'success' => false,
                    'message' => 'Paiement introuvable'
                ], 404);
            }

            // Vérifier que l'utilisateur est autorisé
            $currentUser = $this->security->getUser();
            $paymentUser = $payment->getUtilisateur();

            if (!$currentUser || !$paymentUser) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès non autorisé - Utilisateur non trouvé',
                    'debug' => [
                        'has_current_user' => $currentUser ? 'yes' : 'no',
                        'has_payment_user' => $paymentUser ? 'yes' : 'no'
                    ]
                ], 403);
            }

            // Vérifier que l'utilisateur actuel est bien celui qui a effectué le paiement
            if ($paymentUser->getUserIdentifier() !== $currentUser->getUserIdentifier()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès non autorisé - Ce paiement ne vous appartient pas',
                    'debug' => [
                        'current_user' => $currentUser->getUserIdentifier(),
                        'payment_user' => $paymentUser->getUserIdentifier()
                    ]
                ], 403);
            }

            // Vérifier le paiement selon la méthode
            $verification = match ($method) {
                'fedapay' => $this->fedaPayService->verifyCallback($transactionId),
                'kkiapay' => $this->kkiaPayService->verifyTransaction($transactionId),
                default => ['success' => false, 'message' => 'Méthode de paiement non supportée']
            };

            if ($verification['success']) {
                //verifie sur c'est le premier payement reçu, si oui alors il devient le frais de la tontine
                if ($payment->getTontine()->getFrais() === null) {
                    $payment->getTontine()->setFrais($payment);
                }
                $payment->setStatut('completed');
                $payment->setExternalReference($transactionId);
                $payment->setCreatedAt(new \DateTimeImmutable());
                $this->em->flush();

                return $this->json([
                    'success' => true,
                    'message' => 'Paiement confirmé',
                    'redirect_url' => $this->generateUrl('app_tontine_payment_success', [
                        'id' => $payment->getId()
                    ])
                ]);
            }

            return $this->json([
                'success' => false,
                'message' => $verification['message'] ?? 'Échec de la vérification du paiement',
                'debug' => $verification
            ], 400);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/test-pdf/{id}', name: 'app_test_pdf')]
public function testPdf(Transaction $payment, Request $request,Facture $facture): Response
{
    try {
        $pdfPath = $this->pdfService->generateInvoice(
            [
                'payment' => $payment,
                'user' => $payment->getUtilisateur(),
                'tontine' => $payment->getTontine(),
                'facture' => $facture,
                'baseUrl' => $request->getSchemeAndHttpHost()
            ],
            'emails/facture_pdf.html.twig',
            'test-facture-' . $payment->getId()
        );
        
        return new Response('PDF généré: ' . $pdfPath);
    } catch (\Exception $e) {
        return new Response('Erreur: ' . $e->getMessage());
    }
}
}
