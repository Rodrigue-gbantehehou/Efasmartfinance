<?php

namespace App\Entity;

use App\Repository\SecuritySettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SecuritySettingsRepository::class)]
class SecuritySettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'securitySettings')]
    private ?User $utilisateur = null;

    #[ORM\Column(nullable: true)]
    private ?bool $twoFactorEnabled = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $twoFactorMethod = null;

    #[ORM\Column(nullable: true)]
    private ?bool $loginAlerts = null;

    #[ORM\Column(nullable: true)]
    private ?int $sessionTimeout = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backupCodes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTwoFactorAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function getBackupCodes(): ?string
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?string $backupCodes): static
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    public function getLastTwoFactorAt(): ?\DateTimeImmutable
    {
        return $this->lastTwoFactorAt;
    }

    public function setLastTwoFactorAt(?\DateTimeImmutable $lastTwoFactorAt): static
    {
        $this->lastTwoFactorAt = $lastTwoFactorAt;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function isTwoFactorEnabled(): ?bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(?bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;

        return $this;
    }

    public function getTwoFactorMethod(): ?string
    {
        return $this->twoFactorMethod;
    }

    public function setTwoFactorMethod(?string $twoFactorMethod): static
    {
        $this->twoFactorMethod = $twoFactorMethod;

        return $this;
    }

    public function isLoginAlerts(): ?bool
    {
        return $this->loginAlerts;
    }

    public function setLoginAlerts(?bool $loginAlerts): static
    {
        $this->loginAlerts = $loginAlerts;

        return $this;
    }

    public function getSessionTimeout(): ?int
    {
        return $this->sessionTimeout;
    }

    public function setSessionTimeout(?int $sessionTimeout): static
    {
        $this->sessionTimeout = $sessionTimeout;

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
}
