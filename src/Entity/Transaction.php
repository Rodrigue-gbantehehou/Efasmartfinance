<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invoicePath = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?User $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    private ?Tontine $Tontine = null;

    /**
     * @var Collection<int, TontinePoint>
     */
    #[ORM\OneToMany(targetEntity: TontinePoint::class, mappedBy: 'transaction')]
    private Collection $tontinePoints;



    #[ORM\Column(nullable: true)]
    private ?bool $isDeleted = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->tontinePoints = new ArrayCollection();

    }
    
    public function getInvoicePath(): ?string
    {
        return $this->invoicePath;
    }

    public function setInvoicePath(?string $invoicePath): self
    {
        $this->invoicePath = $invoicePath;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): static
    {
        $this->externalReference = $externalReference;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
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

    public function getTontine(): ?Tontine
    {
        return $this->Tontine;
    }

    public function setTontine(?Tontine $Tontine): static
    {
        $this->Tontine = $Tontine;

        return $this;
    }

    /**
     * @return Collection<int, TontinePoint>
     */
    public function getTontinePoints(): Collection
    {
        return $this->tontinePoints;
    }

    public function addTontinePoint(TontinePoint $tontinePoint): static
    {
        if (!$this->tontinePoints->contains($tontinePoint)) {
            $this->tontinePoints->add($tontinePoint);
            $tontinePoint->setTransaction($this);
        }

        return $this;
    }

    public function removeTontinePoint(TontinePoint $tontinePoint): static
    {
        if ($this->tontinePoints->removeElement($tontinePoint)) {
            // set the owning side to null (unless already changed)
            if ($tontinePoint->getTransaction() === $this) {
                $tontinePoint->setTransaction(null);
            }
        }

        return $this;
    }



    public function isDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(?bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
