<?php

namespace App\Controller\Dashboard;

use App\Entity\User;
use App\Entity\Tontine;
use App\Entity\Facture;

use App\Entity\PlatformFee;
use App\Entity\Transaction;
use App\Service\PdfService;
use App\Service\NumerotationFactureService;

use App\Entity\TontinePoint;
use Psr\Log\LoggerInterface;
use App\Service\EmailService;
use App\Service\ActivityLogger;

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
        private KkiaPayService $kkiaPayService,
        private ActivityLogger $activityLogger,
        private EmailService $emailService,
        private \App\Service\NotificationService $notificationService,
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

        if (!$tontine || $amount <= 0 || $method !== 'kkiapay') {
            return $this->json(['success' => false, 'message' => 'Données invalides ou méthode non supportée'], 400);
        }
        
        $type = $data['type'] ?? 'tontine'; // 'tontine' or 'fee'
        $includeFees = $data['include_fees'] ?? false;
        $feeAmount = (int) ($data['fee_amount'] ?? 0);

        // Bloquer le paiement des frais pour les tontines journalières
        if (($type === 'fee' || $includeFees) && $tontine->getFrequency() === 'daily') {
             return $this->json(['success' => false, 'message' => 'Le paiement cumulatif des frais n\'est pas disponible pour les tontines journalières.'], 400);
        }

        if ($tontine->getStatut() !== 'active' && $type !== 'fee') {
            return $this->json(['success' => false, 'message' => 'Cette tontine n\'est plus active. les paiements sont bloqués.'], 403);
        }

        // Vérifier si l'utilisateur est le créateur de la tontine
        $tontineOwner = $tontine->getUtilisateur();
        $currentUserId = $user->getUserIdentifier();
        $isMember = $tontineOwner && $tontineOwner->getUserIdentifier() === $currentUserId;



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
        $payment->setType($type);
        if ($includeFees && $feeAmount > 0) {
            $payment->setMetadata(['include_fees' => true, 'fee_amount' => $feeAmount]);
        }
        $payment->setStatut('pending');
        $payment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($payment);
        $this->em->flush();

        try {
            $initData = $this->kkiaPayService->initPayment($payment);

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


    #[Route('/verify', name: 'app_tontine_payment_verify', methods: ['POST'])]
public function verifyPayment(Request $request, EntityManagerInterface $em): JsonResponse
{
    $this->logger->info('=== DÉBUT VÉRIFICATION PAIEMENT ===');
    
    $data = json_decode($request->getContent(), true);
    $paymentId = $data['payment_id'] ?? 0;
    $transactionId = $data['transaction_id'] ?? '';

    $this->logger->info('Données reçues', [
        'payment_id' => $paymentId,
        'transaction_id' => $transactionId,
        'raw_data' => $data
    ]);

    $payment = $this->em->getRepository(Transaction::class)->find($paymentId);

    $user = $this->getUser();
    if (!$payment) {
        $this->logger->error('Paiement non trouvé', ['payment_id' => $paymentId]);
        return new JsonResponse([
            'success' => false,
            'message' => 'Paiement non trouvé'
        ], 404);
    }

    $this->logger->info('Paiement trouvé', [
        'payment_id' => $payment->getId(),
        'current_status' => $payment->getStatut(),
        'amount' => $payment->getAmount(),
        'payment_method' => $payment->getPaymentMethod()
    ]);

    // Vérifier si l'utilisateur est autorisé
    $currentUser = $this->security->getUser();
    if ($payment->getUtilisateur()->getId() !== $currentUser->getId()) {
        $this->logger->error("Accès refusé - Utilisateur non autorisé", [
            'payment_user_id' => $payment->getUtilisateur()->getId(),
            'current_user_id' => $currentUser->getId()
        ]);
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
        if ($payment->getPaymentMethod() !== 'kkiapay') {
            throw new \Exception('Méthode de paiement non supportée');
        }
        
        $this->logger->info('Appel KkiaPayService pour vérification', [
            'transaction_id' => $transactionId
        ]);
        
        $verification = $this->kkiaPayService->verifyPayment($payment, $transactionId);

        $this->logger->info('Résultat de la vérification KkiaPay', [
            'verification' => $verification
        ]);

        if ($verification['success']) {
            $this->logger->info('Paiement vérifié avec succès, mise à jour du statut');
            
            // 1. Mettre à jour le statut du paiement d'abord
            $payment->setStatut('completed');
            $payment->setExternalReference($transactionId);
            $payment->setCreatedAt(new \DateTimeImmutable());
            
            // 2. Appliquer le paiement à la tontine
            $tontine = $payment->getTontine();
            
            // Appliquer le paiement à la tontine ou aux frais
            if ($payment->getType() === 'fee') {
                $this->logger->info('Paiement de frais détecté', [
                    'tontine_id' => $tontine->getId(),
                    'amount' => $payment->getAmount()
                ]);
                $tontine->setPaidFees($tontine->getPaidFees() + $payment->getAmount());
                $tontine->setPaidFeesExternally($tontine->getPaidFeesExternally() + $payment->getAmount());
                $this->em->persist($tontine);

                // Create PlatformFee record
                $platformFee = new PlatformFee();
                $platformFee->setUser($user);
                $platformFee->setTontine($tontine);
                $platformFee->setAmount((string)$payment->getAmount());
                $platformFee->setType('service_fee');
                $platformFee->setStatus('collected');
                $platformFee->setTransactionId($transactionId);
                $this->em->persist($platformFee);

            } else {
                $paymentAmount = $payment->getAmount();
                $metadata = $payment->getMetadata();

                // Vérifier si le paiement inclut des frais
                if (isset($metadata['include_fees']) && $metadata['include_fees'] === true && isset($metadata['fee_amount'])) {
                     $feeAmount = (int) $metadata['fee_amount'];
                     $this->logger->info('Paiement combiné détecté (Tontine + Frais)', [
                        'total_amount' => $paymentAmount,
                        'fee_amount' => $feeAmount,
                        'tontine_amount' => $paymentAmount - $feeAmount
                     ]);

                     // 1. Payer les frais
                     $tontine->setPaidFees($tontine->getPaidFees() + $feeAmount);
                     $tontine->setPaidFeesExternally($tontine->getPaidFeesExternally() + $feeAmount);
                     
                     // Create PlatformFee record for the fee portion
                     $platformFee = new PlatformFee();
                     $platformFee->setUser($user);
                     $platformFee->setTontine($tontine);
                     $platformFee->setAmount((string)$feeAmount);
                     $platformFee->setType('service_fee');
                     $platformFee->setStatus('collected');
                     $platformFee->setTransactionId($transactionId);
                     $this->em->persist($platformFee);

                     // 2. Ajuster le montant restant pour la tontine
                     $paymentAmount -= $feeAmount;
                     $this->em->persist($tontine);
                }

                $this->logger->info('Appliquer le paiement à la tontine', [
                     'tontine_id' => $tontine->getId(),
                     'amount' => $paymentAmount
                 ]);
                
                 if ($paymentAmount > 0) {
                    $tontine->applyPayment(
                        $paymentAmount,
                        $payment,
                        $payment->getPaymentMethod()
                    );
                 }
            }

            $this->logger->info('Paiement appliqué à la tontine avec succès');

            // Journalisation
            $this->activityLogger->log(
                $user,
                'PAYMENT_SUCCESS',
                'Transaction',
                $payment->getId(),
                'Paiement de ' . $payment->getAmount() . ' FCFA effectué'
            );

            // Notification in-app
            $this->notificationService->sendPaymentNotification(
                $user,
                (float) $payment->getAmount(),
                $tontine->getName()
            );

            $this->logger->info('Génération de la facture');

            // Créer et enregistrer la facture avant de générer le PDF
            $facture = new Facture();
            $facture->setNumero($this->numerotationFactureService->genererNumero());
            $facture->setDateEmission(new \DateTime());
            $facture->setMontantHT((string)$payment->getAmount());
            $facture->setTva('0.00');
            $facture->setMontantTTC((string)$payment->getAmount());
            $facture->setStatut('payee');
            $facture->setClient($user);
            
            $em->persist($facture);
            $em->flush();
            
            $this->logger->info('Facture créée', ['facture_id' => $facture->getId()]);
            
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
            
            $this->logger->info('PDF généré', ['pdf_path' => $pdfPath]);
            
            // Mettre à jour la facture avec le chemin du PDF
            $facture->setFichier('factures/' . basename($pdfPath));
            $em->persist($facture);
            
            // Mettre à jour le paiement avec le chemin du PDF
            $payment->setInvoicePath('factures/' . basename($pdfPath));
            $em->persist($payment);
            $em->flush();
            
            $this->logger->info('Facture et paiement mis à jour avec le PDF');
            
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

            $this->logger->info('=== FIN VÉRIFICATION PAIEMENT - SUCCÈS ===');

            return new JsonResponse([
                'success' => true,
                'message' => 'Paiement validé avec succès',
                'payment_id' => $payment->getId(),
                'invoice_sent' => true
            ]);
        } else {
            $this->logger->warning('Échec de la vérification du paiement', [
                'verification_message' => $verification['message'] ?? 'Aucun message',
                'verification_data' => $verification
            ]);
            
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

            $this->logger->info('=== FIN VÉRIFICATION PAIEMENT - ÉCHEC ===');

            return new JsonResponse($payload);
        }
    } catch (\Exception $e) {
        $this->logger->error('Exception lors de la vérification du paiement', [
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'payment_id' => $paymentId
        ]);
        
        $payment->setStatut('failed');
        $this->em->flush();

        $this->activityLogger->log(
            $currentUser,
            'PAYMENT_FAILED',
            'Transaction',
            $payment->getId(),
            'Paiement de ' . $payment->getAmount() . ' FCFA échoué'
        );

        $this->logger->info('=== FIN VÉRIFICATION PAIEMENT - EXCEPTION ===');

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
            if ($method !== 'kkiapay') {
                throw new \Exception('Méthode de paiement non supportée');
            }
            $result = $this->handleKkiaPayCallback($payment, $request);

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

                // Notification in-app
                $this->notificationService->sendPaymentNotification(
                    $payment->getUtilisateur(),
                    (float) $payment->getAmount(),
                    $payment->getTontine()->getName()
                );

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



    // Supprimé: creditTontine et updateTontineProgress sont gérés par Tontine::applyPayment

    #[Route('/webhook/{method}', name: 'app_payment_webhook', methods: ['POST'])]
    public function handleWebhook(string $method, Request $request): JsonResponse
    {
        // Pour les webhooks KkiaPay uniquement pour le moment
        $payload = $request->getContent();
        $headers = $request->headers->all();

        try {
            if ($method !== 'kkiapay') {
                throw new \Exception('Méthode non supportée');
            }

            $result = $this->kkiaPayService->handleWebhook($payload, $headers);

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

                // Notification in-app
                $this->notificationService->sendPaymentNotification(
                    $payment->getUtilisateur(),
                    (float) $payment->getAmount(),
                    $payment->getTontine()->getName()
                );

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

    #[Route('/transaction/{id}/download-invoice', name: 'app_transaction_invoice_download')]
    public function downloadInvoice(Transaction $transaction): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        // Vérifier que le paiement appartient à l'utilisateur
        if ($transaction->getUtilisateur()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à télécharger cette facture.');
        }

        $invoicePath = $transaction->getInvoicePath();
        if (!$invoicePath) {
            throw $this->createNotFoundException('Aucune facture n\'est associée à cette transaction.');
        }

        $projectDir = $this->getParameter('kernel.project_dir');
        $filePath = $projectDir . '/var/' . $invoicePath;

        if (!file_exists($filePath)) {
            $this->logger->error('Fichier de facture introuvable', [
                'path' => $filePath,
                'transaction_id' => $transaction->getId()
            ]);
            throw $this->createNotFoundException('Le fichier de facture est introuvable sur le serveur.');
        }

        return $this->file($filePath, sprintf('facture-%s.pdf', $transaction->getExternalReference() ?? $transaction->getId()));
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
