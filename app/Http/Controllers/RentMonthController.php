<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\Property\StoreRentMonthRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Catch up a month whose rent was never invoiced (see RentCalendar). */
class RentMonthController extends Controller
{
    public function store(StoreRentMonthRequest $request, Property $property, InvoiceService $invoices): RedirectResponse
    {
        $tenancy = Tenancy::findOrFail($request->integer('tenancy_id'));
        $month = CarbonImmutable::parse($request->string('month')->value().'-01');

        $payment = $request->boolean('paid') ? [
            'amount' => (float) $request->input('amount'),
            'method' => PaymentMethod::from($request->string('method')->value()),
            'reference' => $request->filled('reference') ? $request->string('reference')->value() : null,
            'paid_at' => CarbonImmutable::parse($request->string('paid_at')->value()),
        ] : null;

        $invoices->createMissedMonth($tenancy, $month, $payment);

        $label = $month->format('F Y');
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $payment ? "{$label} invoice created and marked paid." : "{$label} invoice created.",
        ]);

        return back();
    }
}
