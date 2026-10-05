<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UnitType: string
{
    use EnumHelpers;

    case Room = 'room';
    case Bed = 'bed';
    case Whole = 'whole';
}
