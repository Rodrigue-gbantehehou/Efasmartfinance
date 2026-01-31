<?php

namespace App\Entity;

use App\Repository\SystemSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemSettingsRepository::class)]
class SystemSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $settingKey = null;

    #[ORM\Column]
    private ?bool $maintenanceMode = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $maintenanceMessage = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $maintenanceEndedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettingKey(): ?string
    {
        return $this->settingKey;
    }

    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;
        return $this;
    }

    public function isMaintenanceMode(): ?bool
    {
        return $this->maintenanceMode;
    }

    public function setMaintenanceMode(bool $maintenanceMode): static
    {
        $this->maintenanceMode = $maintenanceMode;
        return $this;
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenanceMessage;
    }

    public function setMaintenanceMessage(?string $maintenanceMessage): static
    {
        $this->maintenanceMessage = $maintenanceMessage;
        return $this;
    }

    public function getMaintenanceStartedAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceStartedAt;
    }

    public function setMaintenanceStartedAt(?\DateTimeImmutable $maintenanceStartedAt): static
    {
        $this->maintenanceStartedAt = $maintenanceStartedAt;
        return $this;
    }

    public function getMaintenanceEndedAt(): ?\DateTimeImmutable
    {
        return $this->maintenanceEndedAt;
    }

    public function setMaintenanceEndedAt(?\DateTimeImmutable $maintenanceEndedAt): static
    {
        $this->maintenanceEndedAt = $maintenanceEndedAt;
        return $this;
    }
}
