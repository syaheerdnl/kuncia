<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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

    /** @return BelongsTo<Tenancy, $this> */
    public function tenancy(): BelongsTo
    {
        return $this->belongsTo(Tenancy::class);
    }
}
