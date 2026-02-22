<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;


#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_UUID', fields: ['uuid'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_PHONE', fields: ['phoneNumber'])]
#[UniqueEntity(fields: ['uuid'], message: 'There is already an account with this uuid')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[UniqueEntity(fields: ['phoneNumber'], message: 'Ce numéro de téléphone est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $uuid = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Tontine>
     */
    #[ORM\OneToMany(targetEntity: Tontine::class, mappedBy: 'utilisateur')]
    private Collection $tontines;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'utilisateur')]
    private Collection $transactions;

    /**
     * @var Collection<int, CookieConsent>
     */
    #[ORM\OneToMany(targetEntity: CookieConsent::class, mappedBy: 'utilisateur')]
    private Collection $cookieConsents;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phoneNumber = null;

    /**
     * @var Collection<int, Facture>
     */
    #[ORM\OneToMany(targetEntity: Facture::class, mappedBy: 'client')]
    private Collection $factures;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $nationality = null;



    /**
     * @var Collection<int, ContactSupport>
     */
    #[ORM\OneToMany(targetEntity: ContactSupport::class, mappedBy: 'utilisateur')]
    private Collection $contactSupports;

    /**
     * @var Collection<int, UserTermsAcceptance>
     */
    #[ORM\OneToMany(targetEntity: UserTermsAcceptance::class, mappedBy: 'utilisateur')]
    private Collection $userTermsAcceptances;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordChangedAt = null;

    /**
     * @var Collection<int, UserSettings>
     */
    #[ORM\OneToMany(targetEntity: UserSettings::class, mappedBy: 'utilisateur')]
    private Collection $userSettings;

    /**
     * @var Collection<int, NotificationPreferences>
     */
    #[ORM\OneToMany(targetEntity: NotificationPreferences::class, mappedBy: 'utilisateur')]
    private Collection $notificationPreferences;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletionWarningSentAt = null;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $notifications;

    /**
     * @var Collection<int, SecuritySettings>
     */
    #[ORM\OneToMany(targetEntity: SecuritySettings::class, mappedBy: 'utilisateur')]
    private Collection $securitySettings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * @var Collection<int, ActivityLog>
     */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'utilisateur')]
    private Collection $activityLogs;

    /**
     * @var Collection<int, SecurityLog>
     */
    #[ORM\OneToMany(targetEntity: SecurityLog::class, mappedBy: 'utilisateur')]
    private Collection $securityLogs;

    /**
     * @var Collection<int, Withdrawals>
     */
    #[ORM\OneToMany(targetEntity: Withdrawals::class, mappedBy: 'utilisateur')]
    private Collection $withdrawals;



    #[ORM\OneToOne(mappedBy: 'user', targetEntity: PinAuth::class, cascade: ['persist', 'remove'])]
    private ?PinAuth $pinAuth = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserVerification::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['submittedAt' => 'DESC'])]
    private Collection $verifications;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletionRequestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->isVerified = false;
        $this->uuid = uniqid('', true);
        $this->tontines = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->cookieConsents = new ArrayCollection();
        $this->factures = new ArrayCollection();

        $this->contactSupports = new ArrayCollection();
        $this->userTermsAcceptances = new ArrayCollection();
        $this->userSettings = new ArrayCollection();
        $this->notificationPreferences = new ArrayCollection();
        $this->securitySettings = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->securityLogs = new ArrayCollection();
        $this->withdrawals = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->verifications = new ArrayCollection();

        // PinAuth will be created after registration
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->uuid;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * @return Collection<int, Tontine>
     */
    public function getTontines(): Collection
    {
        return $this->tontines;
    }

    public function addTontine(Tontine $tontine): static
    {
        if (!$this->tontines->contains($tontine)) {
            $this->tontines->add($tontine);
            $tontine->setUtilisateur($this);
        }

        return $this;
    }

    public function removeTontine(Tontine $tontine): static
    {
        if ($this->tontines->removeElement($tontine)) {
            // set the owning side to null (unless already changed)
            if ($tontine->getUtilisateur() === $this) {
                $tontine->setUtilisateur(null);
            }
        }

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
            $transaction->setUtilisateur($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getUtilisateur() === $this) {
                $transaction->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->getFirstname() . ' ' . $this->getLastname());
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return Collection<int, Facture>
     */
    public function getFactures(): Collection
    {
        return $this->factures;
    }

    public function addFacture(Facture $facture): self
    {
        if (!$this->factures->contains($facture)) {
            $this->factures->add($facture);
            $facture->setClient($this);
        }

        return $this;
    }

    public function removeFacture(Facture $facture): self
    {
        if ($this->factures->removeElement($facture)) {
            // set the owning side to null (unless already changed)
            if ($facture->getClient() === $this) {
                $facture->setClient(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
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

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }



    /**
     * @return Collection<int, ContactSupport>
     */
    public function getContactSupports(): Collection
    {
        return $this->contactSupports;
    }

    public function addContactSupport(ContactSupport $contactSupport): static
    {
        if (!$this->contactSupports->contains($contactSupport)) {
            $this->contactSupports->add($contactSupport);
            $contactSupport->setUtilisateur($this);
        }

        return $this;
    }

    public function removeContactSupport(ContactSupport $contactSupport): static
    {
        if ($this->contactSupports->removeElement($contactSupport)) {
            // set the owning side to null (unless already changed)
            if ($contactSupport->getUtilisateur() === $this) {
                $contactSupport->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserTermsAcceptance>
     */
    public function getUserTermsAcceptances(): Collection
    {
        return $this->userTermsAcceptances;
    }

    public function addUserTermsAcceptance(UserTermsAcceptance $userTermsAcceptance): static
    {
        if (!$this->userTermsAcceptances->contains($userTermsAcceptance)) {
            $this->userTermsAcceptances->add($userTermsAcceptance);
            $userTermsAcceptance->setUtilisateur($this);
        }

        return $this;
    }

    public function removeUserTermsAcceptance(UserTermsAcceptance $userTermsAcceptance): static
    {
        if ($this->userTermsAcceptances->removeElement($userTermsAcceptance)) {
            // set the owning side to null (unless already changed)
            if ($userTermsAcceptance->getUtilisateur() === $this) {
                $userTermsAcceptance->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getPasswordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): self
    {
        $this->passwordChangedAt = $passwordChangedAt;
        return $this;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * @return Collection<int, UserSettings>
     */
    public function getUserSettings(): Collection
    {
        return $this->userSettings;
    }

    public function addUserSetting(UserSettings $userSetting): static
    {
        if (!$this->userSettings->contains($userSetting)) {
            $this->userSettings->add($userSetting);
            $userSetting->setUtilisateur($this);
        }

        return $this;
    }

    public function removeUserSetting(UserSettings $userSetting): static
    {
        if ($this->userSettings->removeElement($userSetting)) {
            // set the owning side to null (unless already changed)
            if ($userSetting->getUtilisateur() === $this) {
                $userSetting->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, NotificationPreferences>
     */
    public function getNotificationPreferences(): Collection
    {
        return $this->notificationPreferences;
    }

    public function addNotificationPreference(NotificationPreferences $notificationPreference): static
    {
        if (!$this->notificationPreferences->contains($notificationPreference)) {
            $this->notificationPreferences->add($notificationPreference);
            $notificationPreference->setUtilisateur($this);
        }

        return $this;
    }

    public function removeNotificationPreference(NotificationPreferences $notificationPreference): static
    {
        if ($this->notificationPreferences->removeElement($notificationPreference)) {
            // set the owning side to null (unless already changed)
            if ($notificationPreference->getUtilisateur() === $this) {
                $notificationPreference->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SecuritySettings>
     */
    public function getSecuritySettings(): Collection
    {
        return $this->securitySettings;
    }

    public function addSecuritySetting(SecuritySettings $securitySetting): static
    {
        if (!$this->securitySettings->contains($securitySetting)) {
            $this->securitySettings->add($securitySetting);
            $securitySetting->setUtilisateur($this);
        }

        return $this;
    }

    public function removeSecuritySetting(SecuritySettings $securitySetting): static
    {
        if ($this->securitySettings->removeElement($securitySetting)) {
            // set the owning side to null (unless already changed)
            if ($securitySetting->getUtilisateur() === $this) {
                $securitySetting->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    public function addActivityLog(ActivityLog $activityLog): static
    {
        if (!$this->activityLogs->contains($activityLog)) {
            $this->activityLogs->add($activityLog);
            $activityLog->setUtilisateur($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog)) {
            // set the owning side to null (unless already changed)
            if ($activityLog->getUtilisateur() === $this) {
                $activityLog->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SecurityLog>
     */
    public function getSecurityLogs(): Collection
    {
        return $this->securityLogs;
    }

    public function addSecurityLog(SecurityLog $securityLog): static
    {
        if (!$this->securityLogs->contains($securityLog)) {
            $this->securityLogs->add($securityLog);
            $securityLog->setUtilisateur($this);
        }

        return $this;
    }

    public function removeSecurityLog(SecurityLog $securityLog): static
    {
        if ($this->securityLogs->removeElement($securityLog)) {
            // set the owning side to null (unless already changed)
            if ($securityLog->getUtilisateur() === $this) {
                $securityLog->setUtilisateur(null);
            }
        }

        return $this;
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
            $withdrawal->setUtilisateur($this);
        }

        return $this;
    }

    public function removeWithdrawal(Withdrawals $withdrawal): static
    {
        if ($this->withdrawals->removeElement($withdrawal)) {
            // set the owning side to null (unless already changed)
            if ($withdrawal->getUtilisateur() === $this) {
                $withdrawal->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CookieConsent>
     */
    public function getCookieConsents(): Collection
    {
        return $this->cookieConsents;
    }

    public function addCookieConsent(CookieConsent $cookieConsent): static
    {
        if (!$this->cookieConsents->contains($cookieConsent)) {
            $this->cookieConsents->add($cookieConsent);
            $cookieConsent->setUtilisateur($this);
        }

        return $this;
    }

    public function removeCookieConsent(CookieConsent $cookieConsent): static
    {
        if ($this->cookieConsents->removeElement($cookieConsent)) {
            // set the owning side to null (unless already changed)
            if ($cookieConsent->getUtilisateur() === $this) {
                $cookieConsent->setUtilisateur(null);
            }
        }

        return $this;
    }



    public function getLatestCookieConsent(): ?CookieConsent
    {
        $consents = $this->cookieConsents->toArray();
        
        if (empty($consents)) {
            return null;
        }
        
        // Trier par date décroissante
        usort($consents, function($a, $b) {
            return $b->getConsentDate() <=> $a->getConsentDate();
        });
        
        return $consents[0];
    }

    // ========================================
    // Méthodes pour PinAuth
    // ========================================

    public function getPinAuth(): ?PinAuth
    {
        return $this->pinAuth;
    }

    public function setPinAuth(?PinAuth $pinAuth): static
    {
        $this->pinAuth = $pinAuth;

        // Set the owning side of the relation if necessary
        if ($pinAuth && $pinAuth->getUser() !== $this) {
            $pinAuth->setUser($this);
        }

        return $this;
    }

    public function hasPinAuth(): bool
    {
        return $this->pinAuth !== null && $this->pinAuth->isEnabled();
    }

    public function mustChangePin(): bool
    {
        return $this->pinAuth !== null && $this->pinAuth->getMustChangePin();
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getDeletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->deletionRequestedAt;
    }

    public function setDeletionRequestedAt(?\DateTimeImmutable $deletionRequestedAt): static
    {
        $this->deletionRequestedAt = $deletionRequestedAt;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isPendingDeletion(): bool
    {
        return $this->deletionRequestedAt !== null && $this->deletedAt === null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Returns the number of days remaining before permanent deletion.
     */
    public function getDaysUntilDeletion(): int
    {
        if ($this->deletionRequestedAt === null) {
            return 30;
        }
        $deadline = $this->deletionRequestedAt->modify('+30 days');
        $now = new \DateTimeImmutable();
        $diff = $now->diff($deadline);
        return max(0, (int) $diff->days);
    }

    /**
     * Anonymizes the user's personal data permanently.
     * Called after the grace period expires.
     */
    public function anonymize(): static
    {
        $this->firstname = 'Utilisateur';
        $this->lastname = 'Supprimé';
        $this->email = 'deleted_' . $this->id . '@deleted.efa';
        $this->phoneNumber = null;
        $this->address = null;
        $this->birthDate = null;
        $this->nationality = null;
        
        if ($this->verification) {
            $this->verification->setDocumentFront(null);
            $this->verification->setSelfie(null);
            $this->verification->setIdentityData(null);
            $this->verification->setRejectionReason('Compte anonymisé');
        }

        $this->deletedAt = new \DateTimeImmutable();
        $this->isActive = false;

        return $this;
    }

    public function getDeletionWarningSentAt(): ?\DateTimeImmutable
    {
        return $this->deletionWarningSentAt;
    }

    public function setDeletionWarningSentAt(?\DateTimeImmutable $deletionWarningSentAt): static
    {
        $this->deletionWarningSentAt = $deletionWarningSentAt;
        return $this;
    }

    /**
     * @return Collection<int, UserVerification>
     */
    public function getVerifications(): Collection
    {
        return $this->verifications;
    }

    public function addVerification(UserVerification $verification): static
    {
        if (!$this->verifications->contains($verification)) {
            $this->verifications->add($verification);
            $verification->setUser($this);
        }

        return $this;
    }

    public function removeVerification(UserVerification $verification): static
    {
        if ($this->verifications->removeElement($verification)) {
            // set the owning side to null (unless already changed)
            if ($verification->getUser() === $this) {
                $verification->setUser(null);
            }
        }

        return $this;
    }

    public function getLatestVerification(): ?UserVerification
    {
        return $this->verifications->first() ?: null;
    }

    public function getVerificationStatus(): string
    {
        $latest = $this->getLatestVerification();
        if (!$latest) {
            return 'None';
        }

        return match($latest->getStatus()) {
            'pending' => 'En attente',
            'verified' => 'Vérifié',
            'rejected' => 'Rejeté',
            default => 'Non renseigné'
        };
    }
}
