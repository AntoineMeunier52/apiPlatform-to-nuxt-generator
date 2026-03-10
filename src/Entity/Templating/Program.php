<?php

namespace App\Entity\Templating;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProgramRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramRepository::class)]
#[ApiResource]
class Program
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $goal = null;

    #[ORM\Column]
    private ?bool $isActive = false;

    /**
     * @var Collection<int, ProgramBlock>
     */
    #[ORM\OneToMany(targetEntity: ProgramBlock::class, mappedBy: 'program', orphanRemoval: true)]
    private Collection $programBlocks;

    public function __construct()
    {
        $this->programBlocks = new ArrayCollection();
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

    public function getGoal(): ?string
    {
        return $this->goal;
    }

    public function setGoal(?string $goal): static
    {
        $this->goal = $goal;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return Collection<int, ProgramBlock>
     */
    public function getProgramBlocks(): Collection
    {
        return $this->programBlocks;
    }

    public function addProgramBlock(ProgramBlock $programBlock): static
    {
        if (!$this->programBlocks->contains($programBlock)) {
            $this->programBlocks->add($programBlock);
            $programBlock->setProgram($this);
        }

        return $this;
    }

    public function removeProgramBlock(ProgramBlock $programBlock): static
    {
        if ($this->programBlocks->removeElement($programBlock)) {
            // set the owning side to null (unless already changed)
            if ($programBlock->getProgram() === $this) {
                $programBlock->setProgram(null);
            }
        }

        return $this;
    }
}
