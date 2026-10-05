<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UnitStatus: string
{
    use EnumHelpers;

    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';
}
