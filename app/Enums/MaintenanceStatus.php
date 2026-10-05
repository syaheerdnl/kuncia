<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MaintenanceStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
