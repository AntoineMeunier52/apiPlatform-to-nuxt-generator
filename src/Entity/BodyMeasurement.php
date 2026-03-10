<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\BodyMeasurementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * BodyMeasurement entity tests:
 * - Inlined ManyToOne relation (User): when normalization groups include user groups,
 *   the user relation should be typed as User object (inlined), not string (IRI).
 * - Read-only computed BMI field (virtual, no setter)
 * - Relation to User, typed contextually
 */
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['body_measurement:list']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['body_measurement:create']],
            normalizationContext: ['groups' => ['body_measurement:read']],
        ),
        // Detailed read: includes inline User object (user groups match context)
        new Get(
            normalizationContext: ['groups' => ['body_measurement:read', 'user:list']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['body_measurement:update']],
            normalizationContext: ['groups' => ['body_measurement:read']],
        ),
        new Delete(),

        // Stats: collection of summaries (no user inline)
        new GetCollection(
            uriTemplate: '/api/body_measurements/stats',
            name: 'body_measurement_stats',
            normalizationContext: ['groups' => ['body_measurement:stats']],
        ),
    ]
)]
#[ORM\Entity(repositoryClass: BodyMeasurementRepository::class)]
class BodyMeasurement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['body_measurement:list', 'body_measurement:read', 'body_measurement:stats'])]
    private ?int $id = null;

    /**
     * Relation to User.
     * - In 'body_measurement:list' context → IRI string (groups don't include user groups)
     * - In 'body_measurement:read' + 'user:list' context → inlined User object
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[Groups(['body_measurement:list', 'body_measurement:read', 'body_measurement:create'])]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    #[Assert\Positive]
    #[Groups(['body_measurement:list', 'body_measurement:read', 'body_measurement:stats', 'body_measurement:create', 'body_measurement:update'])]
    private float $weightKg = 0.0;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['body_measurement:read', 'body_measurement:create', 'body_measurement:update'])]
    private ?float $bodyFatPercent = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['body_measurement:read', 'body_measurement:create', 'body_measurement:update'])]
    private ?float $muscleMassKg = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['body_measurement:read', 'body_measurement:stats'])]
    private ?float $heightCm = null;

    /** Read-only: computed BMI, no setter */
    #[Groups(['body_measurement:read', 'body_measurement:stats'])]
    private ?float $bmi = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['body_measurement:list', 'body_measurement:read', 'body_measurement:stats', 'body_measurement:create'])]
    private \DateTimeImmutable $measuredAt;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['body_measurement:read', 'body_measurement:create', 'body_measurement:update'])]
    private ?string $notes = null;

    public function __construct()
    {
        $this->measuredAt = new \DateTimeImmutable();
    }

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

    public function getWeightKg(): float
    {
        return $this->weightKg;
    }

    public function setWeightKg(float $weightKg): static
    {
        $this->weightKg = $weightKg;
        return $this;
    }

    public function getBodyFatPercent(): ?float
    {
        return $this->bodyFatPercent;
    }

    public function setBodyFatPercent(?float $bodyFatPercent): static
    {
        $this->bodyFatPercent = $bodyFatPercent;
        return $this;
    }

    public function getMuscleMassKg(): ?float
    {
        return $this->muscleMassKg;
    }

    public function setMuscleMassKg(?float $muscleMassKg): static
    {
        $this->muscleMassKg = $muscleMassKg;
        return $this;
    }

    public function getHeightCm(): ?float
    {
        return $this->heightCm;
    }

    public function setHeightCm(?float $heightCm): static
    {
        $this->heightCm = $heightCm;
        return $this;
    }

    /** Computed BMI, no setter - read only */
    public function getBmi(): ?float
    {
        if ($this->heightCm === null || $this->heightCm <= 0) {
            return null;
        }
        $heightM = $this->heightCm / 100;
        return round($this->weightKg / ($heightM * $heightM), 1);
    }

    public function getMeasuredAt(): \DateTimeImmutable
    {
        return $this->measuredAt;
    }

    public function setMeasuredAt(\DateTimeImmutable $measuredAt): static
    {
        $this->measuredAt = $measuredAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }
}
