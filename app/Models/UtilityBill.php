<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $utility_meter_id
 * @property CarbonImmutable $period
 * @property string $amount
 */
#[Fillable(['utility_meter_id', 'period', 'amount'])]
class UtilityBill extends Model
{
    protected function casts(): array
    {
        return [
            'period' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<UtilityMeter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(UtilityMeter::class, 'utility_meter_id');
    }

    /** @return HasMany<UtilityBillShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(UtilityBillShare::class);
    }

    /**
     * The bill photo / PDF (private disk).
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
