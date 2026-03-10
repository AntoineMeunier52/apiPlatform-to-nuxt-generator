<?php

declare(strict_types=1);

namespace App\Dto\Output;

/**
 * DTO output class for ExerciseLog analytics.
 * No serialization groups - all properties should be exposed by default.
 * Used with explicit output: ExerciseLogAnalyticsOutput::class on the operation.
 */
final class ExerciseLogAnalyticsOutput
{
    public int $totalSets = 0;
    public int $totalReps = 0;
    public float $totalVolumeKg = 0.0;
    public float $averageRepsPerSet = 0.0;
    public float $maxWeightKg = 0.0;
    public float $progressRate = 0.0;
    public ?string $trend = null;
}
