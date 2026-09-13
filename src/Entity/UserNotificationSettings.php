<?php

namespace App\Entity;

use App\Repository\UserNotificationSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotificationSettingsRepository::class)]
#[ORM\Table(name: 'user_notification_settings')]
class UserNotificationSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'notificationSettings', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'integer', options: ['default' => 5])]
    private int $alertRadiusKm = 5;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $nightMode = false;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $enabledCategories = ['Crime', 'Accident', 'Missing Person'];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAlertRadiusKm(): int
    {
        return $this->alertRadiusKm;
    }

    public function setAlertRadiusKm(int $radius): self
    {
        $this->alertRadiusKm = $radius;
        return $this;
    }

    public function isNightMode(): bool
    {
        return $this->nightMode;
    }

    public function setNightMode(bool $nightMode): self
    {
        $this->nightMode = $nightMode;
        return $this;
    }

    public function getEnabledCategories(): array
    {
        return $this->enabledCategories;
    }

    public function setEnabledCategories(array $categories): self
    {
        $this->enabledCategories = $categories;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'alert_radius_km'    => $this->alertRadiusKm,
            'night_mode'         => $this->nightMode,
            'enabled_categories' => $this->enabledCategories,
        ];
    }
}
