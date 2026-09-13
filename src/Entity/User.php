<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $trustScore = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $contributionCount = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Alert::class, cascade: ['remove'])]
    private Collection $alerts;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: UserNotificationSettings::class, cascade: ['persist', 'remove'])]
    private ?UserNotificationSettings $notificationSettings = null;

    public function __construct()
    {
        $this->alerts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getTrustScore(): int
    {
        return $this->trustScore;
    }

    public function setTrustScore(int $trustScore): self
    {
        $this->trustScore = $trustScore;
        return $this;
    }

    public function getContributionCount(): int
    {
        return $this->contributionCount;
    }

    public function setContributionCount(int $contributionCount): self
    {
        $this->contributionCount = $contributionCount;
        return $this;
    }

    public function incrementContributionCount(): self
    {
        $this->contributionCount++;
        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function getEmergencyContact(): ?string
    {
        return $this->emergencyContact;
    }

    public function setEmergencyContact(?string $emergencyContact): self
    {
        $this->emergencyContact = $emergencyContact;
        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $token): self
    {
        $this->passwordResetToken = $token;
        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->passwordResetTokenExpiresAt = $expiresAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function getNotificationSettings(): ?UserNotificationSettings
    {
        return $this->notificationSettings;
    }

    public function setNotificationSettings(?UserNotificationSettings $settings): self
    {
        $this->notificationSettings = $settings;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'trust_score'        => $this->trustScore,
            'contribution_count' => $this->contributionCount,
            'avatar_url'         => $this->avatarUrl,
            'emergency_contact'  => $this->emergencyContact,
        ];
    }
}
