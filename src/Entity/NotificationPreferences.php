<?php

namespace App\Entity;

use App\Repository\NotificationPreferencesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationPreferencesRepository::class)]
class NotificationPreferences
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notificationPreferences')]
    private ?User $utilisateur = null;

    #[ORM\Column(nullable: true)]
    private ?bool $emailNotifications = null;

    #[ORM\Column(nullable: true)]
    private ?bool $pushNotifications = null;

    #[ORM\Column(nullable: true)]
    private ?bool $TransactionAlerts = null;

    #[ORM\Column(nullable: true)]
    private ?bool $marketingEmail = null;

    #[ORM\Column(nullable: true)]
    private ?bool $paymentReminders = null;

    #[ORM\Column(nullable: true)]
    private ?bool $securityAlerts = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function isEmailNotifications(): ?bool
    {
        return $this->emailNotifications;
    }

    public function setEmailNotifications(?bool $emailNotifications): static
    {
        $this->emailNotifications = $emailNotifications;

        return $this;
    }

    public function isPushNotifications(): ?bool
    {
        return $this->pushNotifications;
    }

    public function setPushNotifications(?bool $pushNotifications): static
    {
        $this->pushNotifications = $pushNotifications;

        return $this;
    }

    public function isTransactionAlerts(): ?bool
    {
        return $this->TransactionAlerts;
    }

    public function setTransactionAlerts(?bool $TransactionAlerts): static
    {
        $this->TransactionAlerts = $TransactionAlerts;

        return $this;
    }

    public function isMarketingEmail(): ?bool
    {
        return $this->marketingEmail;
    }

    public function setMarketingEmail(?bool $marketingEmail): static
    {
        $this->marketingEmail = $marketingEmail;

        return $this;
    }

    public function isPaymentReminders(): ?bool
    {
        return $this->paymentReminders;
    }

    public function setPaymentReminders(?bool $paymentReminders): static
    {
        $this->paymentReminders = $paymentReminders;

        return $this;
    }

    public function isSecurityAlerts(): ?bool
    {
        return $this->securityAlerts;
    }

    public function setSecurityAlerts(?bool $securityAlerts): static
    {
        $this->securityAlerts = $securityAlerts;

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
