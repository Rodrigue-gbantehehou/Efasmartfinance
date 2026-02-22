<?php

namespace App\Entity;

use App\Repository\WithdrawalsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WithdrawalsRepository::class)]
class Withdrawals
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'withdrawals')]
    private ?User $utilisateur = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\ManyToOne(inversedBy: 'withdrawals')]
    private ?User $administrateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\ManyToOne(inversedBy: 'withdrawals')]
    private ?Tontine $tontine = null;
    
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $withdrawalType = null;
    
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $withdrawalMethod = null;
    
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $phoneNumber = null;
   
    
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $transactionId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(?string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(?\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getAdministrateur(): ?User
    {
        return $this->administrateur;
    }

    public function setAdministrateur(?User $administrateur): static
    {
        $this->administrateur = $administrateur;

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

    public function getTontine(): ?Tontine
    {
        return $this->tontine;
    }

    public function setTontine(?Tontine $tontine): static
    {
        $this->tontine = $tontine;

        return $this;
    }

    public function getWithdrawalType(): ?string
    {
        return $this->withdrawalType;
    }
    
    public function setWithdrawalType(?string $withdrawalType): static
    {
        $this->withdrawalType = $withdrawalType;
        return $this;
    }
    
    public function getWithdrawalMethod(): ?string
    {
        return $this->withdrawalMethod;
    }
    
    public function setWithdrawalMethod(?string $withdrawalMethod): static
    {
        $this->withdrawalMethod = $withdrawalMethod;
        return $this;
    }
   
    
    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
    
    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }
    
    public function getStatus(): ?string
    {
        return $this->statut;
    }
    
    public function setStatus(?string $status): static
    {
        $this->statut = $status;
        return $this;
    }
    

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }
}
