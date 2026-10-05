<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PaymentMethod: string
{
    use EnumHelpers;

    case ToyyibPay = 'toyyibpay';
    case Cash = 'cash';
    case Transfer = 'transfer';
}
