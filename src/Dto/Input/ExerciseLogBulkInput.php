<?php

declare(strict_types=1);

namespace App\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO input class for bulk exercise log creation.
 * No serialization groups - all properties should be accepted by default.
 * Used with explicit input: ExerciseLogBulkInput::class on the operation.
 */
final class ExerciseLogBulkInput
{
    /** @var int[] */
    #[Assert\NotBlank]
    public array $exerciseIds = [];

    #[Assert\NotBlank]
    public ?string $date = null;

    public ?int $workoutId = null;

    public ?string $notes = null;

    public ?bool $isWarmup = null;
}
