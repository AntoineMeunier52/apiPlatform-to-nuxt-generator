<?php

namespace App\Entity\Templating;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProgramBlockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramBlockRepository::class)]
#[ApiResource]
class ProgramBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $orderIndex = null;

    #[ORM\Column]
    private ?int $durationWeeks = null;

    #[ORM\ManyToOne(inversedBy: 'programBlocks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Program $program = null;

    /**
     * @var Collection<int, ProgramWeek>
     */
    #[ORM\OneToMany(targetEntity: ProgramWeek::class, mappedBy: 'block', orphanRemoval: true)]
    private Collection $programWeeks;

    public function __construct()
    {
        $this->programWeeks = new ArrayCollection();
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

    public function getOrderIndex(): ?int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;

        return $this;
    }

    public function getDurationWeeks(): ?int
    {
        return $this->durationWeeks;
    }

    public function setDurationWeeks(int $durationWeeks): static
    {
        $this->durationWeeks = $durationWeeks;

        return $this;
    }

    public function getProgram(): ?Program
    {
        return $this->program;
    }

    public function setProgram(?Program $program): static
    {
        $this->program = $program;

        return $this;
    }

    /**
     * @return Collection<int, ProgramWeek>
     */
    public function getProgramWeeks(): Collection
    {
        return $this->programWeeks;
    }

    public function addProgramWeek(ProgramWeek $programWeek): static
    {
        if (!$this->programWeeks->contains($programWeek)) {
            $this->programWeeks->add($programWeek);
            $programWeek->setBlock($this);
        }

        return $this;
    }

    public function removeProgramWeek(ProgramWeek $programWeek): static
    {
        if ($this->programWeeks->removeElement($programWeek)) {
            // set the owning side to null (unless already changed)
            if ($programWeek->getBlock() === $this) {
                $programWeek->setBlock(null);
            }
        }

        return $this;
    }
}
