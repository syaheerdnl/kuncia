<?php

namespace App\Http\Controllers;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\UserRole;
use App\Http\Requests\Maintenance\StoreStaffRequest;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Support\TicketData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Landlord side + the shared ticket page. */
class MaintenanceController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    public function index(Request $request): Response
    {
        /** @var User $landlord */
        $landlord = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MaintenanceStatus::class)],
            'priority' => ['nullable', Rule::enum(MaintenancePriority::class)],
            'property' => ['nullable', 'integer'],
        ]);

        $tickets = MaintenanceRequest::forLandlord($landlord)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['property'] ?? null, fn ($q, $p) => $q->whereHas('unit', fn ($u) => $u->where('property_id', $p)))
            ->with(['unit.property', 'tenant', 'assignee'])
            ->workOrder()
            ->get()
            ->map(fn (MaintenanceRequest $t) => TicketData::row($t));

        return Inertia::render('maintenance/index', [
            'tickets' => $tickets,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'priority' => $filters['priority'] ?? '',
                'property' => isset($filters['property']) ? (string) $filters['property'] : '',
            ],
            'properties' => $landlord->properties()->orderBy('name')->get(['id', 'name']),
            'staff' => $landlord->staff()->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'statuses' => MaintenanceStatus::options(),
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    /** Shared ticket page for landlord, tenant and assigned staff. */
    public function show(Request $request, MaintenanceRequest $ticket): Response
    {
        Gate::authorize('view', $ticket);

        /** @var User $user */
        $user = $request->user();
        $ticket->load(['unit.property', 'tenant', 'assignee', 'attachments']);
        $canAssign = $user->can('assign', $ticket);

        return Inertia::render('maintenance/show', [
            'ticket' => TicketData::detail($ticket),
            'nextStatuses' => array_map(
                fn (MaintenanceStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                $this->maintenance->allowedNext($ticket, $user),
            ),
            'canAssign' => $canAssign,
            'staff' => $canAssign
                ? $user->staff()->orderBy('name')->get(['id', 'name'])
                : [],
            'priorities' => MaintenancePriority::options(),
        ]);
    }

    public function assign(Request $request, MaintenanceRequest $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        /** @var User $landlord */
        $landlord = $request->user();

        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('landlord_id', $landlord->id)->where('role', UserRole::Maintenance->value)],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
        ]);

        $ticket->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ticket updated.']);

        return back();
    }

    public function updateStatus(Request $request, MaintenanceRequest $ticket): RedirectResponse
    {
        Gate::authorize('view', $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $this->maintenance->transition($ticket, $user, MaintenanceStatus::from((string) $data['status']), $data['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Status updated.']);

        return back();
    }

    public function storeStaff(StoreStaffRequest $request): RedirectResponse
    {
        /** @var User $landlord */
        $landlord = $request->user();

        $staff = User::create([
            ...$request->validated(),
            'role' => UserRole::Maintenance,
            'landlord_id' => $landlord->id,
            'password' => Str::password(24),
        ]);
        $staff->forceFill(['email_verified_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$staff->name} added as maintenance staff."]);

        return back();
    }
}
