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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['user:list']],
            parameters: [
                'email' => new QueryParameter(filter: 'user.search_filter'),
                'firstName' => new QueryParameter(filter: 'user.search_filter'),
                'lastName' => new QueryParameter(filter: 'user.search_filter'),
                'createdAt[before]' => new QueryParameter(filter: 'user.date_filter'),
                'createdAt[strictly_before]' => new QueryParameter(filter: 'user.date_filter'),
                'createdAt[after]' => new QueryParameter(filter: 'user.date_filter'),
                'createdAt[strictly_after]' => new QueryParameter(filter: 'user.date_filter'),
                'order[createdAt]' => new QueryParameter(filter: 'user.order_filter'),
                'order[email]' => new QueryParameter(filter: 'user.order_filter'),
                'order[firstName]' => new QueryParameter(filter: 'user.order_filter'),
                'order[lastName]' => new QueryParameter(filter: 'user.order_filter'),
            ],
        ),
        new Get(
            normalizationContext: ['groups' => ['user:read']],
        ),
        new Post(
            denormalizationContext: ['groups' => ['user:create']],
            normalizationContext: ['groups' => ['user:read']],
        ),
        new Patch(
            denormalizationContext: ['groups' => ['user:update']],
            normalizationContext: ['groups' => ['user:read']],
        ),
        new Delete(),
    ]
)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:list', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:list', 'user:read', 'user:create', 'user:update'])]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['user:list', 'user:read', 'user:create', 'user:update'])]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['user:list', 'user:read', 'user:create', 'user:update'])]
    private ?string $lastName = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:list', 'user:read', 'user:update'])]
    private bool $isActive = true;

    /**
     * @var Collection<int, Workout>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Workout::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $workouts;

    /**
     * @var Collection<int, TrainingPlan>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TrainingPlan::class, cascade: ['persist', 'remove'])]
    #[Groups(['user:read'])]
    private Collection $trainingPlans;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->workouts = new ArrayCollection();
        $this->trainingPlans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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

    public function getWorkouts(): Collection
    {
        return $this->workouts;
    }

    public function getTrainingPlans(): Collection
    {
        return $this->trainingPlans;
    }
}
