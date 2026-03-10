<?php

declare(strict_types=1);

namespace App\Dto\Output\Nutrition;

/**
 * Represents a single macronutrient breakdown.
 * NON-ENTITY DTO - used as a nested type inside MacroBreakdownOutput.
 * No @ORM\Entity, no @ApiResource → tests how generator handles nested DTO references.
 */
final class MacroItemOutput
{
    public float $grams = 0.0;
    public float $calories = 0.0;
    public float $percentage = 0.0;
}
