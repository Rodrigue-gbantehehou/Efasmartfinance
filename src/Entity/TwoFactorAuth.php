<?php

namespace App\Entity;

use App\Repository\TwoFactorAuthRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TwoFactorAuthRepository::class)]
class TwoFactorAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'twoFactorAuth', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backupCodes = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isEnabled = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $emailCode = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isEnabled = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getBackupCodes(): ?string
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?string $backupCodes): static
    {
        $this->backupCodes = $backupCodes;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(?bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    /**
     * Get backup codes as array
     */
    public function getBackupCodesArray(): ?array
    {
        return $this->backupCodes ? json_decode($this->backupCodes, true) : null;
    }

    /**
     * Set backup codes from array
     */
    public function setBackupCodesArray(array $codes): static
    {
        $this->backupCodes = json_encode($codes);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Check if user has backup codes available
     */
    public function hasBackupCodes(): bool
    {
        $codes = $this->getBackupCodesArray();
        return $codes && !empty($codes);
    }

    /**
     * Get count of remaining backup codes
     */
    public function getBackupCodesCount(): int
    {
        $codes = $this->getBackupCodesArray();
        return $codes ? count($codes) : 0;
    }

    /**
     * Remove a backup code (after usage)
     */
    public function removeBackupCode(string $code): bool
    {
        $codes = $this->getBackupCodesArray();
        if (!$codes) {
            return false;
        }

        $key = array_search($code, $codes);
        if ($key === false) {
            return false;
        }

        unset($codes[$key]);
        $this->setBackupCodesArray(array_values($codes));

        return true;
    }

    public function getEmailCode(): ?string
    {
        return $this->emailCode;
    }

    public function setEmailCode(?string $emailCode): static
    {
        $this->emailCode = $emailCode;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
