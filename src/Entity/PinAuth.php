<?php

namespace App\Entity;

use App\Repository\PinAuthRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PinAuthRepository::class)]
class PinAuth
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'pinAuth', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $pinHash = null;

    #[ORM\Column(options: ['default' => true])]
    private ?bool $isEnabled = true;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $failedAttempts = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lockedUntil = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $mustChangePin = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->isEnabled = true;
        $this->failedAttempts = 0;
        $this->mustChangePin = false;
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

    public function getPinHash(): ?string
    {
        return $this->pinHash;
    }

    public function setPinHash(string $pinHash): static
    {
        $this->pinHash = $pinHash;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): static
    {
        $this->isEnabled = $isEnabled;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getFailedAttempts(): ?int
    {
        return $this->failedAttempts;
    }

    public function setFailedAttempts(int $failedAttempts): static
    {
        $this->failedAttempts = $failedAttempts;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function incrementFailedAttempts(): static
    {
        $this->failedAttempts++;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function resetFailedAttempts(): static
    {
        $this->failedAttempts = 0;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLockedUntil(): ?\DateTimeImmutable
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeImmutable $lockedUntil): static
    {
        $this->lockedUntil = $lockedUntil;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        return $this->lockedUntil > new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
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

    public function getMustChangePin(): ?bool
    {
        return $this->mustChangePin;
    }

    public function setMustChangePin(bool $mustChangePin): static
    {
        $this->mustChangePin = $mustChangePin;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
