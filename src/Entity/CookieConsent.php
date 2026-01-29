<?php

namespace App\Entity;

use App\Repository\CookieConsentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CookieConsentRepository::class)]
#[ORM\Table(name: 'cookie_consent')]
class CookieConsent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cookieConsents')]
    private ?User $utilisateur = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'boolean')]
    private bool $necessaryCookies = true;

    #[ORM\Column(type: 'boolean')]
    private bool $analyticsCookies = false;

    #[ORM\Column(type: 'boolean')]
    private bool $marketingCookies = false;

    #[ORM\Column(type: 'boolean')]
    private bool $preferencesCookies = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $consentDate;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $consentVersion = '1.0';

    public function __construct()
    {
        $this->consentDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?User
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?User $utilisateur): self
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function isNecessaryCookies(): bool
    {
        return $this->necessaryCookies;
    }

    public function setNecessaryCookies(bool $necessaryCookies): self
    {
        $this->necessaryCookies = $necessaryCookies;
        return $this;
    }

    public function isAnalyticsCookies(): bool
    {
        return $this->analyticsCookies;
    }

    public function setAnalyticsCookies(bool $analyticsCookies): self
    {
        $this->analyticsCookies = $analyticsCookies;
        return $this;
    }

    public function isMarketingCookies(): bool
    {
        return $this->marketingCookies;
    }

    public function setMarketingCookies(bool $marketingCookies): self
    {
        $this->marketingCookies = $marketingCookies;
        return $this;
    }

    public function isPreferencesCookies(): bool
    {
        return $this->preferencesCookies;
    }

    public function setPreferencesCookies(bool $preferencesCookies): self
    {
        $this->preferencesCookies = $preferencesCookies;
        return $this;
    }

    public function getConsentDate(): \DateTimeImmutable
    {
        return $this->consentDate;
    }

    public function setConsentDate(\DateTimeImmutable $consentDate): self
    {
        $this->consentDate = $consentDate;
        return $this;
    }

    public function getConsentVersion(): ?string
    {
        return $this->consentVersion;
    }

    public function setConsentVersion(?string $consentVersion): self
    {
        $this->consentVersion = $consentVersion;
        return $this;
    }
}
