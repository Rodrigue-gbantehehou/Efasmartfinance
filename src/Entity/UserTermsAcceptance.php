<?php

namespace App\Entity;

use App\Repository\UserTermsAcceptanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserTermsAcceptanceRepository::class)]
class UserTermsAcceptance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userTermsAcceptances')]
    private ?User $utilisateur = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $IpAdress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $useragent = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $termsversion = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

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

    public function getIpAdress(): ?string
    {
        return $this->IpAdress;
    }

    public function setIpAdress(?string $IpAdress): static
    {
        $this->IpAdress = $IpAdress;

        return $this;
    }

    public function getUseragent(): ?string
    {
        return $this->useragent;
    }

    public function setUseragent(?string $useragent): static
    {
        $this->useragent = $useragent;

        return $this;
    }

    public function getTermsversion(): ?string
    {
        return $this->termsversion;
    }

    public function setTermsversion(?string $termsversion): static
    {
        $this->termsversion = $termsversion;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }
}
