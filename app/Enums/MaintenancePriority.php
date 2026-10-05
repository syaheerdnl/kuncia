<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MaintenancePriority: string
{
    use EnumHelpers;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
