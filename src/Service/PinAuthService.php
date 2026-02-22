<?php

namespace App\Service;

use App\Entity\PinAuth;
use App\Entity\User;
use App\Repository\PinAuthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PinAuthService
{
    private const PIN_LENGTH = 5;
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION_MINUTES = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PinAuthRepository $pinAuthRepository,
        private LoggerInterface $logger,
        private EmailService $emailService
    ) {
    }

    /**
     * Create a new PIN for a user
     */
    public function createPin(User $user, string $pin): PinAuth
    {
        // Validate PIN format
        if (!$this->isValidPinFormat($pin)) {
            throw new \InvalidArgumentException('Le code PIN doit contenir exactement 5 chiffres');
        }

        // Check if user already has a PIN
        $existingPin = $user->getPinAuth();
        if ($existingPin) {
            throw new \RuntimeException('L\'utilisateur a déjà un code PIN. Utilisez changePin() pour le modifier.');
        }

        // Create new PinAuth
        $pinAuth = new PinAuth();
        $pinAuth->setUser($user);
        $pinAuth->setPinHash($this->hashPin($pin));
        $pinAuth->setIsEnabled(true);
        $pinAuth->setMustChangePin(false);

        $user->setPinAuth($pinAuth);

        $this->entityManager->persist($pinAuth);
        $this->entityManager->flush();

        $this->logger->info('PIN créé pour l\'utilisateur', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $pinAuth;
    }

    /**
     * Verify a PIN for a user
     */
    public function verifyPin(User $user, string $pin): bool
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth || !$pinAuth->isEnabled()) {
            $this->logger->warning('Tentative de vérification PIN pour utilisateur sans PIN', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        // Check if account is locked
        if ($this->isAccountLocked($user)) {
            $this->logger->warning('Tentative de connexion sur compte verrouillé', [
                'user_id' => $user->getId(),
                'locked_until' => $pinAuth->getLockedUntil()?->format('Y-m-d H:i:s')
            ]);
            throw new \RuntimeException('Votre compte est temporairement verrouillé. Veuillez réessayer plus tard.');
        }

        // Verify PIN
        if (!$this->verifyPinHash($pin, $pinAuth->getPinHash())) {
            $this->incrementFailedAttempts($user);
            
            $this->logger->warning('Échec de vérification du PIN', [
                'user_id' => $user->getId(),
                'failed_attempts' => $pinAuth->getFailedAttempts()
            ]);

            return false;
        }

        // PIN is correct - reset failed attempts and update last used
        $this->resetFailedAttempts($user);
        $pinAuth->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('PIN vérifié avec succès', [
            'user_id' => $user->getId()
        ]);

        return true;
    }

    /**
     * Change PIN for a user
     */
    public function changePin(User $user, string $oldPin, string $newPin): bool
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            throw new \RuntimeException('L\'utilisateur n\'a pas de code PIN configuré');
        }

        // Verify old PIN
        if (!$this->verifyPinHash($oldPin, $pinAuth->getPinHash())) {
            $this->logger->warning('Échec de changement de PIN - ancien PIN incorrect', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        // Validate new PIN format
        if (!$this->isValidPinFormat($newPin)) {
            throw new \InvalidArgumentException('Le nouveau code PIN doit contenir exactement 5 chiffres');
        }

        // Check that new PIN is different from old PIN
        if ($oldPin === $newPin) {
            throw new \InvalidArgumentException('Le nouveau code PIN doit être différent de l\'ancien');
        }

        // Update PIN
        $pinAuth->setPinHash($this->hashPin($newPin));
        $pinAuth->setMustChangePin(false);
        $this->entityManager->flush();

        $this->logger->info('PIN modifié avec succès', [
            'user_id' => $user->getId()
        ]);

        return true;
    }


    /**
     * Create a temporary PIN for existing users (migration)
     */
    public function createTemporaryPin(User $user): string
    {
        // Check if user already has a PIN
        if ($user->getPinAuth()) {
            throw new \RuntimeException('L\'utilisateur a déjà un code PIN');
        }

        // Generate temporary PIN
        $temporaryPin = $this->generateRandomPin();

        // Create PinAuth with temporary PIN
        $pinAuth = new PinAuth();
        $pinAuth->setUser($user);
        $pinAuth->setPinHash($this->hashPin($temporaryPin));
        $pinAuth->setIsEnabled(true);
        $pinAuth->setMustChangePin(true);

        $user->setPinAuth($pinAuth);

        $this->entityManager->persist($pinAuth);
        $this->entityManager->flush();

        $this->logger->info('PIN temporaire créé pour migration', [
            'user_id' => $user->getId()
        ]);

        return $temporaryPin;
    }

    /**
     * Lock account after too many failed attempts
     */
    public function lockAccount(User $user, int $minutes = self::LOCK_DURATION_MINUTES): void
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            return;
        }

        $lockedUntil = (new \DateTimeImmutable())->modify("+{$minutes} minutes");
        $pinAuth->setLockedUntil($lockedUntil);
        $this->entityManager->flush();

        $this->logger->warning('Compte verrouillé', [
            'user_id' => $user->getId(),
            'locked_until' => $lockedUntil->format('Y-m-d H:i:s')
        ]);

        // Envoyer la notification email
        $this->emailService->sendPinLockoutNotification($user, $lockedUntil->format('H:i'));
    }

    /**
     * Check if account is locked
     */
    public function isAccountLocked(User $user): bool
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            return false;
        }

        return $pinAuth->isLocked();
    }

    /**
     * Increment failed attempts
     */
    public function incrementFailedAttempts(User $user): void
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            return;
        }

        $pinAuth->incrementFailedAttempts();
        
        // Lock account if max attempts reached
        if ($pinAuth->getFailedAttempts() >= self::MAX_FAILED_ATTEMPTS) {
            $this->lockAccount($user);
        }

        $this->entityManager->flush();
    }

    /**
     * Reset failed attempts
     */
    public function resetFailedAttempts(User $user): void
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            return;
        }

        $pinAuth->resetFailedAttempts();
        $pinAuth->setLockedUntil(null);
        $this->entityManager->flush();
    }

    /**
     * Get remaining attempts before lock
     */
    public function getRemainingAttempts(User $user): int
    {
        $pinAuth = $user->getPinAuth();

        if (!$pinAuth) {
            return self::MAX_FAILED_ATTEMPTS;
        }

        return max(0, self::MAX_FAILED_ATTEMPTS - $pinAuth->getFailedAttempts());
    }

    /**
     * Re-creates or updates a PIN after a secure reset (recovery)
     */
    public function createPinAfterReset(User $user, string $pin): PinAuth
    {
        if (!$this->isValidPinFormat($pin)) {
            throw new \InvalidArgumentException('Le code PIN doit contenir exactement 5 chiffres');
        }

        $pinAuth = $user->getPinAuth();
        if (!$pinAuth) {
            $pinAuth = new PinAuth();
            $pinAuth->setUser($user);
            $this->entityManager->persist($pinAuth);
        }

        $pinAuth->setPinHash($this->hashPin($pin));
        $pinAuth->setIsEnabled(true);
        $pinAuth->setMustChangePin(false);
        $pinAuth->resetFailedAttempts();
        $pinAuth->setLockedUntil(null);

        $this->entityManager->flush();

        return $pinAuth;
    }

    /**
     * Validate PIN format (5 digits)
     */
    private function isValidPinFormat(string $pin): bool
    {
        return preg_match('/^\d{' . self::PIN_LENGTH . '}$/', $pin) === 1;
    }

    /**
     * Hash a PIN securely
     */
    private function hashPin(string $pin): string
    {
        return password_hash($pin, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a PIN against its hash
     */
    private function verifyPinHash(string $pin, string $hash): bool
    {
        return password_verify($pin, $hash);
    }

    /**
     * Generate a random 5-digit PIN
     */
    private function generateRandomPin(): string
    {
        return str_pad((string) random_int(0, 99999), self::PIN_LENGTH, '0', STR_PAD_LEFT);
    }
}
