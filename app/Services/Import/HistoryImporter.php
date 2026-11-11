<?php

namespace App\Services\Import;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\TenancyStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\TenancyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Loads past rent records (tenants, tenancies, paid invoices) from a JSON file.
 *
 * It goes through TenancyService and InvoiceService so the same rules apply as
 * in the app. Safe to run twice: existing tenants, tenancies and paid invoices
 * are reused. A dry run does everything inside a transaction and rolls back.
 *
 * File shape: see tests/Feature/HistoryImportTest.php.
 */
class HistoryImporter
{
    public function __construct(
        private readonly TenancyService $tenancies,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return list<string> report lines
     *
     * @throws ValidationException when the file is wrong (nothing is saved)
     */
    public function import(array $data, bool $dryRun = false): array
    {
        $data = $this->validate($data);
        $report = [];

        try {
            DB::transaction(function () use ($data, &$report, $dryRun) {
                $landlord = User::where('email', $data['landlord'])->where('role', UserRole::Landlord)->firstOrFail();

                foreach ($data['tenancies'] as $row) {
                    array_push($report, ...$this->importTenancy($landlord, $row));
                }

                if ($dryRun) {
                    throw new DryRunRollback;
                }
            });
        } catch (DryRunRollback) {
            $report[] = 'DRY RUN: nothing was saved.';
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function importTenancy(User $landlord, array $row): array
    {
        $lines = [];

        $property = Property::where('owner_id', $landlord->id)->where('name', $row['property'])->first();
        $unit = $property ? Unit::where('property_id', $property->id)->where('code', $row['unit'])->first() : null;

        if (! $property || ! $unit) {
            throw ValidationException::withMessages([
                'property' => "Property \"{$row['property']}\" with unit \"{$row['unit']}\" was not found for this landlord. Available: ".$this->available($landlord),
            ]);
        }

        $tenant = $this->tenant($landlord, $row['tenant'], $lines);
        $start = CarbonImmutable::parse((string) $row['start_date']);

        $tenancy = Tenancy::where('unit_id', $unit->id)
            ->where('tenant_id', $tenant->id)
            ->whereDate('start_date', $start)
            ->first();

        if ($tenancy) {
            $lines[] = "Tenancy exists: {$tenant->name} at {$property->name} · {$unit->code}";
        } else {
            $tenancy = $this->tenancies->start([
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_date' => $start,
                'monthly_rent' => $row['monthly_rent'],
                'deposit_amount' => $row['deposit'] ?? 0,
                'due_day' => $row['due_day'],
            ]);
            $lines[] = "Tenancy created: {$tenant->name} at {$property->name} · {$unit->code}, RM{$row['monthly_rent']}/month";
        }

        $paid = 0;
        $skipped = 0;

        foreach ($row['payments'] as $payment) {
            $this->recordMonth($tenancy, $payment) ? $paid++ : $skipped++;
        }

        $lines[] = "  {$paid} month(s) recorded as paid, {$skipped} already paid (skipped).";

        if (! empty($row['transactions'])) {
            $lines[] = $this->importTransactions($tenancy, $row['transactions']);
        }

        // A finished contract is closed last, after its months are in, which frees the unit
        // so the next contract on it can start. A tenancy that is already closed is left alone.
        if (! empty($row['end_date']) && $tenancy->status === TenancyStatus::Active) {
            $end = CarbonImmutable::parse((string) $row['end_date']);
            $this->tenancies->end($tenancy, $end, DepositStatus::from((string) ($row['deposit_status'] ?? DepositStatus::Held->value)));
            $lines[] = "  Tenancy ended on {$end->toDateString()}, unit is vacant again.";
        }

        return $lines;
    }

    /**
     * Deposits received and money paid out. A row that already exists is skipped.
     *
     * @param  list<array<string, mixed>>  $transactions
     */
    private function importTransactions(Tenancy $tenancy, array $transactions): string
    {
        $added = 0;

        foreach ($transactions as $tx) {
            $type = TransactionType::from((string) $tx['type']);

            // whereDate: a date column may be stored with a time part, so compare the day only.
            $exists = $tenancy->transactions()
                ->where('type', $type->value)
                ->whereDate('occurred_on', (string) $tx['date'])
                ->where('amount', $tx['amount'])
                ->exists();

            if ($exists) {
                continue;
            }

            $tenancy->transactions()->create([
                'type' => $type,
                'amount' => $tx['amount'],
                'occurred_on' => (string) $tx['date'],
                'note' => $tx['note'] ?? null,
            ]);
            $added++;
        }

        $skipped = count($transactions) - $added;

        return "  {$added} other transaction(s) added, {$skipped} already there (skipped).";
    }

    /** "Name [CODE1, CODE2]; Other [A]" so a wrong name or code is easy to fix. */
    private function available(User $landlord): string
    {
        $list = Property::where('owner_id', $landlord->id)->with('units:id,property_id,code')->orderBy('name')->get()
            ->map(fn (Property $p) => $p->name.' ['.$p->units->pluck('code')->implode(', ').']')
            ->implode('; ');

        return $list !== '' ? $list : '(this landlord has no properties)';
    }

    /**
     * @param  array<string, mixed>  $info
     * @param  list<string>  $lines
     */
    private function tenant(User $landlord, array $info, array &$lines): User
    {
        $existing = User::where('email', $info['email'])->first();

        if ($existing) {
            if ($existing->role !== UserRole::Tenant || $existing->landlord_id !== $landlord->id) {
                throw ValidationException::withMessages(['tenant' => "{$info['email']} already belongs to a different account."]);
            }

            return $existing;
        }

        $tenant = User::create([
            'name' => $info['name'],
            'email' => $info['email'],
            'phone' => $info['phone'],
            'role' => UserRole::Tenant,
            'landlord_id' => $landlord->id,
            // Tenant sets their own password later via "Forgot password".
            'password' => Str::password(24),
        ]);
        $tenant->forceFill(['email_verified_at' => now()])->save();
        $lines[] = "Tenant created: {$tenant->name} ({$tenant->email})";

        return $tenant;
    }

    /**
     * Returns false when that month was already paid.
     *
     * @param  array<string, mixed>  $payment
     */
    private function recordMonth(Tenancy $tenancy, array $payment): bool
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', (string) $payment['period']);
        assert($period instanceof CarbonImmutable);

        $invoice = $this->invoices->generateFor($tenancy, $period)
            ?? $tenancy->invoices()->whereDate('period', $period)->first();

        if (! $invoice) {
            throw ValidationException::withMessages([
                'period' => "{$payment['period']} is outside the tenancy dates.",
            ]);
        }

        $paidAt = CarbonImmutable::parse((string) $payment['paid_at']);

        // The timeline dates a charge by when it was added, so an imported extra gets
        // the real payment date instead of the day of the import. Repeat runs also
        // correct extras that an earlier import dated with the import day.
        if ($invoice->status === InvoiceStatus::Paid) {
            foreach ($payment['extras'] ?? [] as $extra) {
                $invoice->items()
                    ->where('description', $extra['description'])
                    ->update(['created_at' => $paidAt, 'updated_at' => $paidAt]);
            }

            return false;
        }

        // The rent can change over a tenancy (a reduction, say). A month may carry its own rent;
        // otherwise it is the tenancy's rent.
        if (isset($payment['rent'])) {
            $invoice->items()
                ->where('description', 'like', InvoiceItem::RENT_PREFIX.'%')
                ->update(['amount' => $payment['rent']]);
        }

        foreach ($payment['extras'] ?? [] as $extra) {
            if (! $invoice->items()->where('description', $extra['description'])->exists()) {
                $invoice->items()->forceCreate([
                    'description' => $extra['description'],
                    'amount' => $extra['amount'],
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]);
            }
        }
        $invoice->recalculateTotal();

        $this->assertMatches($invoice, (float) $payment['amount'], (string) $payment['period']);

        $this->invoices->recordPayment(
            $invoice,
            (float) $payment['amount'],
            PaymentMethod::from((string) ($payment['method'] ?? 'transfer')),
            $payment['reference'] ?? null,
            $paidAt,
        );

        return true;
    }

    private function assertMatches(Invoice $invoice, float $amount, string $period): void
    {
        if (round((float) $invoice->total - $amount, 2) !== 0.0) {
            throw ValidationException::withMessages([
                'amount' => "{$period}: payment RM".number_format($amount, 2)." does not match the invoice total RM{$invoice->total}. Add the difference under \"extras\".",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'landlord' => ['required', 'email', Rule::exists('users', 'email')->where('role', UserRole::Landlord->value)],
            'tenancies' => ['required', 'array', 'min:1'],
            'tenancies.*.property' => ['required', 'string'],
            'tenancies.*.unit' => ['required', 'string'],
            'tenancies.*.tenant.name' => ['required', 'string', 'max:120'],
            'tenancies.*.tenant.email' => ['required', 'email', 'max:255'],
            'tenancies.*.tenant.phone' => ['required', 'regex:/^01\d-?\d{7,8}$/'],
            'tenancies.*.start_date' => ['required', 'date_format:Y-m-d'],
            'tenancies.*.monthly_rent' => ['required', 'numeric', 'min:0.01'],
            'tenancies.*.deposit' => ['nullable', 'numeric', 'min:0'],
            'tenancies.*.due_day' => ['required', 'integer', 'between:1,31'],
            'tenancies.*.payments' => ['required', 'array', 'min:1'],
            'tenancies.*.payments.*.period' => ['required', 'date_format:Y-m'],
            'tenancies.*.payments.*.paid_at' => ['required', 'date_format:Y-m-d'],
            'tenancies.*.end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tenancies.*.start_date'],
            'tenancies.*.deposit_status' => ['nullable', Rule::in(DepositStatus::values())],
            'tenancies.*.payments.*.rent' => ['nullable', 'numeric', 'min:0.01'],
            'tenancies.*.payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenancies.*.payments.*.method' => ['nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Transfer->value])],
            'tenancies.*.payments.*.reference' => ['nullable', 'string', 'max:100'],
            'tenancies.*.payments.*.extras' => ['nullable', 'array'],
            'tenancies.*.payments.*.extras.*.description' => ['required', 'string', 'max:120'],
            'tenancies.*.payments.*.extras.*.amount' => ['required', 'numeric'],
            'tenancies.*.transactions' => ['nullable', 'array'],
            'tenancies.*.transactions.*.type' => ['required', Rule::in(TransactionType::values())],
            'tenancies.*.transactions.*.date' => ['required', 'date_format:Y-m-d'],
            'tenancies.*.transactions.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenancies.*.transactions.*.note' => ['nullable', 'string', 'max:150'],
        ]);

        $validator->after(function ($v) use ($data) {
            // A file with leftover placeholders must never be imported.
            if (str_contains((string) json_encode($data), 'TODO')) {
                $v->errors()->add('file', 'The file still contains TODO placeholders. Fill them in first.');
            }
        });

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();

        return $validated;
    }
}
