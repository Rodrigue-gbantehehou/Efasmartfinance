<?php

namespace App\Entity;

use App\Repository\TontinePointRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TontinePointRepository::class)]
class TontinePoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $PointNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pointedAt = null;

    #[ORM\ManyToOne(inversedBy: 'tontinePoints')]
    private ?Tontine $tontine = null;

    #[ORM\ManyToOne(inversedBy: 'tontinePoints')]
    private ?Transaction $transaction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPointNumber(): ?string
    {
        return $this->PointNumber;
    }

    public function setPointNumber(?string $PointNumber): static
    {
        $this->PointNumber = $PointNumber;

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

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getPointedAt(): ?\DateTimeImmutable
    {
        return $this->pointedAt;
    }

    public function setPointedAt(?\DateTimeImmutable $pointedAt): static
    {
        $this->pointedAt = $pointedAt;

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

    public function getTransaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(?Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }
}
