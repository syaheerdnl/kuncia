<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PropertyType;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Demo data for recruiters. All accounts use the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $landlord = User::factory()->landlord()->create([
            'name' => 'Encik Hakim (Landlord)',
            'email' => 'landlord@sewahub.test',
        ]);

        $tech = User::factory()->maintenance()->create([
            'name' => 'Ali Technician',
            'email' => 'tech@sewahub.test',
            'landlord_id' => $landlord->id,
        ]);

        $hostel = Property::factory()->for($landlord, 'owner')->create([
            'name' => 'Hostel Seri Durian Tunggal',
            'type' => PropertyType::Hostel,
            'address' => '12, Jalan TU 43, Taman Tasik Utama',
            'city' => 'Durian Tunggal',
            'state' => 'Melaka',
            'postcode' => '76100',
        ]);

        $house = Property::factory()->for($landlord, 'owner')->create([
            'name' => 'Rumah Sewa Ayer Keroh',
            'type' => PropertyType::House,
            'address' => '8, Jalan AK 3, Taman Ayer Keroh Heights',
            'city' => 'Ayer Keroh',
            'state' => 'Melaka',
            'postcode' => '75450',
        ]);

        /** @var list<Unit> $units */
        $units = [];
        foreach (['A-101', 'A-102', 'A-103', 'A-104', 'B-201', 'B-202'] as $code) {
            $units[] = (Unit::factory()->for($hostel)->create([
                'code' => $code, 'type' => UnitType::Room, 'monthly_rent' => 350, 'deposit' => 700,
            ]));
        }
        foreach (['R1', 'R2', 'R3', 'R4'] as $code) {
            $units[] = (Unit::factory()->for($house)->create([
                'code' => $code, 'type' => UnitType::Room, 'monthly_rent' => 450, 'deposit' => 900,
            ]));
        }

        // 6 tenants occupy the first 6 units; the first one is the public demo tenant.
        /** @var list<User> $tenants */
        $tenants = [
            User::factory()->tenant()->create(['name' => 'Aisyah Rahman', 'email' => 'tenant@sewahub.test', 'landlord_id' => $landlord->id]),
            ...User::factory()->tenant()->count(5)->create(['landlord_id' => $landlord->id])->all(),
        ];

        $thisMonth = now()->startOfMonth();

        foreach ($tenants as $i => $tenant) {
            $unit = $units[$i];
            $unit->update(['status' => UnitStatus::Occupied]);

            $tenancy = Tenancy::factory()->for($unit)->for($tenant, 'tenant')->create([
                'start_date' => $thisMonth->copy()->subMonths(3),
                'end_date' => $thisMonth->copy()->addMonths(9)->endOfMonth(),
                'monthly_rent' => $unit->monthly_rent,
                'deposit_amount' => $unit->deposit,
            ]);

            // Last 2 months + current month.
            foreach ([2, 1, 0] as $monthsAgo) {
                $period = $thisMonth->copy()->subMonths($monthsAgo);
                $status = $this->statusFor($i, $monthsAgo);
                $this->makeInvoice($tenancy, $period, $status);
            }
        }

        // One unit under repair
        $units[9]->update(['status' => UnitStatus::Maintenance]);

        $tickets = [
            ['Aircond not cold', MaintenancePriority::High, MaintenanceStatus::Open, null],
            ['Water leaking in bathroom', MaintenancePriority::High, MaintenanceStatus::InProgress, $tech->id],
            ['Ceiling fan broken', MaintenancePriority::Medium, MaintenanceStatus::InProgress, $tech->id],
            ['Door lock stuck', MaintenancePriority::Low, MaintenanceStatus::Resolved, $tech->id],
            ['No hot water', MaintenancePriority::Medium, MaintenanceStatus::Closed, $tech->id],
        ];

        foreach ($tickets as $i => [$title, $priority, $status, $assignee]) {
            MaintenanceRequest::factory()->create([
                'unit_id' => $units[$i]->id,
                'tenant_id' => $tenants[$i]->id,
                'assigned_to' => $assignee,
                'title' => $title,
                'priority' => $priority,
                'status' => $status,
                'resolved_at' => in_array($status, [MaintenanceStatus::Resolved, MaintenanceStatus::Closed], true)
                    ? now()->subDays(3) : null,
            ]);
        }
    }

    /** Older months mostly paid; tenant #2 and #5 fell behind last month; current month unpaid. */
    private function statusFor(int $tenantIndex, int $monthsAgo): InvoiceStatus
    {
        if ($monthsAgo === 0) {
            return in_array($tenantIndex, [0, 3], true) ? InvoiceStatus::Paid : InvoiceStatus::Unpaid;
        }

        if ($monthsAgo === 1 && in_array($tenantIndex, [2, 5], true)) {
            return InvoiceStatus::Overdue;
        }

        return InvoiceStatus::Paid;
    }

    private function makeInvoice(Tenancy $tenancy, CarbonInterface $period, InvoiceStatus $status): void
    {
        $issue = $period->copy();
        $due = $period->copy()->day($tenancy->due_day);

        $invoice = Invoice::create([
            'tenancy_id' => $tenancy->id,
            'invoice_no' => Invoice::nextNumber($period),
            'period' => $period,
            'issue_date' => $issue,
            'due_date' => $due,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? $due->copy()->subDays(2) : null,
        ]);

        $invoice->items()->create(['description' => 'Monthly rent - '.$period->format('F Y'), 'amount' => $tenancy->monthly_rent]);
        $invoice->items()->create(['description' => 'Utilities (water & electric)', 'amount' => fake()->randomElement([25, 30, 40, 55])]);

        if ($status === InvoiceStatus::Overdue) {
            $invoice->items()->create(['description' => 'Late payment fee', 'amount' => 20]);
        }

        $invoice->recalculateTotal();

        if ($status === InvoiceStatus::Paid) {
            $invoice->payments()->create([
                'amount' => $invoice->total,
                'method' => fake()->randomElement([PaymentMethod::ToyyibPay, PaymentMethod::Cash, PaymentMethod::Transfer]),
                'reference' => strtoupper(fake()->bothify('REF-####??')),
                'status' => PaymentStatus::Success,
                'paid_at' => $invoice->paid_at,
            ]);
        }
    }
}
