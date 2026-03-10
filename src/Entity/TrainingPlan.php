<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\QueryParameter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['plan:list']],
            parameters: [
                'name' => new QueryParameter(filter: 'plan.search_filter'),
                'user' => new QueryParameter(filter: 'plan.search_filter'),
                'isActive' => new QueryParameter(filter: 'plan.boolean_filter'),
                'order[createdAt]' => new QueryParameter(filter: 'plan.order_filter'),
                'order[name]' => new QueryParameter(filter: 'plan.order_filter'),
                'order[durationWeeks]' => new QueryParameter(filter: 'plan.order_filter'),
            ],
        ),
        new Get(
            normalizationContext: ['groups' => ['plan:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['plan:create']],
            normalizationContext: ['groups' => ['plan:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['plan:update']],
            normalizationContext: ['groups' => ['plan:read']],
        ),
        new Put(
            denormalizationContext: ['groups' => ['plan:replace']],
            normalizationContext: ['groups' => ['plan:read']],
        ),
        new Delete(),
    ]
)]
class TrainingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['plan:list', 'plan:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    #[Groups(['plan:list', 'plan:read', 'plan:create', 'plan:update', 'plan:replace', 'user:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['plan:read', 'plan:create', 'plan:update', 'plan:replace'])]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['plan:list', 'plan:read', 'plan:create', 'plan:update', 'plan:replace'])]
    private int $durationWeeks = 4;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['plan:list', 'plan:read', 'plan:update', 'plan:replace'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['plan:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trainingPlans')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['plan:list', 'plan:read', 'plan:create', 'plan:replace'])]
    private ?User $user = null;

    /**
     * @var Collection<int, Workout>
     */
    #[ORM\OneToMany(mappedBy: 'trainingPlan', targetEntity: Workout::class)]
    #[Groups(['plan:read'])]
    private Collection $workouts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->workouts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDurationWeeks(): int
    {
        return $this->durationWeeks;
    }

    public function setDurationWeeks(int $durationWeeks): self
    {
        $this->durationWeeks = $durationWeeks;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getWorkouts(): Collection
    {
        return $this->workouts;
    }
}
