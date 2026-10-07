<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\TicketData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Maintenance staff: tickets assigned to me. */
class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $staff */
        $staff = $request->user();

        return Inertia::render('tasks/index', [
            'tickets' => $staff->assignedRequests()
                ->with(['unit.property', 'tenant', 'assignee'])
                ->workOrder()
                ->get()
                ->map(fn (MaintenanceRequest $t) => TicketData::row($t)),
        ]);
    }
}
