<?php

declare(strict_types=1);

namespace App\Dto\Input\Nutrition;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input DTO for batch importing nutrition logs.
 * Tests: array of scalars, date range, nested arrays.
 */
final class NutritionLogBatchInput
{
    /**
     * @var array<array{foodId: int, quantity: float, unit: string}> Raw meal entries
     */
    #[Assert\NotBlank]
    #[Assert\Count(min: 1, max: 100)]
    public array $entries = [];

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $date = '';

    public ?string $source = null;

    public bool $overwriteExisting = false;
}
