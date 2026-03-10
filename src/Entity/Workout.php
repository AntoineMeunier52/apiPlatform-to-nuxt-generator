<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\QueryParameter;
use App\Enum\WorkoutTypeEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['workout:list']],
            parameters: [
                'name' => new QueryParameter(filter: 'workout.search_filter'),
                'user' => new QueryParameter(filter: 'workout.search_filter'),
                'type' => new QueryParameter(filter: 'workout.search_filter'),
                'date[before]' => new QueryParameter(filter: 'workout.date_filter'),
                'date[strictly_before]' => new QueryParameter(filter: 'workout.date_filter'),
                'date[after]' => new QueryParameter(filter: 'workout.date_filter'),
                'date[strictly_after]' => new QueryParameter(filter: 'workout.date_filter'),
                'order[date]' => new QueryParameter(filter: 'workout.order_filter'),
                'order[name]' => new QueryParameter(filter: 'workout.order_filter'),
                'order[duration]' => new QueryParameter(filter: 'workout.order_filter'),
            ],
        ),
        new Get(
            normalizationContext: ['groups' => ['workout:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['workout:create']],
            normalizationContext: ['groups' => ['workout:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['workout:update']],
            normalizationContext: ['groups' => ['workout:read']],
        ),
        new Delete(),
    ]
)]
class Workout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['workout:list', 'workout:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    #[Groups(['workout:list', 'workout:read', 'workout:create', 'workout:update', 'user:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', enumType: WorkoutTypeEnum::class)]
    #[Groups(['workout:list', 'workout:read', 'workout:create', 'workout:update'])]
    private ?WorkoutTypeEnum $type = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['workout:list', 'workout:read', 'workout:create', 'workout:update'])]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['workout:read', 'workout:create', 'workout:update'])]
    private ?int $duration = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['workout:read', 'workout:create', 'workout:update'])]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'workouts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['workout:list', 'workout:read', 'workout:create'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: TrainingPlan::class, inversedBy: 'workouts')]
    #[Groups(['workout:read', 'workout:create', 'workout:update'])]
    private ?TrainingPlan $trainingPlan = null;

    /**
     * @var Collection<int, WorkoutExercise>
     */
    #[ORM\OneToMany(mappedBy: 'workout', targetEntity: WorkoutExercise::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['workout:read'])]
    private Collection $exercises;

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
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

    public function getType(): ?WorkoutTypeEnum
    {
        return $this->type;
    }

    public function setType(WorkoutTypeEnum $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->trainingPlan;
    }

    public function setTrainingPlan(?TrainingPlan $trainingPlan): self
    {
        $this->trainingPlan = $trainingPlan;
        return $this;
    }

    public function getExercises(): Collection
    {
        return $this->exercises;
    }

    public function addExercise(WorkoutExercise $exercise): self
    {
        if (!$this->exercises->contains($exercise)) {
            $this->exercises[] = $exercise;
            $exercise->setWorkout($this);
        }
        return $this;
    }
}
