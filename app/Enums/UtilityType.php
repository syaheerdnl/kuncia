<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UtilityType: string
{
    use EnumHelpers;

    case Electricity = 'electricity';
    case Water = 'water';
    case Sewerage = 'sewerage';
    case Internet = 'internet';
    case Other = 'other';
}
