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
use App\Dto\Input\ExerciseLogBulkInput;
use App\Dto\Output\ExerciseLogAnalyticsOutput;
use App\Dto\Output\ExerciseLogSummaryOutput;
use App\Repository\ExerciseLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ExerciseLog entity for testing:
 * - Explicit output DTO classes (no groups) → tests type naming from DTO class name
 * - Explicit input DTO class (no groups) → tests property extraction without groups
 * - Explicit output DTO class (with groups) → tests group-based filtering on DTOs
 * - State machine pattern with status field
 */
#[ApiResource(
    normalizationContext: ['groups' => ['exercise_log:read']],
    denormalizationContext: ['groups' => ['exercise_log:create']],
    operations: [
        // Standard CRUD
        new GetCollection(
            normalizationContext: ['groups' => ['exercise_log:list']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['exercise_log:create']],
        ),
        new Get(
            normalizationContext: ['groups' => ['exercise_log:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['exercise_log:update']],
        ),
        new Put(
            denormalizationContext: ['groups' => ['exercise_log:replace']],
        ),
        new Delete(),

        // Custom GET: returns explicit output DTO (no groups on DTO)
        // Expected: output type name = ExerciseLogAnalyticsOutput (from DTO class name)
        new Get(
            uriTemplate: '/api/exercise_logs/{id}/analytics',
            name: 'exercise_log_analytics',
            output: ExerciseLogAnalyticsOutput::class,
            // No normalizationContext → DTO has no groups → all properties exposed
        ),

        // Custom GET: returns explicit output DTO (with groups)
        // Expected: output type name = ExerciseLogSummaryOutput (from DTO class name)
        new Get(
            uriTemplate: '/api/exercise_logs/{id}/summary',
            name: 'exercise_log_summary',
            output: ExerciseLogSummaryOutput::class,
            normalizationContext: ['groups' => ['exercise_log:summary']],
        ),

        // Custom GET collection: returns explicit output DTO with extended groups
        // Expected: output type name = ExerciseLogSummaryOutput (same DTO, different groups)
        new GetCollection(
            uriTemplate: '/api/exercise_logs/summaries',
            name: 'exercise_log_summaries',
            output: ExerciseLogSummaryOutput::class,
            normalizationContext: ['groups' => ['exercise_log:summary', 'exercise_log:summary_extended']],
        ),

        // Custom POST: explicit input DTO (no groups on DTO)
        // Expected: input type name = ExerciseLogBulkInput (from DTO class name)
        new Post(
            uriTemplate: '/api/exercise_logs/bulk',
            name: 'exercise_log_bulk_create',
            input: ExerciseLogBulkInput::class,
            // No denormalizationContext → DTO has no groups → all properties accepted
        ),

        // State transitions (signal-only posts, no body)
        new Post(
            uriTemplate: '/api/exercise_logs/{id}/complete',
            name: 'exercise_log_complete',
        ),
        new Post(
            uriTemplate: '/api/exercise_logs/{id}/reset',
            name: 'exercise_log_reset',
        ),
    ]
)]
#[ORM\Entity(repositoryClass: ExerciseLogRepository::class)]
class ExerciseLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['exercise_log:list', 'exercise_log:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:replace'])]
    private string $exerciseName = '';

    #[ORM\Column]
    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:update', 'exercise_log:replace'])]
    private int $sets = 1;

    #[ORM\Column]
    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:update', 'exercise_log:replace'])]
    private int $reps = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['exercise_log:list', 'exercise_log:read', 'exercise_log:create', 'exercise_log:update', 'exercise_log:replace'])]
    private ?float $weightKg = null;

    #[ORM\Column(length: 50, options: ['default' => 'pending'])]
    #[Groups(['exercise_log:list', 'exercise_log:read'])]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['exercise_log:read', 'exercise_log:create', 'exercise_log:update', 'exercise_log:replace'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['exercise_log:list', 'exercise_log:read'])]
    private \DateTimeImmutable $loggedAt;

    public function __construct()
    {
        $this->loggedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExerciseName(): string
    {
        return $this->exerciseName;
    }

    public function setExerciseName(string $exerciseName): static
    {
        $this->exerciseName = $exerciseName;
        return $this;
    }

    public function getSets(): int
    {
        return $this->sets;
    }

    public function setSets(int $sets): static
    {
        $this->sets = $sets;
        return $this;
    }

    public function getReps(): int
    {
        return $this->reps;
    }

    public function setReps(int $reps): static
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
    }

    public function setWeightKg(?float $weightKg): static
    {
        $this->weightKg = $weightKg;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(\DateTimeImmutable $loggedAt): static
    {
        $this->loggedAt = $loggedAt;
        return $this;
    }
}
