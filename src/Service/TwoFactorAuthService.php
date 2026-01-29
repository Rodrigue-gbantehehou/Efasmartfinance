<?php

namespace App\Service;

use App\Entity\User;
use Endroid\QrCode\QrCode;
use App\Entity\TwoFactorAuth;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\ErrorCorrectionLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class TwoFactorAuthService
{
    private string $appName;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->appName = 'EFA Smart Finance';
    }

    public function generateTotpSecret(): string
    {
        // Générer un secret Base32 de 16 caractères (32 bytes en binaire)
        $randomBytes = random_bytes(20); // 20 bytes = 160 bits (standard TOTP)
        return \ParagonIE\ConstantTime\Base32::encodeUpper($randomBytes);
    }

    public function enableTwoFactor(User $user): TwoFactorAuth
    {
        $twoFactorAuth = $user->getTwoFactorAuth();
        
        if (!$twoFactorAuth) {
            // Créer une nouvelle entité seulement si elle n'existe pas
            $twoFactorAuth = new TwoFactorAuth();
            $twoFactorAuth->setUser($user);
            $user->setTwoFactorAuth($twoFactorAuth);
            
            $twoFactorAuth->setIsEnabled(false);
            $twoFactorAuth->setMethod('email');

            $this->entityManager->persist($twoFactorAuth);
            $this->entityManager->flush();
        } else {
            $twoFactorAuth->setIsEnabled(false);
            $twoFactorAuth->setMethod('email');
            $this->entityManager->flush();
        }

        return $twoFactorAuth;
    }

    public function generateQrCode(User $user, string $totpSecret): Response
    {
        $issuer = $this->appName;
        $email = $user->getEmail();
        $uuid = $user->getUuid();
        
        // Utiliser l'email si disponible, sinon le UUID
        $identifier = $email ?: $uuid;
        
        // Nettoyer le secret des caractères invalides
        $cleanSecret = preg_replace('/[^A-Z2-7]/', '', $totpSecret);
        
        // Formatter correctement l'URL TOTP selon la spécification
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($identifier),
            $cleanSecret,
            rawurlencode($issuer)
        );
        
        error_log("QR Code Debug - URL: " . $qrCodeUrl);
        error_log("QR Code Debug - Clean Secret: " . $cleanSecret);
        error_log("QR Code Debug - Original Secret: " . $totpSecret);

        try {
            $qrCode = new QrCode(
            data: $qrCodeUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            
            $qrCodeData = $result->getString();
            
            return new Response($qrCodeData, 200, [
                'Content-Type' => $result->getMimeType(),
                'Cache-Control' => 'no-store, no-cache, must-revalidate'
            ]);
        } catch (\Exception $e) {
            error_log("QR Code Error: " . $e->getMessage());
            throw new \Exception('Erreur génération QR code: ' . $e->getMessage());
        }
    }

   public function verifyTotpCode(User $user, string $code): bool
{
    $twoFactorAuth = $user->getTwoFactorAuth();
    
    if (!$twoFactorAuth || !$twoFactorAuth->getTotpSecret()) {
        error_log("TOTP Verification - No TwoFactorAuth or secret found for user: " . $user->getId());
        return false;
    }

    $secret = $twoFactorAuth->getTotpSecret();
    $cleanSecret = preg_replace('/[^A-Z2-7]/', '', $secret);
    
    error_log("=== TOTP VERIFICATION START ===");
    error_log("User ID: " . $user->getId());
    error_log("User Email: " . $user->getEmail());
    error_log("User UUID: " . $user->getUuid());
    error_log("Original Secret: " . $secret);
    error_log("Clean Secret: " . $cleanSecret);
    error_log("Clean Secret Length: " . strlen($cleanSecret));
    error_log("Code to verify: " . $code);
    error_log("Current time: " . time());
    error_log("Time mod 30: " . (time() % 30));

    try {
        // Créer le TOTP avec les paramètres corrects
        $totp = \OTPHP\TOTP::create(
            $cleanSecret,
            30,          // période de 30 secondes
            'sha1',      // algorithme SHA1
            6,           // 6 digits
            0            // epoch (0 par défaut)
        );
        
        // Définir l'émetteur et le label
        $label = $user->getEmail() ?: $user->getUuid();
        $totp->setLabel($label);
        $totp->setIssuer($this->appName);
        
        error_log("TOTP Parameters:");
        error_log("  - Label: " . $label);
        error_log("  - Issuer: " . $this->appName);
        error_log("  - Period: 30");
        error_log("  - Algorithm: SHA1");
        error_log("  - Digits: 6");
        
        // Pour debug, générer le code actuel et les précédents/suivants
        $currentCode = $totp->now();
        error_log("Current code from TOTP lib: " . $currentCode);
        
        // Calculer les codes précédents et suivants pour voir si nous avons un problème de timing
        $previousCode = $totp->at(time() - 30);
        $nextCode = $totp->at(time() + 30);
        error_log("Previous code (30s ago): " . $previousCode);
        error_log("Next code (30s later): " . $nextCode);
        
        // Vérifier avec une fenêtre de tolérance plus large
        error_log("Verifying with tolerance 1...");
        $isValidWithTolerance1 = $totp->verify($code, null, 1);
        error_log("Result with tolerance 1: " . ($isValidWithTolerance1 ? 'VALID' : 'INVALID'));
        
        error_log("Verifying with tolerance 2...");
        $isValidWithTolerance2 = $totp->verify($code, null, 2);
        error_log("Result with tolerance 2: " . ($isValidWithTolerance2 ? 'VALID' : 'INVALID'));
        
        error_log("Verifying without tolerance...");
        $isValid = $totp->verify($code);
        error_log("Result without tolerance: " . ($isValid ? 'VALID' : 'INVALID'));
        
        // Vérifier aussi les codes précédents/suivants
        $isPrevious = ($code === $previousCode);
        $isNext = ($code === $nextCode);
        $isCurrent = ($code === $currentCode);
        
        error_log("Code matches current: " . ($isCurrent ? 'YES' : 'NO'));
        error_log("Code matches previous: " . ($isPrevious ? 'YES' : 'NO'));
        error_log("Code matches next: " . ($isNext ? 'YES' : 'NO'));
        
        // Utiliser une fenêtre plus large si nécessaire
        $finalResult = $isValidWithTolerance2;
        
        error_log("=== TOTP VERIFICATION END ===");
        error_log("Final result: " . ($finalResult ? 'VALID' : 'INVALID'));
        
        return $finalResult;
    } catch (\Exception $e) {
        error_log("TOTP Verification Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}


   public function confirmTwoFactor(User $user, string $code): bool
{
    error_log("=== 2FA CONFIRMATION START ===");
    error_log("User ID: " . $user->getId());
    error_log("User Email: " . $user->getEmail());
    error_log("Code provided: " . $code);
    
    $twoFactorAuth = $user->getTwoFactorAuth();
    if (!$twoFactorAuth) {
        error_log("2FA Confirmation - No TwoFactorAuth found");
        error_log("=== 2FA CONFIRMATION END ===");
        return false;
    }
    
    error_log("TwoFactorAuth exists, enabled: " . ($twoFactorAuth->isEnabled() ? 'true' : 'false'));
    error_log("TOTP Secret: " . $twoFactorAuth->getTotpSecret());
    
    // Si c'est l'e-mail, on vérifie contre le code stocké dans TwoFactorAuth
    if ($twoFactorAuth->getMethod() === 'email') {
        $isValid = ($code === $twoFactorAuth->getEmailCode());
    } else {
        $isValid = $this->verifyTotpCode($user, $code);
    }
    
    if (!$isValid) {
        error_log("2FA Confirmation - TOTP verification failed");
        error_log("=== 2FA CONFIRMATION END ===");
        return false;
    }

    error_log("2FA Confirmation - TOTP verification successful, enabling 2FA");
    
    $twoFactorAuth->setIsEnabled(true);
    $twoFactorAuth->setLastUsedAt(new \DateTimeImmutable());

    // Générer les codes de sauvegarde
    $backupCodes = $this->generateBackupCodes();
    $twoFactorAuth->setBackupCodesArray($backupCodes);
    
    error_log("2FA Confirmation - Generated " . count($backupCodes) . " backup codes");

    $this->entityManager->flush();
    
    error_log("2FA Confirmation - 2FA enabled successfully");
    error_log("=== 2FA CONFIRMATION END ===");
    return true;
}

    public function disableTwoFactor(User $user): void
    {
        $twoFactorAuth = $user->getTwoFactorAuth();
        
        if ($twoFactorAuth) {
            $this->entityManager->remove($twoFactorAuth);
            $this->entityManager->flush();
        }
    }

    public function verifyBackupCode(User $user, string $code): bool
    {
        $twoFactorAuth = $user->getTwoFactorAuth();
        
        if (!$twoFactorAuth || !$twoFactorAuth->hasBackupCodes()) {
            return false;
        }

        if ($twoFactorAuth->removeBackupCode($code)) {
            $twoFactorAuth->setLastUsedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = sprintf('%06d', random_int(0, 999999));
        }
        return $codes;
    }

    public function isTwoFactorEnabled(User $user): bool
    {
        return $user->isTwoFactorEnabled();
    }

    /**
     * Get TwoFactorAuth entity for user
     */
    public function getTwoFactorAuth(User $user): ?TwoFactorAuth
    {
        return $user->getTwoFactorAuth();
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(User $user): void
    {
        $twoFactorAuth = $user->getTwoFactorAuth();
        if ($twoFactorAuth) {
            $twoFactorAuth->setLastUsedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }
    
    /**
     * Test function to generate current TOTP code for debugging
     */
    public function getCurrentTotpCode(User $user): ?string
    {
        $twoFactorAuth = $user->getTwoFactorAuth();
        
        if (!$twoFactorAuth || !$twoFactorAuth->getTotpSecret()) {
            return null;
        }

        $secret = $twoFactorAuth->getTotpSecret();
        $cleanSecret = preg_replace('/[^A-Z2-7]/', '', $secret);
        
        try {
            $totp = \OTPHP\TOTP::create(
                $cleanSecret,
                30,
                'sha1',
                6
            );
            
            return $totp->now();
        } catch (\Exception $e) {
            error_log("Get current TOTP code error: " . $e->getMessage());
            return null;
        }
    }
}