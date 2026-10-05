<?php

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Enums\TenancyStatus;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenancy>
 */
class TenancyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory()->occupied(),
            'tenant_id' => User::factory()->tenant(),
            'start_date' => now()->subMonths(3)->startOfMonth(),
            'end_date' => now()->addMonths(9)->endOfMonth(),
            'monthly_rent' => 450,
            'deposit_amount' => 900,
            'deposit_status' => DepositStatus::Held,
            'status' => TenancyStatus::Active,
            'due_day' => 7,
        ];
    }

    public function configure(): static
    {
        // Keep data consistent: the tenant belongs to the landlord who owns the unit.
        return $this->afterCreating(function (Tenancy $tenancy) {
            if ($tenancy->tenant->landlord_id === null) {
                $tenancy->tenant->update(['landlord_id' => $tenancy->unit->property->owner_id]);
            }
        });
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TenancyStatus::Ended,
            'end_date' => now()->subMonth()->endOfMonth(),
        ]);
    }
}
