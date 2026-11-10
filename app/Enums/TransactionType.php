<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/** Dated money on a tenancy that is not a rent payment on an invoice. */
enum TransactionType: string
{
    use EnumHelpers;

    case DepositReceived = 'deposit_received';
    case PaidToTenant = 'paid_to_tenant';
}
