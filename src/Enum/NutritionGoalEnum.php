<?php

declare(strict_types=1);

namespace App\Enum;

enum NutritionGoalEnum: string
{
    case WEIGHT_LOSS = 'weight_loss';
    case MUSCLE_GAIN = 'muscle_gain';
    case MAINTENANCE = 'maintenance';
    case PERFORMANCE = 'performance';
}
