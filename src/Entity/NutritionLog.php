<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Input\Nutrition\NutritionLogBatchInput;
use App\Dto\Output\Nutrition\MacroBreakdownOutput;
use App\Enum\NutritionGoalEnum;
use App\Repository\NutritionLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * NutritionLog entity tests:
 * - UUID identifier (TypeScript: string, not number)
 * - Explicit output DTO with nested non-entity DTOs (MacroBreakdownOutput → MacroItemOutput)
 * - output: false (explicit no-response operation)
 * - Write-only password-style field (only in create input)
 * - Read-only computed field (only in output)
 * - Enum property with backed string enum
 * - Nullable relation (belongsTo TrainingSession, optional)
 */
#[ApiResource(
    operations: [
        // Standard CRUD with UUID path param
        new GetCollection(
            normalizationContext: ['groups' => ['nutrition_log:list']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['nutrition_log:create']],
            normalizationContext: ['groups' => ['nutrition_log:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['nutrition_log:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['nutrition_log:update']],
            normalizationContext: ['groups' => ['nutrition_log:read']],
        ),
        new Delete(),

        // Custom GET: returns nested DTO (MacroBreakdownOutput which has MacroItemOutput properties)
        // Expected: output type = MacroBreakdownOutput, with nested MacroItemOutput properties typed as any/object
        new Get(
            uriTemplate: '/api/nutrition_logs/{id}/macros',
            name: 'nutrition_log_macros',
            output: MacroBreakdownOutput::class,
            // No normalizationContext → DTO has no groups → all properties exposed
        ),

        // Custom POST: output: false → no response body (background processing, queued job)
        // Expected: function returns Promise<void>, no return type
        new Post(
            uriTemplate: '/api/nutrition_logs/batch',
            name: 'nutrition_log_batch',
            input: NutritionLogBatchInput::class,
            output: false,  // Explicitly no response body
        ),

        // Custom POST: signal-only that triggers analysis (no body, returns NutritionLogDetail)
        // Expected: no data param, returns NutritionLogDetail (entity with read groups)
        new Post(
            uriTemplate: '/api/nutrition_logs/{id}/analyze',
            name: 'nutrition_log_analyze',
            normalizationContext: ['groups' => ['nutrition_log:read']],
        ),
    ]
)]
#[ORM\Entity(repositoryClass: NutritionLogRepository::class)]
class NutritionLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['nutrition_log:list', 'nutrition_log:read'])]
    private ?Uuid $id = null;

    /** Write-only: only in create, never in output */
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['nutrition_log:list', 'nutrition_log:read', 'nutrition_log:create', 'nutrition_log:replace'])]
    private string $foodName = '';

    #[ORM\Column(type: 'float')]
    #[Groups(['nutrition_log:list', 'nutrition_log:read', 'nutrition_log:create', 'nutrition_log:update', 'nutrition_log:replace'])]
    private float $quantityGrams = 0.0;

    #[ORM\Column(type: 'float')]
    #[Groups(['nutrition_log:list', 'nutrition_log:read'])]
    private float $calories = 0.0;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['nutrition_log:read'])]
    private ?float $proteinGrams = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['nutrition_log:read'])]
    private ?float $carbGrams = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['nutrition_log:read'])]
    private ?float $fatGrams = null;

    #[ORM\Column(enumType: NutritionGoalEnum::class, nullable: true)]
    #[Groups(['nutrition_log:list', 'nutrition_log:read', 'nutrition_log:create', 'nutrition_log:update'])]
    private ?NutritionGoalEnum $goal = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['nutrition_log:list', 'nutrition_log:read', 'nutrition_log:create', 'nutrition_log:replace'])]
    private \DateTimeImmutable $logDate;

    /** Write-only: API key for external food DB, never returned */
    #[Groups(['nutrition_log:create'])]
    private ?string $externalFoodId = null;

    /** Read-only: computed nutrition score, only in detailed output */
    #[Groups(['nutrition_log:read'])]
    private ?float $nutritionScore = null;

    /** @var string[] Tags for filtering */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['nutrition_log:read', 'nutrition_log:create', 'nutrition_log:update'])]
    private array $tags = [];

    public function __construct()
    {
        $this->logDate = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFoodName(): string
    {
        return $this->foodName;
    }

    public function setFoodName(string $foodName): static
    {
        $this->foodName = $foodName;
        return $this;
    }

    public function getQuantityGrams(): float
    {
        return $this->quantityGrams;
    }

    public function setQuantityGrams(float $quantityGrams): static
    {
        $this->quantityGrams = $quantityGrams;
        return $this;
    }

    public function getCalories(): float
    {
        return $this->calories;
    }

    public function getProteinGrams(): ?float
    {
        return $this->proteinGrams;
    }

    public function getCarbGrams(): ?float
    {
        return $this->carbGrams;
    }

    public function getFatGrams(): ?float
    {
        return $this->fatGrams;
    }

    public function getGoal(): ?NutritionGoalEnum
    {
        return $this->goal;
    }

    public function setGoal(?NutritionGoalEnum $goal): static
    {
        $this->goal = $goal;
        return $this;
    }

    public function getLogDate(): \DateTimeImmutable
    {
        return $this->logDate;
    }

    public function setLogDate(\DateTimeImmutable $logDate): static
    {
        $this->logDate = $logDate;
        return $this;
    }

    public function getExternalFoodId(): ?string
    {
        return $this->externalFoodId;
    }

    public function setExternalFoodId(?string $externalFoodId): static
    {
        $this->externalFoodId = $externalFoodId;
        return $this;
    }

    /** Computed: derived from macros, never stored */
    public function getNutritionScore(): ?float
    {
        return $this->nutritionScore;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }
}
