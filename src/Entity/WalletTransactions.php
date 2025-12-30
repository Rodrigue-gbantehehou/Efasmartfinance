<?php

namespace App\Entity;

use App\Repository\WalletTransactionsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WalletTransactionsRepository::class)]
class WalletTransactions
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'walletTransactions', cascade: ['persist'])]
    private ?Wallets $wallet = null;

    #[ORM\ManyToOne(inversedBy: 'walletTransactions')]
    private ?Transaction $transactions = null;

    #[ORM\Column(nullable: true)]
    private ?float $amount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isAutomatic = null;

    #[ORM\Column(nullable: true)]
    private ?float $newBalance = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWallet(): ?Wallets
    {
        return $this->wallet;
    }

    public function setWallet(?Wallets $wallet): static
    {
        $this->wallet = $wallet;

        return $this;
    }

    public function getTransactions(): ?Transaction
    {
        return $this->transactions;
    }

    public function setTransactions(?Transaction $transactions): static
    {
        $this->transactions = $transactions;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isAutomatic(): ?bool
    {
        return $this->isAutomatic;
    }

    public function setIsAutomatic(?bool $isAutomatic): static
    {
        $this->isAutomatic = $isAutomatic;

        return $this;
    }

    public function getNewBalance(): ?float
    {
        return $this->newBalance;
    }

    public function setNewBalance(?float $newBalance): static
    {
        $this->newBalance = $newBalance;

        return $this;
    }
}
