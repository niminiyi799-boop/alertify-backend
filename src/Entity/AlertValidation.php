<?php

namespace App\Entity;

use App\Repository\AlertValidationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertValidationRepository::class)]
#[ORM\Table(name: 'alert_validation')]
#[ORM\UniqueConstraint(name: 'unique_user_alert_validation', columns: ['user_id', 'alert_id'])]
class AlertValidation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Alert::class, inversedBy: 'validations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Alert $alert;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getAlert(): Alert
    {
        return $this->alert;
    }

    public function setAlert(Alert $alert): self
    {
        $this->alert = $alert;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
