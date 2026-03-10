<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\GoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Goal entity tests:
 * - Self-referential ManyToOne (parent goal → child goals)
 * - Self-referential OneToMany (children collection)
 * - Multiple enum-like string fields
 * - All standard CRUD + toggle operations
 */
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['goal:list']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['goal:create']],
            normalizationContext: ['groups' => ['goal:read']],
        ),
        new Get(
            normalizationContext: ['groups' => ['goal:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['goal:update']],
            normalizationContext: ['groups' => ['goal:read']],
        ),
        new Delete(),

        // Toggle achieved status (signal-only)
        new Post(
            uriTemplate: '/api/goals/{id}/achieve',
            name: 'goal_achieve',
        ),

        // Reopen goal (signal-only)
        new Post(
            uriTemplate: '/api/goals/{id}/reopen',
            name: 'goal_reopen',
        ),

        // Get goal tree (goal + all descendants)
        new Get(
            uriTemplate: '/api/goals/{id}/tree',
            name: 'goal_tree',
            normalizationContext: ['groups' => ['goal:tree']],
        ),
    ]
)]
#[ORM\Entity(repositoryClass: GoalRepository::class)]
class Goal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['goal:list', 'goal:read', 'goal:tree'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['goal:list', 'goal:read', 'goal:tree', 'goal:create', 'goal:update'])]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['goal:read', 'goal:create', 'goal:update'])]
    private ?string $description = null;

    #[ORM\Column(length: 50, options: ['default' => 'active'])]
    #[Groups(['goal:list', 'goal:read', 'goal:tree'])]
    private string $status = 'active';

    #[ORM\Column(length: 50)]
    #[Groups(['goal:list', 'goal:read', 'goal:tree', 'goal:create'])]
    private string $type = 'fitness';

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['goal:read', 'goal:create', 'goal:update'])]
    private ?float $targetValue = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['goal:read', 'goal:update'])]
    private ?float $currentValue = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['goal:list', 'goal:read', 'goal:create', 'goal:update'])]
    private ?\DateTimeImmutable $targetDate = null;

    /**
     * Self-referential: parent goal (optional)
     * Types as IRI string (groups don't match) in most contexts.
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[Groups(['goal:read', 'goal:create'])]
    private ?self $parent = null;

    /**
     * Self-referential: child goals collection
     * Groups include 'goal:tree' so in tree context these are inlined as objects.
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[Groups(['goal:tree'])]
    private Collection $children;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTargetValue(): ?float
    {
        return $this->targetValue;
    }

    public function setTargetValue(?float $targetValue): static
    {
        $this->targetValue = $targetValue;
        return $this;
    }

    public function getCurrentValue(): ?float
    {
        return $this->currentValue;
    }

    public function setCurrentValue(?float $currentValue): static
    {
        $this->currentValue = $currentValue;
        return $this;
    }

    public function getTargetDate(): ?\DateTimeImmutable
    {
        return $this->targetDate;
    }

    public function setTargetDate(?\DateTimeImmutable $targetDate): static
    {
        $this->targetDate = $targetDate;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }
}
