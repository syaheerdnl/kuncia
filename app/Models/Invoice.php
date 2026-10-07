<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenancy_id
 * @property string $invoice_no
 * @property CarbonImmutable $period
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $due_date
 * @property string $total
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $paid_at
 */
#[Fillable(['tenancy_id', 'invoice_no', 'period', 'issue_date', 'due_date', 'total', 'status', 'paid_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'total' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Invoices for units owned by this landlord.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeForLandlord(Builder $query, User $landlord): void
    {
        $query->whereHas('tenancy.unit.property', fn ($q) => $q->where('owner_id', $landlord->id));
    }

    /** Next running number for a month, e.g. INV-202610-0001. */
    public static function nextNumber(CarbonInterface $period): string
    {
        $prefix = 'INV-'.$period->format('Ym').'-';
        $last = static::where('invoice_no', 'like', $prefix.'%')->max('invoice_no');
        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function recalculateTotal(): void
    {
        $this->total = (string) $this->items()->sum('amount');
        $this->save();
    }

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
