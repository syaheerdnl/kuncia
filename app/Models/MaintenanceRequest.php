<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $unit_id
 * @property int $tenant_id
 * @property int|null $assigned_to
 * @property string $title
 * @property string $description
 * @property MaintenancePriority $priority
 * @property MaintenanceStatus $status
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $resolved_at
 */
#[Fillable(['unit_id', 'tenant_id', 'assigned_to', 'title', 'description', 'priority', 'status', 'resolution_note', 'resolved_at'])]
class MaintenanceRequest extends Model
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Tickets on this landlord's properties.
     *
     * @param  Builder<MaintenanceRequest>  $query
     */
    public function scopeForLandlord(Builder $query, User $landlord): void
    {
        $query->whereHas('unit.property', fn ($q) => $q->where('owner_id', $landlord->id));
    }

    /**
     * Open work first, then by priority, newest first.
     *
     * @param  Builder<MaintenanceRequest>  $query
     */
    public function scopeWorkOrder(Builder $query): void
    {
        $query->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 when 'resolved' then 2 else 3 end")
            ->orderByRaw("case priority when 'high' then 0 when 'medium' then 1 else 2 end")
            ->latest();
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

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
