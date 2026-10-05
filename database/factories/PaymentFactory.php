<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => 450,
            'method' => PaymentMethod::Cash,
            'reference' => strtoupper(fake()->bothify('REF-####??')),
            'status' => PaymentStatus::Success,
            'paid_at' => now(),
        ];
    }
}
