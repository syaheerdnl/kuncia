<?php

namespace App\Services\Utilities;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\Tenancy;
use App\Models\UtilityBill;
use App\Models\UtilityBillShare;
use App\Models\UtilityMeter;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Splits a meter bill equally between the units that were occupied that month
 * and puts each share on the tenant's open invoice. Empty units are skipped,
 * so the occupied ones carry the whole bill. When a tenant has no open
 * invoice, the share waits and is added to their next invoice.
 */
class UtilityBillService
{
    /**
     * @param  array{path: string, name: string}|null  $file  bill photo / PDF already stored on the local disk
     */
    public function record(UtilityMeter $meter, CarbonInterface $month, float $amount, ?array $file = null): UtilityBill
    {
        $period = CarbonImmutable::parse($month)->startOfMonth();

        if ($meter->bills()->whereDate('period', $period)->exists()) {
            throw ValidationException::withMessages([
                'period' => 'This meter already has a bill for '.$period->format('F Y').'. Remove it first to re-enter.',
            ]);
        }

        return DB::transaction(function () use ($meter, $period, $amount, $file) {
            $bill = $meter->bills()->create(['period' => $period, 'amount' => $amount]);

            if ($file) {
                $bill->attachments()->create(['path' => $file['path'], 'original_name' => $file['name']]);
            }

            $tenancies = $this->tenanciesFor($meter, $period);
            $amounts = self::split($amount, $tenancies->count());

            foreach ($tenancies->values() as $i => $tenancy) {
                $share = $bill->shares()->create(['tenancy_id' => $tenancy->id, 'amount' => $amounts[$i]]);
                $invoice = $this->openInvoiceFor($tenancy);

                if ($invoice) {
                    $this->attach($share, $invoice);
                }
            }

            return $bill;
        });
    }

    /** Add waiting shares to a freshly generated invoice. */
    public function attachPending(Invoice $invoice): void
    {
        UtilityBillShare::where('tenancy_id', $invoice->tenancy_id)
            ->whereNull('invoice_item_id')
            ->get()
            ->each(fn (UtilityBillShare $share) => $this->attach($share, $invoice));
    }

    /** A voided invoice gives its shares back so the next invoice picks them up. */
    public function release(Invoice $invoice): void
    {
        UtilityBillShare::whereIn('invoice_item_id', $invoice->items()->pluck('id'))
            ->update(['invoice_item_id' => null]);
    }

    /** Remove a bill and its lines, only while no tenant has paid for it. */
    public function remove(UtilityBill $bill): void
    {
        $bill->load(['shares.invoiceItem.invoice', 'attachments']);

        $locked = $bill->shares->contains(
            fn (UtilityBillShare $s) => $s->invoiceItem && ! $s->invoiceItem->invoice->isOpenForCharges()
        );

        if ($locked) {
            throw ValidationException::withMessages([
                'bill' => 'A tenant has already paid an invoice with this bill. It can no longer be removed.',
            ]);
        }

        $paths = $bill->attachments->pluck('path');

        DB::transaction(function () use ($bill, $paths) {
            foreach ($bill->shares as $share) {
                if ($item = $share->invoiceItem) {
                    $invoice = $item->invoice;
                    $item->delete();
                    $invoice->recalculateTotal();
                    $invoice->attachments()->whereIn('path', $paths)->delete();
                }
            }

            $bill->shares()->delete();
            $bill->attachments()->delete();
            $bill->delete();
        });

        // Only delete the file when nothing else points at it.
        $paths->each(function (string $path) {
            if (! Attachment::where('path', $path)->exists()) {
                Storage::disk('local')->delete($path);
            }
        });
    }

    /**
     * Equal split in cents; leftover cents go to the first shares so the total matches.
     *
     * @return list<string>
     */
    public static function split(float $amount, int $parts): array
    {
        if ($parts < 1) {
            return [];
        }

        $cents = (int) round($amount * 100);
        $base = intdiv($cents, $parts);
        $extra = $cents - $base * $parts;

        $out = [];
        for ($i = 0; $i < $parts; $i++) {
            $out[] = number_format(($base + ($i < $extra ? 1 : 0)) / 100, 2, '.', '');
        }

        return $out;
    }

    /**
     * One tenancy per occupied unit for that month (the latest one if a unit changed hands).
     *
     * @return Collection<int, Tenancy>
     */
    private function tenanciesFor(UtilityMeter $meter, CarbonImmutable $period): Collection
    {
        $end = $period->endOfMonth();

        return Tenancy::whereIn('unit_id', $meter->units()->pluck('units.id'))
            ->whereDate('start_date', '<=', $end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $period))
            ->orderBy('start_date')
            ->get()
            ->keyBy('unit_id')
            ->sortBy('id')
            ->values();
    }

    private function openInvoiceFor(Tenancy $tenancy): ?Invoice
    {
        return $tenancy->invoices()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Overdue])
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Success))
            ->orderByDesc('period')
            ->first();
    }

    private function attach(UtilityBillShare $share, Invoice $invoice): void
    {
        $bill = $share->bill;
        $meter = $bill->meter;

        $item = $invoice->items()->create([
            'description' => mb_substr($meter->label.' ('.$bill->period->format('M Y').')', 0, 120),
            'amount' => $share->amount,
        ]);
        $share->update(['invoice_item_id' => $item->id]);
        $invoice->recalculateTotal();

        // Same bill file shown to the tenant as proof.
        foreach ($bill->attachments as $file) {
            if (! $invoice->attachments()->where('path', $file->path)->exists()) {
                $invoice->attachments()->create(['path' => $file->path, 'original_name' => $file->original_name]);
            }
        }
    }
}
