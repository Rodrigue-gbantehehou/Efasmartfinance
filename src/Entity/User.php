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
#[UniqueEntity(fields: ['uuid'], message: 'There is already an account with this uuid')]
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentFront = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentBack = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $selfie = null;

    /**
     * @var Collection<int, Wallets>
     */
    #[ORM\OneToMany(targetEntity: Wallets::class, mappedBy: 'utilisateur')]
    private Collection $wallets;

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

    /**
     * @var Collection<int, SecuritySettings>
     */
    #[ORM\OneToMany(targetEntity: SecuritySettings::class, mappedBy: 'utilisateur')]
    private Collection $securitySettings;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $identityDocument = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $verificationStatut = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationSubmittedAt = null;

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

    public function __construct()
    {
        $this->tontines = new ArrayCollection();
        $this->transactions = new ArrayCollection();
        $this->isVerified = false;
        $this->wallets = new ArrayCollection();
        $this->contactSupports = new ArrayCollection();
        $this->userTermsAcceptances = new ArrayCollection();
        $this->userSettings = new ArrayCollection();
        $this->notificationPreferences = new ArrayCollection();
        $this->securitySettings = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->securityLogs = new ArrayCollection();
        $this->withdrawals = new ArrayCollection();
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

    public function setLastname(?string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

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
     * @return Collection<int, Wallets>
     */
    public function getWallets(): Collection
    {
        return $this->wallets;
    }

    public function addWallet(Wallets $wallet): static
    {
        if (!$this->wallets->contains($wallet)) {
            $this->wallets->add($wallet);
            $wallet->setUtilisateur($this);
        }

        return $this;
    }

    public function removeWallet(Wallets $wallet): static
    {
        if ($this->wallets->removeElement($wallet)) {
            // set the owning side to null (unless already changed)
            if ($wallet->getUtilisateur() === $this) {
                $wallet->setUtilisateur(null);
            }
        }

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

    public function getDocumentFront(): ?string
    {
        return $this->documentFront;
    }

    public function setDocumentFront(?string $documentFront): static
    {
        $this->documentFront = $documentFront;
        return $this;
    }

    public function getDocumentBack(): ?string
    {
        return $this->documentBack;
    }

    public function setDocumentBack(?string $documentBack): static
    {
        $this->documentBack = $documentBack;
        return $this;
    }

    public function getSelfie(): ?string
    {
        return $this->selfie;
    }

    public function setSelfie(?string $selfie): static
    {
        $this->selfie = $selfie;
        return $this;
    }

    public function getIdentityDocument(): ?string
    {
        return $this->identityDocument;
    }

    public function setIdentityDocument(?string $identityDocument): static
    {
        $this->identityDocument = $identityDocument;

        return $this;
    }

    public function getVerificationStatut(): ?string
    {
        return $this->verificationStatut;
    }

    public function setVerificationStatut(?string $verificationStatut): static
    {
        $this->verificationStatut = $verificationStatut;

        return $this;
    }

    public function getVerificationSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->verificationSubmittedAt;
    }

    public function setVerificationSubmittedAt(?\DateTimeImmutable $verificationSubmittedAt): static
    {
        $this->verificationSubmittedAt = $verificationSubmittedAt;

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
}
