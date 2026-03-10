<?php

namespace App\Entity\Templating;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ProgramDayRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgramDayRepository::class)]
#[ApiResource]
class ProgramDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $dayIndex = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'programDays')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProgramWeek $week = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDayIndex(): ?int
    {
        return $this->dayIndex;
    }

    public function setDayIndex(int $dayIndex): static
    {
        $this->dayIndex = $dayIndex;

        return $this;
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

    public function getWeek(): ?ProgramWeek
    {
        return $this->week;
    }

    public function setWeek(?ProgramWeek $week): static
    {
        $this->week = $week;

        return $this;
    }
}
