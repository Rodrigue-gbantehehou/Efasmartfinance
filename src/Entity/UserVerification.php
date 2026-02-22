<?php

namespace App\Entity;

use App\Repository\UserVerificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserVerificationRepository::class)]
class UserVerification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'verifications', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentFront = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $selfie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $identityData = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

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

    public function getSelfie(): ?string
    {
        return $this->selfie;
    }

    public function setSelfie(?string $selfie): static
    {
        $this->selfie = $selfie;

        return $this;
    }

    public function getIdentityData(): ?string
    {
        return $this->identityData;
    }

    public function setIdentityData(?string $identityData): static
    {
        $this->identityData = $identityData;

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
