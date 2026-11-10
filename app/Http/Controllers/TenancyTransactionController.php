<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\Property\StoreTransactionRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\TenancyTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Deposits received and money paid out to a tenant, shown on the property timeline. */
class TenancyTransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, Property $property): RedirectResponse
    {
        $data = $request->validated();

        Tenancy::findOrFail($request->integer('tenancy_id'))->transactions()->create([
            'type' => $data['type'],
            'amount' => $data['amount'],
            'occurred_on' => $data['occurred_on'],
            'note' => $data['note'] ?? null,
        ]);

        $message = match (TransactionType::from($data['type'])) {
            TransactionType::DepositReceived => 'Deposit recorded.',
            TransactionType::PaidToTenant => 'Payment to tenant recorded.',
        };
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function destroy(TenancyTransaction $transaction): RedirectResponse
    {
        Gate::authorize('update', $transaction->tenancy->unit->property);

        $transaction->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Entry deleted.']);

        return back();
    }
}
