<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alert')]
#[ORM\HasLifecycleCallbacks]
class Alert
{
    public const CATEGORIES = ['Crime', 'Accident', 'Fire', 'Missing Person', 'Attack', 'Other'];
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PACK   = 'pack';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 50)]
    private string $category;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private float $latitude;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7)]
    private float $longitude;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'public'])]
    private string $visibility = self::VISIBILITY_PUBLIC;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    private array $media = [];

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $validationCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, AlertValidation> */
    #[ORM\OneToMany(mappedBy: 'alert', targetEntity: AlertValidation::class, cascade: ['remove'])]
    private Collection $validations;

    public function __construct()
    {
        $this->id = self::generateUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->validations = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
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

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getLatitude(): float
    {
        return (float) $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): float
    {
        return (float) $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }

    public function getMedia(): array
    {
        return $this->media;
    }

    public function setMedia(array $media): self
    {
        $this->media = $media;
        return $this;
    }

    public function addMediaUrl(string $url): self
    {
        $this->media[] = $url;
        return $this;
    }

    public function getValidationCount(): int
    {
        return $this->validationCount;
    }

    public function incrementValidationCount(): self
    {
        $this->validationCount++;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getValidations(): Collection
    {
        return $this->validations;
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant bits
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'category'         => $this->category,
            'description'      => $this->description,
            'lat'              => $this->getLatitude(),
            'lng'              => $this->getLongitude(),
            'visibility'       => $this->visibility,
            'created_at'       => $this->createdAt->format(\DateTimeInterface::ATOM),
            'media'            => $this->media,
            'validation_count' => $this->validationCount,
            'user'             => [
                'id'          => $this->user->getId(),
                'name'        => $this->user->getName(),
                'trust_score' => $this->user->getTrustScore(),
            ],
        ];
    }
}
