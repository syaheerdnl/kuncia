<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property PropertyType $type
 * @property string $address
 * @property string $city
 * @property string $state
 * @property string $postcode
 * @property string|null $description
 * @property string|null $cover_image
 * @property-read int|null $units_count
 * @property-read int|null $occupied_count
 */
#[Fillable(['owner_id', 'name', 'type', 'address', 'city', 'state', 'postcode', 'description', 'cover_image'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['type' => PropertyType::class];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasManyThrough<Tenancy, Unit, $this> */
    public function tenancies(): HasManyThrough
    {
        return $this->hasManyThrough(Tenancy::class, Unit::class);
    }

    /** @return HasMany<UtilityMeter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(UtilityMeter::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
