<?php

namespace App\Services;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    /**
     * Statuses this user may move the ticket to right now.
     *
     * @return list<MaintenanceStatus>
     */
    public function allowedNext(MaintenanceRequest $ticket, User $user): array
    {
        if ($user->cannot('updateStatus', $ticket)) {
            return [];
        }

        return array_values(array_filter(
            $ticket->status->next(),
            // Closing (at any stage) and reopening are landlord-only.
            // A landlord may close directly, e.g. fixed by their own contractor outside the system.
            fn (MaintenanceStatus $to) => $user->isLandlord()
                || ($to !== MaintenanceStatus::Closed && $ticket->status !== MaintenanceStatus::Resolved),
        ));
    }

    public function transition(MaintenanceRequest $ticket, User $user, MaintenanceStatus $to, ?string $note = null): void
    {
        if (! in_array($to, $this->allowedNext($ticket, $user), true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move from {$ticket->status->label()} to {$to->label()}.",
            ]);
        }

        // A note is needed when resolving, or when closing a ticket that was never resolved.
        $closingDirectly = $to === MaintenanceStatus::Closed && $ticket->status !== MaintenanceStatus::Resolved;
        $needsNote = $to === MaintenanceStatus::Resolved || $closingDirectly;

        if ($needsNote && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Add a short note on what was done.']);
        }

        $ticket->update([
            'status' => $to,
            'resolution_note' => $needsNote ? $note : $ticket->resolution_note,
            'resolved_at' => match (true) {
                $to === MaintenanceStatus::Resolved, $closingDirectly => now(),
                $to === MaintenanceStatus::InProgress => null, // reopened
                default => $ticket->resolved_at,
            },
        ]);
    }
}
