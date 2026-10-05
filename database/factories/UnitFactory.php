<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $rent = fake()->randomElement([250, 300, 350, 450, 600, 900, 1200]);

        return [
            'property_id' => Property::factory(),
            'code' => fake()->randomElement(['A', 'B', 'C']).'-'.fake()->unique()->numberBetween(101, 999),
            'type' => fake()->randomElement(UnitType::cases()),
            'monthly_rent' => $rent,
            'deposit' => $rent * 2,
            'status' => UnitStatus::Vacant,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn (array $attributes) => ['status' => UnitStatus::Occupied]);
    }
}
