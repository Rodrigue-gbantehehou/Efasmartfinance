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
    private ?bool $pinEnabled = null;

    #[ORM\Column(nullable: true)]
    private ?bool $loginAlerts = null;

    #[ORM\Column(nullable: true)]
    private ?int $sessionTimeout = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPinAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getLastPinAt(): ?\DateTimeImmutable
    {
        return $this->lastPinAt;
    }

    public function setLastPinAt(?\DateTimeImmutable $lastPinAt): static
    {
        $this->lastPinAt = $lastPinAt;

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

    public function isPinEnabled(): ?bool
    {
        return $this->pinEnabled;
    }

    public function setPinEnabled(?bool $pinEnabled): static
    {
        $this->pinEnabled = $pinEnabled;

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
