<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Http\Requests\Tenancy\StoreTenantRequest;
use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $landlord */
        $landlord = $request->user();
        $search = trim((string) $request->string('q'));

        $tenants = $landlord->tenants()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->with('activeTenancy.unit.property')
            ->withSum(['invoices as outstanding' => fn ($q) => $q->whereIn('invoices.status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue])], 'total')
            ->orderBy('name')
            ->get()
            ->map(fn (User $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'phone' => $t->phone,
                'unit' => $t->activeTenancy
                    ? $t->activeTenancy->unit->property->name.' · '.$t->activeTenancy->unit->code
                    : null,
                'outstanding' => $t->outstanding ?? '0',
            ]);

        return Inertia::render('tenants/index', [
            'tenants' => $tenants,
            'filters' => ['q' => $search],
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        /** @var User $landlord */
        $landlord = $request->user();

        $tenant = User::create([
            ...$request->validated(),
            'role' => UserRole::Tenant,
            'landlord_id' => $landlord->id,
            // Tenant sets their own password later via "Forgot password".
            'password' => Str::password(24),
        ]);
        $tenant->forceFill(['email_verified_at' => now()])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$tenant->name} added."]);

        return to_route('tenants.show', $tenant);
    }

    public function show(User $tenant): Response
    {
        Gate::authorize('manage-tenant', $tenant);

        $tenancies = $tenant->tenancies()->with('unit.property')->latest('start_date')->get();
        $invoices = $tenant->invoices()->latest('period')->limit(12)->get();

        return Inertia::render('tenants/show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'joined' => $tenant->created_at?->toDateString(),
            ],
            'tenancies' => $tenancies->map(fn (Tenancy $t) => [
                'id' => $t->id,
                'property' => $t->unit->property->name,
                'unit' => $t->unit->code,
                'start_date' => $t->start_date->toDateString(),
                'end_date' => $t->end_date?->toDateString(),
                'monthly_rent' => $t->monthly_rent,
                'deposit_amount' => $t->deposit_amount,
                'deposit_status' => $t->deposit_status->label(),
                'due_day' => $t->due_day,
                'status' => $t->status->value,
            ]),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_no' => $i->invoice_no,
                'period' => $i->period->format('M Y'),
                'due_date' => $i->due_date->toDateString(),
                'total' => $i->total,
                'status' => $i->status->value,
            ]),
        ]);
    }
}
