<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\MaintenanceRequest;

/** Shapes maintenance tickets for Inertia pages. */
class TicketData
{
    /** @return array<string, mixed> */
    public static function row(MaintenanceRequest $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'priority' => $t->priority->value,
            'status' => $t->status->value,
            'unit' => $t->unit->property->name.' · '.$t->unit->code,
            'tenant' => $t->tenant->name,
            'assignee' => $t->assignee?->name,
            'created_at' => $t->created_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(MaintenanceRequest $t): array
    {
        return [
            ...self::row($t),
            'description' => $t->description,
            'assigned_to' => $t->assigned_to,
            'resolution_note' => $t->resolution_note,
            'resolved_at' => $t->resolved_at?->toDateTimeString(),
            'photos' => $t->attachments->map(fn (Attachment $a) => [
                'id' => $a->id,
                'url' => route('attachments.show', $a),
                'name' => $a->original_name,
            ]),
        ];
    }
}
