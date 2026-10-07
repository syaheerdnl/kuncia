<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreTicketRequest;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\TicketData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MyMaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $tenant */
        $tenant = $request->user();
        $tenancy = $tenant->activeTenancy()->with('unit.property')->first();

        return Inertia::render('my/maintenance', [
            'tickets' => $tenant->maintenanceRequests()
                ->with(['unit.property', 'tenant', 'assignee'])
                ->workOrder()
                ->get()
                ->map(fn (MaintenanceRequest $t) => TicketData::row($t)),
            'unit' => $tenancy ? $tenancy->unit->property->name.' · '.$tenancy->unit->code : null,
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        /** @var User $tenant */
        $tenant = $request->user();
        $tenancy = $tenant->activeTenancy()->first();

        if (! $tenancy) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'You need an active tenancy to report a problem.']);

            return back();
        }

        $ticket = DB::transaction(function () use ($request, $tenant, $tenancy) {
            $ticket = MaintenanceRequest::create([
                ...$request->safe()->only(['title', 'description', 'priority']),
                'unit_id' => $tenancy->unit_id,
                'tenant_id' => $tenant->id,
                'status' => MaintenanceStatus::Open,
            ]);

            $photos = $request->file('photos');

            /** @var list<UploadedFile> $files */
            $files = is_array($photos) ? $photos : [];

            foreach ($files as $photo) {
                $ticket->attachments()->create([
                    // private disk: served only through AttachmentController
                    'path' => $photo->store('maintenance', 'local'),
                    'original_name' => $photo->getClientOriginalName(),
                ]);
            }

            return $ticket;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request sent to your landlord.']);

        return to_route('maintenance.show', $ticket);
    }
}
