<?php

namespace App\Entity;

use App\Repository\TontineReminderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TontineReminderRepository::class)]
class TontineReminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reminderType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reminderChannel = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\ManyToOne(inversedBy: 'tontineReminders')]
    private ?Tontine $tontine = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReminderType(): ?string
    {
        return $this->reminderType;
    }

    public function setReminderType(?string $reminderType): static
    {
        $this->reminderType = $reminderType;

        return $this;
    }

    public function getReminderChannel(): ?string
    {
        return $this->reminderChannel;
    }

    public function setReminderChannel(?string $reminderChannel): static
    {
        $this->reminderChannel = $reminderChannel;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getSent(): ?string
    {
        return $this->sent;
    }

    public function setSent(?string $sent): static
    {
        $this->sent = $sent;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

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
}
