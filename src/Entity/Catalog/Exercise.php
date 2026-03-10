<?php

namespace App\Entity\Catalog;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ApiResource]
class Exercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decription = null;

    /**
     * @var Collection<int, MuscleGroup>
     */
    #[ORM\ManyToMany(targetEntity: MuscleGroup::class, mappedBy: 'exercises')]
    private Collection $muscleGroups;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'exercises')]
    private Collection $categories;

    public function __construct()
    {
        $this->muscleGroups = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDecription(): ?string
    {
        return $this->decription;
    }

    public function setDecription(?string $decription): static
    {
        $this->decription = $decription;

        return $this;
    }

    /**
     * @return Collection<int, MuscleGroup>
     */
    public function getMuscleGroups(): Collection
    {
        return $this->muscleGroups;
    }

    public function addMuscleGroup(MuscleGroup $muscleGroup): static
    {
        if (!$this->muscleGroups->contains($muscleGroup)) {
            $this->muscleGroups->add($muscleGroup);
            $muscleGroup->addExercise($this);
        }

        return $this;
    }

    public function removeMuscleGroup(MuscleGroup $muscleGroup): static
    {
        if ($this->muscleGroups->removeElement($muscleGroup)) {
            $muscleGroup->removeExercise($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addExercise($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeExercise($this);
        }

        return $this;
    }
}
