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

    /**
     * Possible next steps. Who may take each one is decided in MaintenanceService
     * (closing and reopening are landlord-only).
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Closed],
            self::InProgress => [self::Resolved, self::Closed],
            self::Resolved => [self::Closed, self::InProgress], // close, or reopen
            self::Closed => [],
        };
    }
}
