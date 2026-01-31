<?php

// src/Service/Payment/KkiaPayService.php
namespace App\Service\Payment;

use App\Entity\Transaction;
use Kkiapay\Kkiapay;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class KkiaPayService
{
    private Kkiapay $kkiapay;
    private ?LoggerInterface $logger;
    private string $publicKey;
    private string $privateKey;
    private string $secretKey;
    private bool $sandbox;

    public function __construct(
        string $publicKey,
        string $privateKey,
        string $secretKey,
        bool $sandbox = true,
        ?LoggerInterface $logger = null
    ) {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->secretKey = $secretKey;
        $this->sandbox = $sandbox;
        
        $this->validateConfiguration();
        
        $this->kkiapay = new Kkiapay(
            $this->publicKey,
            $this->privateKey,
            $this->secretKey,
            $this->sandbox
        );
        $this->logger = $logger;
    }
    
    private function validateConfiguration(): void
    {
        $requiredKeys = ['publicKey', 'privateKey', 'secretKey'];
        
        foreach ($requiredKeys as $key) {
            $value = $this->$key;
            if (empty($value) || !is_string($value)) {
                throw new \RuntimeException(sprintf(
                    'La clé KkiaPay "%s" n\'est pas configurée correctement',
                    $key
                ));
            }
        }
    }
    
  public function initPayment(Transaction $payment, array $options = []): array
    {
        return [
            'public_key' => $this->publicKey,
            'amount' => $payment->getAmount(),
            'currency' => $options['currency'] ?? 'F CFA',
            'description' => $options['description'] ?? 'Paiement',
            'callback' => $options['callback'] ?? null,
            'data' => $options['data'] ?? []
        ];
    }
    
    public function verifyPayment(Transaction $payment, string $transactionId): array
    {
        return $this->verifyTransaction($transactionId);
    }
    
    public function verifyTransaction(string $transactionId): array
    {
        try {
            // Utilisation du SDK KkiaPay pour vérifier la transaction
            $transaction = $this->kkiapay->verifyTransaction($transactionId);
            
            // Conversion de l'objet en tableau si nécessaire
            $transactionData = is_object($transaction) ? json_decode(json_encode($transaction), true) : $transaction;
            
            // Journalisation
            if ($this->logger) {
                $this->logger->info('Réponse de vérification KkiaPay', [
                    'transactionId' => $transactionId,
                    'response' => $transactionData,
                    'status' => $transactionData['status'] ?? 'UNKNOWN'
                ]);
            }
            
            // Vérification du statut de la transaction
            $status = strtoupper($transactionData['status'] ?? 'PENDING');
            $isSuccess = in_array($status, ['SUCCESS', 'COMPLETED']);
            
            return [
                'success' => $isSuccess,
                'status' => $status,
                'data' => $transactionData,
                'message' => $isSuccess ? 'Paiement vérifié avec succès' : ($transactionData['message'] ?? 'En attente de confirmation du paiement'),
                'status_code' => 200
            ];

        } catch (\Exception $e) {
            $errorResponse = [
                'success' => false,
                'error' => 'verification_error',
                'message' => 'Erreur lors de la vérification du paiement',
                'details' => $e->getMessage(),
                'status_code' => 500
            ];

            if ($this->logger) {
                $this->logger->error('Erreur KkiaPay', [
                    'transactionId' => $transactionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            return $errorResponse;
        }
     }
    
    /**
     * Webhook signature verification
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->headers->get('X-KKIAPAY-SIGNATURE');
        $payload = $request->getContent();
        
        if (!$signature || !$payload) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        
        return hash_equals($expectedSignature, $signature);
    }
}