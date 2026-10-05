<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\TenancyStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TenancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $unit_id
 * @property int $tenant_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property string $monthly_rent
 * @property string $deposit_amount
 * @property DepositStatus $deposit_status
 * @property TenancyStatus $status
 * @property int $due_day
 */
#[Fillable(['unit_id', 'tenant_id', 'start_date', 'end_date', 'monthly_rent', 'deposit_amount', 'deposit_status', 'status', 'due_day'])]
class Tenancy extends Model
{
    /** @use HasFactory<TenancyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'deposit_status' => DepositStatus::class,
            'status' => TenancyStatus::class,
            'due_day' => 'integer',
        ];
    }

    /** @param Builder<Tenancy> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TenancyStatus::Active);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
