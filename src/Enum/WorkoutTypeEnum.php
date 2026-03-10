<?php

namespace App\Enum;

enum WorkoutTypeEnum: string
{
    case SINGLE = 'single';
    case BISET = 'biset';
    case SUPERSET = 'superset';
    case CIRCUIT = 'circuit';
    case DROPSET = 'dropSet';
}
