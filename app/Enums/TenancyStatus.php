<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TenancyStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Ended = 'ended';
    case Terminated = 'terminated';
}
