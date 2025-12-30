<?php

namespace App\Entity;

use App\Repository\TontineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TontineRepository::class)]
class Tontine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tontineCode = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $amountPerPoint = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalPoints = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $frequency = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $nextDueDate = null;

    #[ORM\Column(nullable: true)]
    private ?bool $reminderEnabled = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'tontines')]
    private ?User $utilisateur = null;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'Tontine')]
    private Collection $transactions;

    /**
     * @var Collection<int, TontinePoint>
     */
    #[ORM\OneToMany(targetEntity: TontinePoint::class, mappedBy: 'tontine', cascade: ['persist'], orphanRemoval: true)]
    private Collection $tontinePoints;

    /**
     * @var Collection<int, TontineReminder>
     */
    #[ORM\OneToMany(targetEntity: TontineReminder::class, mappedBy: 'tontine')]
    private Collection $tontineReminders;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalPay = 0;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->tontinePoints = new ArrayCollection();
        $this->tontineReminders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTontineCode(): ?string
    {
        return $this->tontineCode;
    }

    public function setTontineCode(?string $tontineCode): static
    {
        $this->tontineCode = $tontineCode;

        return $this;
    }

    public function getAmountPerPoint(): ?int
    {
        return $this->amountPerPoint;
    }

    public function setAmountPerPoint(?int $amountPerPoint): static
    {
        $this->amountPerPoint = $amountPerPoint;

        return $this;
    }

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(?int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;

        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(?string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getNextDueDate(): ?\DateTime
    {
        return $this->nextDueDate;
    }

    public function setNextDueDate(?\DateTime $nextDueDate): static
    {
        $this->nextDueDate = $nextDueDate;

        return $this;
    }

    public function isReminderEnabled(): ?bool
    {
        return $this->reminderEnabled;
    }

    public function setReminderEnabled(?bool $reminderEnabled): static
    {
        $this->reminderEnabled = $reminderEnabled;

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

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setTontine($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getTontine() === $this) {
                $transaction->setTontine(null);
            }
        }

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
            $tontinePoint->setTontine($this);
        }

        return $this;
    }

    public function removeTontinePoint(TontinePoint $tontinePoint): static
    {
        if ($this->tontinePoints->removeElement($tontinePoint)) {
            // set the owning side to null (unless already changed)
            if ($tontinePoint->getTontine() === $this) {
                $tontinePoint->setTontine(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TontineReminder>
     */
    public function getTontineReminders(): Collection
    {
        return $this->tontineReminders;
    }

    public function addTontineReminder(TontineReminder $tontineReminder): static
    {
        if (!$this->tontineReminders->contains($tontineReminder)) {
            $this->tontineReminders->add($tontineReminder);
            $tontineReminder->setTontine($this);
        }

        return $this;
    }

    public function removeTontineReminder(TontineReminder $tontineReminder): static
    {
        if ($this->tontineReminders->removeElement($tontineReminder)) {
            // set the owning side to null (unless already changed)
            if ($tontineReminder->getTontine() === $this) {
                $tontineReminder->setTontine(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTotalPay(): ?int
    {
        return $this->totalPay;
    }

    public function setTotalPay(?int $totalPay): static
    {
        $this->totalPay = $totalPay;

        return $this;
    }


    public function updateNextDueDate(): void
    {
        if (!$this->frequency) {
            throw new \LogicException('La fréquence de la tontine est manquante');
        }

        $baseDate = $this->nextDueDate
            ?? $this->startDate
            ?? new \DateTime();

        $baseDate = \DateTimeImmutable::createFromMutable($baseDate);

        switch ($this->frequency) {
            case 'daily':
                $next = $baseDate->modify('+1 day');
                break;

            case 'weekly':
                $next = $baseDate->modify('+1 week');
                break;

            case 'monthly':
                $next = $baseDate->modify('+1 month');
                break;

            default:
                throw new \LogicException('Fréquence invalide : ' . $this->frequency);
        }

        $this->nextDueDate = \DateTime::createFromImmutable($next);
    }
    public function applyPayment(
        int $amount,
        ?Transaction $transaction = null,
        string $method = 'unknown'
    ): void {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Montant invalide');
        }

        if ($this->statut === 'completed') {
            throw new \LogicException('Tontine déjà terminée');
        }

        $previousPaidPoints = $this->getPaidPoints();

        // 1️⃣ Total payé
        $this->totalPay += $amount;

        $newPaidPoints = $this->getPaidPoints();
        $pointsToCreate = $newPaidPoints - $previousPaidPoints;

        // 2️⃣ Créer les nouveaux points
        for ($i = 1; $i <= $pointsToCreate; $i++) {

            $pointNumber = $previousPaidPoints + $i;

            $point = new TontinePoint();
            $point->setPointNumber($pointNumber);
            $point->setAmount($this->amountPerPoint);
            $point->setMethod($method);
            $point->setPointedAt(new \DateTimeImmutable());
            $point->setTontine($this);
            $point->setTransaction($transaction);

            $this->tontinePoints[] = $point;
        }

        // 3️⃣ Clôture ou prochaine échéance
        if ($newPaidPoints >= $this->totalPoints) {
            $this->statut = 'completed';
            $this->nextDueDate = null;
            return;
        }

        $this->updateNextDueDate();
    }
    public function getPaidPoints(): int
    {
        return intdiv($this->totalPay, $this->amountPerPoint);
    }
}
