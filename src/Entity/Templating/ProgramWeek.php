<?php

namespace App\Entity\Templating;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProgramWeekRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramWeekRepository::class)]
#[ApiResource]
class ProgramWeek
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $weekIndex = null;

    #[ORM\ManyToOne(inversedBy: 'programWeeks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProgramBlock $block = null;

    /**
     * @var Collection<int, ProgramDay>
     */
    #[ORM\OneToMany(targetEntity: ProgramDay::class, mappedBy: 'week', orphanRemoval: true)]
    private Collection $programDays;

    public function __construct()
    {
        $this->programDays = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekIndex(): ?int
    {
        return $this->weekIndex;
    }

    public function setWeekIndex(int $weekIndex): static
    {
        $this->weekIndex = $weekIndex;

        return $this;
    }

    public function getBlock(): ?ProgramBlock
    {
        return $this->block;
    }

    public function setBlock(?ProgramBlock $block): static
    {
        $this->block = $block;

        return $this;
    }

    /**
     * @return Collection<int, ProgramDay>
     */
    public function getProgramDays(): Collection
    {
        return $this->programDays;
    }

    public function addProgramDay(ProgramDay $programDay): static
    {
        if (!$this->programDays->contains($programDay)) {
            $this->programDays->add($programDay);
            $programDay->setWeek($this);
        }

        return $this;
    }

    public function removeProgramDay(ProgramDay $programDay): static
    {
        if ($this->programDays->removeElement($programDay)) {
            // set the owning side to null (unless already changed)
            if ($programDay->getWeek() === $this) {
                $programDay->setWeek(null);
            }
        }

        return $this;
    }
}
