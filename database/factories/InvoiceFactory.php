<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenancy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $period = now()->startOfMonth();

        return [
            'tenancy_id' => Tenancy::factory(),
            'invoice_no' => 'INV-'.$period->format('Ym').'-'.fake()->unique()->numerify('####'),
            'period' => $period,
            'issue_date' => $period,
            'due_date' => $period->copy()->day(7),
            'total' => 450,
            'status' => InvoiceStatus::Unpaid,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Overdue,
            'due_date' => now()->subDays(10),
        ]);
    }
}
