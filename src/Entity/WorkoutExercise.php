<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Entity\Catalog\Exercise;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['workout_exercise:list']],
        ),
        new Get(
            uriTemplate: '/workout_exercises/test/test/{id}',
            normalizationContext: ['groups' => ['workout_exercise:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['workout_exercise:create']],
            normalizationContext: ['groups' => ['workout_exercise:read']],
        ),
        new Post(
            uriTemplate: '/workout_exercises/test/test/yolo',
            denormalizationContext: ['groups' => ['workout_exercise:create']],
            normalizationContext: ['groups' => ['workout_exercise:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['workout_exercise:update']],
            normalizationContext: ['groups' => ['workout_exercise:read']],
        ),
    ]
)]
class WorkoutExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['workout_exercise:list', 'workout_exercise:read', 'workout:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workout::class, inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['workout_exercise:read', 'workout_exercise:create'])]
    private ?Workout $workout = null;

    #[ORM\ManyToOne(targetEntity: Exercise::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['workout_exercise:list', 'workout_exercise:read', 'workout_exercise:create', 'workout:read'])]
    private ?Exercise $exercise = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['workout_exercise:list', 'workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private int $sets = 3;

    #[ORM\Column(type: 'integer')]
    #[Groups(['workout_exercise:list', 'workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private int $reps = 10;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private ?float $weight = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private ?int $restSeconds = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['workout_exercise:list', 'workout_exercise:read', 'workout_exercise:create', 'workout_exercise:update', 'workout:read'])]
    private int $orderIndex = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getExercise(): ?Exercise
    {
        return $this->exercise;
    }

    public function setExercise(?Exercise $exercise): self
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getSets(): int
    {
        return $this->sets;
    }

    public function setSets(int $sets): self
    {
        $this->sets = $sets;
        return $this;
    }

    public function getReps(): int
    {
        return $this->reps;
    }

    public function setReps(int $reps): self
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): self
    {
        $this->weight = $weight;
        return $this;
    }

    public function getRestSeconds(): ?int
    {
        return $this->restSeconds;
    }

    public function setRestSeconds(?int $restSeconds): self
    {
        $this->restSeconds = $restSeconds;
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

    public function getOrderIndex(): int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): self
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    #[Groups(['workout_exercise:read', 'workout:read'])]
    public function getCoucou(): string
    {
        return 'coucou';
    }
}
