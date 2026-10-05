<?php

namespace Database\Factories;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => User::factory()->tenant(),
            'title' => fake()->randomElement([
                'Aircond not cold', 'Water leaking in bathroom', 'Ceiling fan broken',
                'Door lock stuck', 'No hot water', 'Light bulb blown',
            ]),
            'description' => fake()->sentence(15),
            'priority' => fake()->randomElement(MaintenancePriority::cases()),
            'status' => MaintenanceStatus::Open,
        ];
    }
}
