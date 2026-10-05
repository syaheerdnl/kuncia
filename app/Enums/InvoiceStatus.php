<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum InvoiceStatus: string
{
    use EnumHelpers;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
