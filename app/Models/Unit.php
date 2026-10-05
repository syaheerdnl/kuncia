<?php

namespace App\Models;

use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $property_id
 * @property string $code
 * @property UnitType $type
 * @property string $monthly_rent
 * @property string $deposit
 * @property UnitStatus $status
 */
#[Fillable(['property_id', 'code', 'type', 'monthly_rent', 'deposit', 'status'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'status' => UnitStatus::class,
            'monthly_rent' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return HasMany<Tenancy, $this> */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class);
    }

    /** @return HasOne<Tenancy, $this> */
    public function activeTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class)->where('status', TenancyStatus::Active);
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }
}
