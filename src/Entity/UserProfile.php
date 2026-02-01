<?php

namespace App\Entity;

use App\Repository\UserProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserProfileRepository::class)]
class UserProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 191, unique: true)]
    private string $userEmail;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 8)]
    private float $latitude;

    #[ORM\Column(type: 'decimal', precision: 11, scale: 8)]
    private float $longitude;

    #[ORM\Column(type: 'text')]
    private string $address;

    // --- ID ---
    public function getId(): ?int
    {
        return $this->id;
    }

    // --- User Email ---
    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): self
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    // --- Latitude ---
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    // --- Longitude ---
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    // --- Address ---
    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }
}
