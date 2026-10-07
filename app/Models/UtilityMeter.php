<?php

namespace App\Models;

use App\Enums\UtilityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $property_id
 * @property UtilityType $type
 * @property string $label
 * @property string|null $account_no
 */
#[Fillable(['property_id', 'type', 'label', 'account_no'])]
class UtilityMeter extends Model
{
    protected function casts(): array
    {
        return ['type' => UtilityType::class];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsToMany<Unit, $this> */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    /** @return HasMany<UtilityBill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(UtilityBill::class);
    }
}
