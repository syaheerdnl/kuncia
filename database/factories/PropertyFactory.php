<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $locations = [
            ['Melaka', 'Melaka', '75450'],
            ['Ayer Keroh', 'Melaka', '75450'],
            ['Durian Tunggal', 'Melaka', '76100'],
            ['Shah Alam', 'Selangor', '40000'],
            ['Petaling Jaya', 'Selangor', '46000'],
            ['Kuala Lumpur', 'W.P. Kuala Lumpur', '50450'],
        ];
        [$city, $state, $postcode] = fake()->randomElement($locations);

        return [
            'owner_id' => User::factory()->landlord(),
            'name' => fake()->randomElement(['Residensi', 'Rumah Sewa', 'Hostel', 'Pangsapuri']).' '.fake()->lastName(),
            'type' => fake()->randomElement(PropertyType::cases()),
            'address' => fake()->buildingNumber().', Jalan '.fake()->lastName(),
            'city' => $city,
            'state' => $state,
            'postcode' => $postcode,
            'description' => fake()->sentence(12),
        ];
    }
}
