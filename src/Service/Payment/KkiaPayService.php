<?php

// src/Service/Payment/KkiaPayService.php
namespace App\Service\Payment;

use App\Entity\Transaction;
use Kkiapay\Kkiapay;
use Psr\Log\LoggerInterface;

class KkiaPayService
{
    private Kkiapay $kkiapay;
    private ?LoggerInterface $logger;
    private string $publicKey;
    private string $privateKey;

    public function __construct(
        string $publicKey,
        string $privateKey,
        string $secretKey,
        ?LoggerInterface $logger = null,
        bool $sandbox = true
    ) {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->kkiapay = new Kkiapay($publicKey, $privateKey, $secretKey, $sandbox);
        $this->logger = $logger;
    }
    
    public function initPayment(Transaction $payment, array $options = []): array
    {
        return [
            'public_key' => $this->publicKey,
            'amount' => $payment->getAmount(),
            'currency' => $options['currency'] ?? 'XOF',
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
 }