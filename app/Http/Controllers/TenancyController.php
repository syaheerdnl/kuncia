<?php

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Enums\UnitStatus;
use App\Http\Requests\Tenancy\EndTenancyRequest;
use App\Http\Requests\Tenancy\StoreTenancyRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Services\TenancyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TenancyController extends Controller
{
    public function __construct(private TenancyService $tenancies) {}

    public function create(Request $request): Response
    {
        Gate::authorize('create', Tenancy::class);

        /** @var User $landlord */
        $landlord = $request->user();

        $tenants = $landlord->tenants()
            ->whereDoesntHave('activeTenancy')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $properties = $landlord->properties()
            ->with(['units' => fn ($q) => $q->where('status', UnitStatus::Vacant)->orderBy('code')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Property $p) => $p->units->isNotEmpty())
            ->values()
            ->map(fn (Property $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'units' => $p->units->map(fn (Unit $u) => [
                    'id' => $u->id,
                    'code' => $u->code,
                    'monthly_rent' => $u->monthly_rent,
                    'deposit' => $u->deposit,
                ]),
            ]);

        return Inertia::render('tenancies/create', [
            'tenants' => $tenants,
            'properties' => $properties,
            'preselectTenant' => $request->integer('tenant') ?: null,
        ]);
    }

    public function store(StoreTenancyRequest $request): RedirectResponse
    {
        $tenancy = $this->tenancies->start($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tenancy started. Unit is now occupied.']);

        return to_route('tenants.show', $tenancy->tenant_id);
    }

    public function end(EndTenancyRequest $request, Tenancy $tenancy): RedirectResponse
    {
        $this->tenancies->end(
            $tenancy,
            CarbonImmutable::parse((string) $request->validated('end_date')),
            DepositStatus::from((string) $request->validated('deposit_status')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tenancy ended. Unit is vacant again.']);

        return back();
    }

    /** Landlord or the tenant can download the agreement. */
    public function agreement(Tenancy $tenancy): HttpResponse
    {
        Gate::authorize('view', $tenancy);

        $tenancy->load(['unit.property.owner', 'tenant']);

        return Pdf::loadView('pdf.tenancy-agreement', ['tenancy' => $tenancy])
            ->download("tenancy-agreement-{$tenancy->id}.pdf");
    }
}
