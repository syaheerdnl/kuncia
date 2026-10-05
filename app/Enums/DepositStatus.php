<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum DepositStatus: string
{
    use EnumHelpers;

    case Held = 'held';
    case Refunded = 'refunded';
    case Forfeited = 'forfeited';
}
