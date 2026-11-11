<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money on a tenancy that is not a rent payment: a deposit received, or money
 * the landlord paid out to the tenant (for example a repair they arranged).
 *
 * @property int $id
 * @property int $tenancy_id
 * @property TransactionType $type
 * @property string $amount
 * @property CarbonImmutable $occurred_on
 * @property string|null $note
 */
#[Fillable(['tenancy_id', 'type', 'amount', 'occurred_on', 'note'])]
class TenancyTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
        ];
    }

    /**
     * The same entry again: same kind, day, amount and note. Two deposits of RM100 on one day
     * (electricity and water) are different entries because their notes differ.
     *
     * @param  Builder<TenancyTransaction>  $query
     */
    public function scopeSameEntry(Builder $query, string $type, string $date, string|int|float $amount, ?string $note): void
    {
        $query->where('type', $type)
            // whereDate: a date column may be stored with a time part, so compare the day only.
            ->whereDate('occurred_on', $date)
            ->where('amount', $amount)
            ->when(
                $note === null || $note === '',
                fn (Builder $q) => $q->whereNull('note'),
                fn (Builder $q) => $q->where('note', $note),
            );
    }

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }
}
