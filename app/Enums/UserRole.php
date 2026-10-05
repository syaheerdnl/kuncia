<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserRole: string
{
    use EnumHelpers;

    case Landlord = 'landlord';
    case Tenant = 'tenant';
    case Maintenance = 'maintenance';
}
