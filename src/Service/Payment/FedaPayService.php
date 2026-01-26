<?php

namespace App\Service\Payment;

use App\Entity\Transaction;
use FedaPay\FedaPay;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FedaPayService
{
    private string $publicKey;
    private string $privateKey;
    private string $webhookSecret;
    private UrlGeneratorInterface $urlGenerator;
    private string $environment;

    public function __construct(string $publicKey, string $privateKey, string $webhookSecret, UrlGeneratorInterface $urlGenerator, string $environment = 'sandbox')
    {
        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
        $this->webhookSecret = $webhookSecret;
        $this->urlGenerator = $urlGenerator;
        $this->environment = $environment;
    }

    // src/Service/Payment/FedaPayService.php - Suite
    public function initPayment(Transaction $payment, array $options = []): array
    {
        // Configuration avec callback URL
        $callbackUrl = $this->generateCallbackUrl($payment);

        return [
            'public_key' => $this->publicKey,
            'amount' => $payment->getAmount(),
            'currency' => 'F CFA',
            'description' => $options['description'] ?? 'Paiement tontine',
            'callback_url' => $callbackUrl,
            'cancel_url' => $this->generateCancelUrl($payment)
        ];
    }


    private function generateCallbackUrl(Transaction $payment): string
    {
        return $this->urlGenerator->generate('app_fedapay_callback', [
            'payment_id' => $payment->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
    public function verifyPayment(Transaction $payment, string $transactionId): array
{
    try {
        \FedaPay\FedaPay::setApiKey($this->privateKey);
        \FedaPay\FedaPay::setEnvironment($this->environment);
        
        $transaction = \FedaPay\Transaction::retrieve($transactionId);
        
        return [
            'success' => $transaction->status === 'approved',
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'data' => $transaction->toArray()
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

    private function generateCancelUrl(Transaction $payment): string
    {
        return $this->urlGenerator->generate('app_tontine_payment_cancel', [
            'method' => 'fedapay',
            'payment_id' => $payment->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function verifyCallback(string $transactionId): array
    {
        // Utiliser le SDK FedaPay pour vérifier
        \FedaPay\FedaPay::setApiKey($this->privateKey);
        \FedaPay\FedaPay::setEnvironment($this->environment);

        try {
            $transaction = \FedaPay\Transaction::retrieve($transactionId);

            return [
                'success' => true,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function handleWebhook(string $payload, array $headers): array
    {
        // Vérifier la signature du webhook
        $signature = '';

        foreach (['x-fedapay-signature', 'X-FedaPay-Signature', 'X-FEDAPAY-SIGNATURE'] as $key) {
            if (isset($headers[$key])) {
                $signature = is_array($headers[$key]) ? ($headers[$key][0] ?? '') : (string) $headers[$key];
                break;
            }
        }

        if (!$this->verifyWebhookSignature($payload, $signature)) {
            return ['success' => false, 'message' => 'Signature invalide'];
        }

        $data = json_decode($payload, true);
        $event = $data['event'] ?? '';
        $transactionId = $data['data']['id'] ?? '';

        if ($event === 'transaction.approved') {
            // Traiter la transaction approuvée
            return $this->processWebhookTransaction($transactionId);
        }

        return ['success' => true, 'message' => 'Webhook ignoré'];
    }

    /**
     * Process an approved transaction from webhook
     *
     * @param string $transactionId The FedaPay transaction ID
     * @return array Result of the operation
     */
    private function processWebhookTransaction(string $transactionId): array
    {
        try {
            // Verify the transaction with FedaPay
            $transaction = $this->verifyCallback($transactionId);

            if (!$transaction['success']) {
                return ['success' => false, 'message' => 'Transaction verification failed'];
            }

            // Here you would typically update your database, send confirmation emails, etc.
            // For example:
            // $this->paymentService->markAsPaid($transaction['data']);

            return ['success' => true, 'message' => 'Transaction processed successfully'];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing transaction: ' . $e->getMessage()
            ];
        }
    }

    // Ajouter cette méthode dans chaque service
    private function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // FedaPay
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);

        // PayPal - vérification différente
        // Utiliser le SDK PayPal pour vérifier
    }
  
}
