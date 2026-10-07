<?php

namespace App\Models;

use App\Enums\TenancyStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $phone
 * @property int|null $landlord_id
 * @property bool $is_guest
 * @property-read string|null $outstanding
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'landlord_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_guest' => 'boolean',
        ];
    }

    public function isLandlord(): bool
    {
        return $this->role === UserRole::Landlord;
    }

    public function isTenant(): bool
    {
        return $this->role === UserRole::Tenant;
    }

    public function isMaintenance(): bool
    {
        return $this->role === UserRole::Maintenance;
    }

    /** @return HasMany<Property, $this> */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /** @return HasMany<Tenancy, $this> */
    public function tenancies(): HasMany
    {
        return $this->hasMany(Tenancy::class, 'tenant_id');
    }

    /**
     * Landlord who added this tenant / staff member.
     *
     * @return BelongsTo<User, $this>
     */
    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    /** @return HasMany<User, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(User::class, 'landlord_id')->where('role', UserRole::Tenant);
    }

    /** @return HasMany<User, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'landlord_id')->where('role', UserRole::Maintenance);
    }

    /** @return HasOne<Tenancy, $this> */
    public function activeTenancy(): HasOne
    {
        return $this->hasOne(Tenancy::class, 'tenant_id')->where('status', TenancyStatus::Active);
    }

    /** @return HasManyThrough<Invoice, Tenancy, $this> */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Tenancy::class, 'tenant_id', 'tenancy_id');
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'tenant_id');
    }

    /** @return HasMany<MaintenanceRequest, $this> */
    public function assignedRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'assigned_to');
    }
}
