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

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $amountPerPoint = null;

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

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

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

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $totalPay = '0.00';

    /**
     * @var Collection<int, Withdrawals>
     */
    #[ORM\OneToMany(targetEntity: Withdrawals::class, mappedBy: 'tontine')]
    private Collection $withdrawals;

    #[ORM\Column(type: 'boolean')]
    private bool $fraisPreleves = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $paidFees = 0;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->tontinePoints = new ArrayCollection();
        $this->tontineReminders = new ArrayCollection();
        $this->withdrawals = new ArrayCollection();
        $this->fraisPreleves = false;
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
    
    public function getAmount(): ?int
    {
        return $this->amountPerPoint * $this->totalPoints;
    }
    
        public function getDuration(): ?int
    {
        if (!$this->totalPoints || !$this->frequency) {
            return null;
        }

        return match ($this->frequency) {
            'daily'   => (int) ceil($this->totalPoints / 31),
            'weekly'  => (int) ceil($this->totalPoints / 4),
            'monthly' => (int) $this->totalPoints,
            default   => 1,
        };
    }

    /**
     * Calcule les frais de service à déduire selon la fréquence
     * (Daily: 1 jour/mois, Weekly: 1/4 jour/mois, Monthly: 1/30 jour/mois)
     */
    public function getDeductedServiceFee(): int
    {
        $montantTotalProjete = (float)($this->amountPerPoint ?? 0) * ($this->totalPoints ?? 0);

        return (int) match ($this->frequency) {
            'daily'   => (int) ($montantTotalProjete / 31),
            'weekly'  => (int) ($montantTotalProjete / 30),
            'monthly' => (int) ($montantTotalProjete / 30),
            default   => 0,
        };
    }
    
    public function getAvailableBalance(): float
    {
        // Retourne le solde disponible pour retrait
        // À adapter selon votre logique métier
        return $this->getAmount() - $this->getTotalWithdrawn();
    }
    
    public function setAvailableBalance(float $amount): self
    {
        // Cette méthode est un exemple, à adapter selon votre logique métier
        // Dans une implémentation réelle, vous pourriez vouloir mettre à jour un champ spécifique
        // ou effectuer d'autres opérations pour mettre à jour le solde disponible
        
        // Pour l'instant, nous allons simplement mettre à jour le montant par point
        if ($this->totalPoints > 0) {
            $this->amountPerPoint = (int) ($amount / $this->totalPoints);
        }
        
        return $this;
    }
    
    private function getTotalWithdrawn(): float
    {
        // Implémentez la logique pour obtenir le montant total retiré
        // Par exemple, en faisant la somme des retraits approuvés
        return 0.0; // Valeur par défaut à adapter
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

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

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

    public function getTotalPay(): ?string
    {
        return $this->totalPay;
    }

    public function setTotalPay(?string $totalPay): static
    {
        $this->totalPay = $totalPay;

        return $this;
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

        // Calculer le nombre total de points payés après ce paiement
        $newPaidPoints = $this->getPaidPoints();
        $pointsPaidInThisTransaction = $newPaidPoints - $previousPaidPoints;

        // Créer un point pour chaque point payé dans cette transaction
        for ($i = 1; $i <= $pointsPaidInThisTransaction; $i++) {
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

        // 3️⃣ Vérifier si la tontine est complète
        if ($newPaidPoints >= $this->totalPoints) {
            $this->statut = 'completed';
            $this->nextDueDate = null;
            $this->endedAt = new \DateTimeImmutable();
            return;
        }

        // Mettre à jour la prochaine date d'échéance
        $frequency = $this->getFrequency();
        $intervalMap = [
            'daily'   => 'day',
            'weekly'  => 'week',
            'monthly' => 'month',
            'yearly'  => 'year',
        ];

        if (!array_key_exists($frequency, $intervalMap)) {
            throw new \LogicException('Fréquence de tontine invalide');
        }

        // Toujours ajouter 1 à l'intervalle pour la prochaine échéance
        $interval = sprintf('+1 %s', $intervalMap[$frequency]);

        // Définir la prochaine date d'échéance
        if ($this->nextDueDate instanceof \DateTimeInterface) {
            $this->nextDueDate = (clone $this->nextDueDate)->modify($interval);
        } else {
            // Première échéance
            $this->nextDueDate = (new \DateTime())->modify($interval);
        }
    }
    public function getPaidPoints(): int
    {
        return intdiv($this->totalPay, $this->amountPerPoint);
    }

    /**
     * Vérifie si un retrait partiel a été effectué sur la tontine
     * 
     * @return bool True si un retrait partiel a été effectué, false sinon
     */
    public function isPartiallyWithdrawn(): bool
    {
        $withdrawnAmount = $this->getWithdrawnAmount();
        
        if ($this->frequency === 'daily') {
            return $withdrawnAmount > 0 && $this->getAvailableWithdrawalAmount() > 0;
        }

        return $withdrawnAmount > 0 && $withdrawnAmount < $this->getTotalPay();
    }

    /**
     * Calcule le montant total déjà retiré de la tontine
     */
    public function getWithdrawnAmount(): int
    {
        return (int) array_sum(
            $this->withdrawals
                ->filter(fn($w) => $w->getStatut() === 'approved')
                ->map(fn($w) => (int) $w->getAmount())
                ->toArray()
        );
    }

    /**
     * Récupère le montant disponible pour retrait (Net de frais)
     */
    public function getAvailableWithdrawalAmount(): int
    {
        $basis = (int)$this->getTotalPay() - $this->getWithdrawnAmount();
        
        // On ne déduit que les frais qui sont réellement dus par rapport aux cotisations effectuées
        $feesDueAtThisStage = $this->getFeesDue();
        
        return max(0, $basis - $feesDueAtThisStage);
    }
    
    /**
     * Vérifie si la tontine est complète (tous les points payés ou date de fin dépassée)
     */
    public function isComplete(): bool
    {
        $now = new \DateTimeImmutable();
        
        // Vérifier si la date de fin est dépassée
        if ($this->endedAt && $this->endedAt < $now) {
            return true;
        }
        
        // Vérifier si tous les points ont été payés
        $paidPoints = $this->getPaidPoints();
        if ($this->totalPoints && $paidPoints >= $this->totalPoints) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Effectue un retrait sur la tontine
     * 
     * @throws \RuntimeException Si le montant demandé dépasse le disponible
     */
    public function withdraw(int $amount): void
    {
        $available = $this->getAvailableWithdrawalAmount();
        if ($amount > $available) {
            throw new \RuntimeException(sprintf(
                'Le montant demandé (%s FCFA) dépasse le montant disponible pour retrait (%s FCFA)',
                number_format($amount, 0, ',', ' '),
                number_format($available, 0, ',', ' ')
            ));
        }
    }

    /**
     * Calcule le montant restant à retirer
     */
    public function getRemainingAmount(): int
    {
        return $this->getAvailableWithdrawalAmount();
    }

    /**
     * Vérifie si la tontine est entièrement retirée
     */
    public function isFullyWithdrawn(): bool
    {
        if ($this->frequency === 'daily') {
             return $this->getAvailableWithdrawalAmount() === 0 && ($this->getWithdrawnAmount() > 0 || ($this->getTotalPay() > 0 && $this->getTotalPay() <= $this->getDeductedServiceFee()));
        }

        return $this->getAvailableWithdrawalAmount() === 0 && $this->getWithdrawnAmount() > 0;
    }

    /**
     * Vérifie si un retrait est possible
     */
    public function canWithdraw(): bool
    {
        return $this->getRemainingAmount() > 0;
    }

    /**
     * @return Collection<int, Withdrawals>
     */
    public function getWithdrawals(): Collection
    {
        return $this->withdrawals;
    }

    public function addWithdrawal(Withdrawals $withdrawal): static
    {
        if (!$this->withdrawals->contains($withdrawal)) {
            $this->withdrawals->add($withdrawal);
            $withdrawal->setTontine($this);
        }

        return $this;
    }

    public function removeWithdrawal(Withdrawals $withdrawal): static
    {
        if ($this->withdrawals->removeElement($withdrawal)) {
            // set the owning side to null (unless already changed)
            if ($withdrawal->getTontine() === $this) {
                $withdrawal->setTontine(null);
            }
        }

        return $this;
    }

    public function isFraisPreleves(): bool
    {
        return $this->fraisPreleves;
    }

    public function setFraisPreleves(bool $fraisPreleves): static
    {
        $this->fraisPreleves = $fraisPreleves;
        return $this;
    }

    public function getPaidFees(): int
    {
        return $this->paidFees;
    }

    public function setPaidFees(int $paidFees): static
    {
        $this->paidFees = $paidFees;
        return $this;
    }

    /**
     * Calcule le montant des frais qui auraient dû être payés à ce stade
     */
    public function getFeesDue(): int
    {
        $totalFees = (float)$this->getDeductedServiceFee();
        
        // Les tontines quotidiennes ne sont pas soumises au prorata
        if ($this->frequency === 'daily') {
            return max(0, (int)$totalFees - $this->paidFees);
        }

        $totalToSave = (float)((float)($this->amountPerPoint ?? 0) * ($this->totalPoints ?? 0));
        
        if ($totalToSave <= 0) {
            return 0;
        }

        // Progression de la tontine (basée sur le montant payé)
        $progress = (float)$this->totalPay / $totalToSave;
        
        // Montant théorique dû = TotalFrais * Progression
        // On utilise la progression pour avoir des frais au prorata de l'épargne
        $theoreticalFeesDue = (int) round($totalFees * $progress);
        
        // Reste à payer = Théorique - Déjà payé via KkiaPay ou autre
        return max(0, $theoreticalFeesDue - $this->paidFees);
    }
}
