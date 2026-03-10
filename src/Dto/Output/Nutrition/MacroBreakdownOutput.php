<?php

declare(strict_types=1);

namespace App\Dto\Output\Nutrition;

/**
 * Macro breakdown for a nutrition log.
 * Output DTO with nested non-entity DTOs as properties.
 * Tests: how generator handles DTO → DTO references.
 */
final class MacroBreakdownOutput
{
    public MacroItemOutput $carbs;
    public MacroItemOutput $protein;
    public MacroItemOutput $fat;
    public float $totalCalories = 0.0;
    public float $totalGrams = 0.0;

    /** @var MacroItemOutput[] */
    public array $byMeal = [];

    public function __construct()
    {
        $this->carbs = new MacroItemOutput();
        $this->protein = new MacroItemOutput();
        $this->fat = new MacroItemOutput();
    }
}
