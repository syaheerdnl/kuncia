<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
