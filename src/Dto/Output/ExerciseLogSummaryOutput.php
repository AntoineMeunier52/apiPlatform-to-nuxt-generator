<?php

declare(strict_types=1);

namespace App\Dto\Output;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO output class for ExerciseLog summary.
 * Has explicit serialization groups - used to test group-based filtering on DTOs.
 * Used with explicit output: ExerciseLogSummaryOutput::class + normalizationContext groups.
 */
final class ExerciseLogSummaryOutput
{
    #[Groups(['exercise_log:summary'])]
    public int $exerciseLogId = 0;

    #[Groups(['exercise_log:summary'])]
    public string $exerciseName = '';

    #[Groups(['exercise_log:summary'])]
    public int $totalSets = 0;

    #[Groups(['exercise_log:summary'])]
    public int $totalReps = 0;

    #[Groups(['exercise_log:summary', 'exercise_log:summary_extended'])]
    public float $totalVolumeKg = 0.0;

    /** Only in extended summary */
    #[Groups(['exercise_log:summary_extended'])]
    public ?float $estimatedOneRepMax = null;

    /** Only in extended summary */
    #[Groups(['exercise_log:summary_extended'])]
    public ?string $progressSinceLastSession = null;
}
