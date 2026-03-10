<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\SessionStatusEnum;
use App\Enum\WorkoutTypeEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Represents a real-time training session started by a user.
 *
 * Complex entity designed to stress-test the Nuxt generator with:
 * - Full standard CRUD (GetCollection, Get, Post, Patch, Put, Delete)
 * - Custom GET on item: /summary
 * - Custom GET on collection: /today, /active
 * - Custom POST on item: /start, /complete, /pause, /resume
 * - Custom POST on collection (non-canonical): /from-template
 * - Custom DELETE on item: /exercises (clear all exercises)
 * - Multiple serialization groups per operation
 * - QueryParameters with filters and ordering
 */
#[ORM\Entity]
#[ApiResource(
    operations: [
        // ── Standard CRUD ────────────────────────────────────────────────────
        new GetCollection(
            normalizationContext: ['groups' => ['training_session:list']],
            parameters: [
                'status'                      => new QueryParameter(filter: 'training_session.search_filter'),
                'user'                        => new QueryParameter(filter: 'training_session.search_filter'),
                'workout'                     => new QueryParameter(filter: 'training_session.search_filter'),
                'type'                        => new QueryParameter(filter: 'training_session.search_filter'),
                'isPublic'                    => new QueryParameter(filter: 'training_session.boolean_filter'),
                'startedAt[before]'           => new QueryParameter(filter: 'training_session.date_filter'),
                'startedAt[strictly_before]'  => new QueryParameter(filter: 'training_session.date_filter'),
                'startedAt[after]'            => new QueryParameter(filter: 'training_session.date_filter'),
                'startedAt[strictly_after]'   => new QueryParameter(filter: 'training_session.date_filter'),
                'completedAt[before]'         => new QueryParameter(filter: 'training_session.date_filter'),
                'completedAt[after]'          => new QueryParameter(filter: 'training_session.date_filter'),
                'order[startedAt]'            => new QueryParameter(filter: 'training_session.order_filter'),
                'order[completedAt]'          => new QueryParameter(filter: 'training_session.order_filter'),
                'order[durationSeconds]'      => new QueryParameter(filter: 'training_session.order_filter'),
                'order[totalVolumeKg]'        => new QueryParameter(filter: 'training_session.order_filter'),
            ],
        ),
        new Get(
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['training_session:create']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['training_session:update']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Put(
            denormalizationContext: ['groups' => ['training_session:replace']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Delete(),

        // ── Custom GET on collection ──────────────────────────────────────────
        new GetCollection(
            uriTemplate: '/training_sessions/today',
            name: 'training_session_today',
            normalizationContext: ['groups' => ['training_session:list']],
        ),
        new GetCollection(
            uriTemplate: '/training_sessions/active',
            name: 'training_session_active',
            normalizationContext: ['groups' => ['training_session:list']],
            parameters: [
                'user' => new QueryParameter(filter: 'training_session.search_filter'),
                'order[startedAt]' => new QueryParameter(filter: 'training_session.order_filter'),
            ],
        ),

        // ── Custom GET on item ────────────────────────────────────────────────
        new Get(
            uriTemplate: '/training_sessions/{id}/summary',
            name: 'training_session_summary',
            normalizationContext: ['groups' => ['training_session:summary']],
        ),
        new Get(
            uriTemplate: '/training_sessions/{id}/performance',
            name: 'training_session_performance',
            normalizationContext: ['groups' => ['training_session:performance']],
        ),

        // ── Custom POST on item: lifecycle actions ────────────────────────────
        new Post(
            uriTemplate: '/training_sessions/{id}/start',
            name: 'training_session_start',
            denormalizationContext: ['groups' => ['training_session:start']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Post(
            uriTemplate: '/training_sessions/{id}/pause',
            name: 'training_session_pause',
            denormalizationContext: ['groups' => ['training_session:pause']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Post(
            uriTemplate: '/training_sessions/{id}/resume',
            name: 'training_session_resume',
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Post(
            uriTemplate: '/training_sessions/{id}/complete',
            name: 'training_session_complete',
            denormalizationContext: ['groups' => ['training_session:complete']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),
        new Post(
            uriTemplate: '/training_sessions/{id}/cancel',
            name: 'training_session_cancel',
            normalizationContext: ['groups' => ['training_session:read']],
        ),

        // ── Custom POST on collection (non-canonical) ─────────────────────────
        new Post(
            uriTemplate: '/training_sessions/from-template',
            name: 'training_session_from_template',
            denormalizationContext: ['groups' => ['training_session:from_template']],
            normalizationContext: ['groups' => ['training_session:read']],
        ),

        // ── Custom DELETE on item: clear exercises ────────────────────────────
        new Delete(
            uriTemplate: '/training_sessions/{id}/exercises',
            name: 'training_session_clear_exercises',
        ),
    ]
)]
class TrainingSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary', 'training_session:performance'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:create'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Workout::class)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:create', 'training_session:replace'])]
    private ?Workout $workout = null;

    #[ORM\Column(type: 'string', enumType: SessionStatusEnum::class)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary'])]
    private SessionStatusEnum $status = SessionStatusEnum::PENDING;

    #[ORM\Column(type: 'string', enumType: WorkoutTypeEnum::class, nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:create', 'training_session:replace'])]
    private ?WorkoutTypeEnum $type = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:create', 'training_session:update', 'training_session:replace'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['training_session:read', 'training_session:create', 'training_session:update', 'training_session:replace'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:create', 'training_session:update', 'training_session:replace'])]
    private bool $isPublic = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary'])]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['training_session:read'])]
    private ?\DateTimeImmutable $pausedAt = null;

    /** Total elapsed seconds (excluding pause time) */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary', 'training_session:performance'])]
    private ?int $durationSeconds = null;

    /** Accumulated pause duration in seconds */
    #[ORM\Column(type: 'integer')]
    #[Groups(['training_session:read'])]
    private int $pauseDurationSeconds = 0;

    /** Total volume lifted (kg × reps × sets) */
    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['training_session:list', 'training_session:read', 'training_session:summary', 'training_session:performance'])]
    private ?float $totalVolumeKg = null;

    /** Average heart rate (BPM) if tracked */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:performance'])]
    private ?int $avgHeartRate = null;

    /** Peak heart rate (BPM) if tracked */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:performance'])]
    private ?int $peakHeartRate = null;

    /** Estimated calories burned */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:summary', 'training_session:performance'])]
    private ?int $caloriesBurned = null;

    /** Perceived exertion (RPE) 1-10 */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:complete', 'training_session:summary'])]
    private ?int $perceivedExertion = null;

    /** Mood/energy rating 1-5 */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:complete'])]
    private ?int $moodRating = null;

    /** GPS location where session took place */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['training_session:read', 'training_session:create', 'training_session:start'])]
    private ?string $location = null;

    /** Template ID used when creating from a template */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['training_session:read', 'training_session:from_template'])]
    private ?int $sourceTemplateId = null;

    /**
     * @var Collection<int, WorkoutExercise>
     */
    #[ORM\OneToMany(mappedBy: 'trainingSession', targetEntity: WorkoutExercise::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['training_session:read'])]
    private Collection $exercises;

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getWorkout(): ?Workout
    {
        return $this->workout;
    }

    public function setWorkout(?Workout $workout): self
    {
        $this->workout = $workout;
        return $this;
    }

    public function getStatus(): SessionStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SessionStatusEnum $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getType(): ?WorkoutTypeEnum
    {
        return $this->type;
    }

    public function setType(?WorkoutTypeEnum $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getPausedAt(): ?\DateTimeImmutable
    {
        return $this->pausedAt;
    }

    public function setPausedAt(?\DateTimeImmutable $pausedAt): self
    {
        $this->pausedAt = $pausedAt;
        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;
        return $this;
    }

    public function getPauseDurationSeconds(): int
    {
        return $this->pauseDurationSeconds;
    }

    public function setPauseDurationSeconds(int $pauseDurationSeconds): self
    {
        $this->pauseDurationSeconds = $pauseDurationSeconds;
        return $this;
    }

    public function getTotalVolumeKg(): ?float
    {
        return $this->totalVolumeKg;
    }

    public function setTotalVolumeKg(?float $totalVolumeKg): self
    {
        $this->totalVolumeKg = $totalVolumeKg;
        return $this;
    }

    public function getAvgHeartRate(): ?int
    {
        return $this->avgHeartRate;
    }

    public function setAvgHeartRate(?int $avgHeartRate): self
    {
        $this->avgHeartRate = $avgHeartRate;
        return $this;
    }

    public function getPeakHeartRate(): ?int
    {
        return $this->peakHeartRate;
    }

    public function setPeakHeartRate(?int $peakHeartRate): self
    {
        $this->peakHeartRate = $peakHeartRate;
        return $this;
    }

    public function getCaloriesBurned(): ?int
    {
        return $this->caloriesBurned;
    }

    public function setCaloriesBurned(?int $caloriesBurned): self
    {
        $this->caloriesBurned = $caloriesBurned;
        return $this;
    }

    public function getPerceivedExertion(): ?int
    {
        return $this->perceivedExertion;
    }

    public function setPerceivedExertion(?int $perceivedExertion): self
    {
        $this->perceivedExertion = $perceivedExertion;
        return $this;
    }

    public function getMoodRating(): ?int
    {
        return $this->moodRating;
    }

    public function setMoodRating(?int $moodRating): self
    {
        $this->moodRating = $moodRating;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getSourceTemplateId(): ?int
    {
        return $this->sourceTemplateId;
    }

    public function setSourceTemplateId(?int $sourceTemplateId): self
    {
        $this->sourceTemplateId = $sourceTemplateId;
        return $this;
    }

    /** @return Collection<int, WorkoutExercise> */
    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    // ── Virtual fields exposed in summary/performance groups ──────────────────

    #[Groups(['training_session:summary'])]
    public function getExerciseCount(): int
    {
        return $this->exercises->count();
    }

    #[Groups(['training_session:performance'])]
    public function getIntensityScore(): ?float
    {
        if ($this->totalVolumeKg === null || $this->durationSeconds === null || $this->durationSeconds === 0) {
            return null;
        }
        return round($this->totalVolumeKg / ($this->durationSeconds / 60), 2);
    }
}
