<?php

namespace App\Support;

use App\Enums\DepositStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PropertyType;
use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Builds a realistic sample portfolio for one landlord.
 * No factories / Faker, so it also runs on the production server
 * (used for the local demo seeder and the guest sandbox).
 */
class SampleData
{
    private const TENANTS = ['Aisyah Rahman', 'Muhammad Faiz', 'Nurul Huda', 'Lim Wei Jie', 'Kavitha Raj', 'Hafiz Zulkifli'];

    private const UTILITIES = [25, 30, 40, 55, 30, 40];

    /**
     * @param  array{tenant: string, tech: string}  $emails  logins for the main sample tenant and technician
     */
    public static function seed(User $landlord, array $emails, string $password, bool $guest = false): void
    {
        $make = function (string $name, string $email, UserRole $role) use ($landlord, $password, $guest): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'phone' => '01'.random_int(2, 9).'-'.random_int(1000000, 9999999),
                'landlord_id' => $landlord->id,
            ]);
            $user->forceFill(['email_verified_at' => now(), 'is_guest' => $guest])->save();

            return $user;
        };

        $tech = $make('Ali Technician', $emails['tech'], UserRole::Maintenance);

        $hostel = $landlord->properties()->create([
            'name' => 'Hostel Seri Durian Tunggal',
            'type' => PropertyType::Hostel,
            'address' => '12, Jalan TU 43, Taman Tasik Utama',
            'city' => 'Durian Tunggal',
            'state' => 'Melaka',
            'postcode' => '76100',
            'description' => 'Student hostel near UTeM, single rooms with shared kitchen.',
        ]);
        $house = $landlord->properties()->create([
            'name' => 'Rumah Sewa Ayer Keroh',
            'type' => PropertyType::House,
            'address' => '8, Jalan AK 3, Taman Ayer Keroh Heights',
            'city' => 'Ayer Keroh',
            'state' => 'Melaka',
            'postcode' => '75450',
            'description' => 'Double-storey terrace, rented by room.',
        ]);

        /** @var list<Unit> $units */
        $units = [];
        foreach (['A-101', 'A-102', 'A-103', 'A-104', 'B-201', 'B-202'] as $code) {
            $units[] = $hostel->units()->create(['code' => $code, 'type' => UnitType::Room, 'monthly_rent' => 350, 'deposit' => 700, 'status' => UnitStatus::Vacant]);
        }
        foreach (['R1', 'R2', 'R3', 'R4'] as $code) {
            $units[] = $house->units()->create(['code' => $code, 'type' => UnitType::Room, 'monthly_rent' => 450, 'deposit' => 900, 'status' => UnitStatus::Vacant]);
        }

        /** @var list<User> $tenants */
        $tenants = [];
        foreach (self::TENANTS as $i => $name) {
            $email = $i === 0 ? $emails['tenant'] : Str::slug($name, '.').'.'.$landlord->id.'@kuncia.test';
            $tenants[] = $make($name, $email, UserRole::Tenant);
        }

        $thisMonth = now()->startOfMonth();

        foreach ($tenants as $i => $tenant) {
            $unit = $units[$i];
            $unit->update(['status' => UnitStatus::Occupied]);

            $tenancy = $unit->tenancies()->create([
                'tenant_id' => $tenant->id,
                'start_date' => $thisMonth->subMonths(3),
                'end_date' => $i === 4 ? $thisMonth->addMonth()->endOfMonth() : $thisMonth->addMonths(9)->endOfMonth(),
                'monthly_rent' => $unit->monthly_rent,
                'deposit_amount' => $unit->deposit,
                'deposit_status' => DepositStatus::Held,
                'status' => TenancyStatus::Active,
                'due_day' => 7,
            ]);

            foreach ([2, 1, 0] as $monthsAgo) {
                self::invoice($tenancy, $thisMonth->subMonths($monthsAgo), self::statusFor($i, $monthsAgo), self::UTILITIES[$i]);
            }
        }

        $units[9]->update(['status' => UnitStatus::Maintenance]);

        $tickets = [
            ['Aircond not cold', 'Blowing warm air since Monday, remote shows error E5.', MaintenancePriority::High, MaintenanceStatus::Open, null],
            ['Water leaking in bathroom', 'Pipe under the sink is dripping, floor always wet.', MaintenancePriority::High, MaintenanceStatus::InProgress, $tech->id],
            ['Ceiling fan broken', 'Fan makes noise and stops after a few minutes.', MaintenancePriority::Medium, MaintenanceStatus::InProgress, $tech->id],
            ['Door lock stuck', 'Key hard to turn, need to push the door.', MaintenancePriority::Low, MaintenanceStatus::Resolved, $tech->id],
            ['No hot water', 'Water heater not turning on.', MaintenancePriority::Medium, MaintenanceStatus::Closed, $tech->id],
        ];

        foreach ($tickets as $i => [$title, $description, $priority, $status, $assignee]) {
            $done = in_array($status, [MaintenanceStatus::Resolved, MaintenanceStatus::Closed], true);
            MaintenanceRequest::create([
                'unit_id' => $units[$i]->id,
                'tenant_id' => $tenants[$i]->id,
                'assigned_to' => $assignee,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'resolution_note' => $done ? 'Fixed and tested.' : null,
                'resolved_at' => $done ? now()->subDays(3) : null,
            ]);
        }
    }

    /** Older months mostly paid; tenants #3 and #6 fell behind last month; this month mostly unpaid. */
    private static function statusFor(int $tenantIndex, int $monthsAgo): InvoiceStatus
    {
        if ($monthsAgo === 0) {
            return in_array($tenantIndex, [0, 3], true) ? InvoiceStatus::Paid : InvoiceStatus::Unpaid;
        }

        if ($monthsAgo === 1 && in_array($tenantIndex, [2, 5], true)) {
            return InvoiceStatus::Overdue;
        }

        return InvoiceStatus::Paid;
    }

    private static function invoice(Tenancy $tenancy, CarbonInterface $period, InvoiceStatus $status, float $utilities): void
    {
        $due = $period->copy()->day($tenancy->due_day);

        $invoice = Invoice::create([
            'tenancy_id' => $tenancy->id,
            'invoice_no' => Invoice::nextNumber($period),
            'period' => $period,
            'issue_date' => $period,
            'due_date' => $due,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::Paid ? $due->copy()->subDays(2) : null,
        ]);

        $invoice->items()->create(['description' => 'Monthly rent - '.$period->format('F Y'), 'amount' => $tenancy->monthly_rent]);
        $invoice->items()->create(['description' => 'Utilities (water & electric)', 'amount' => $utilities]);

        if ($status === InvoiceStatus::Overdue) {
            $invoice->items()->create(['description' => 'Late payment fee', 'amount' => config('sewahub.late_fee', 20)]);
        }

        $invoice->recalculateTotal();

        if ($status === InvoiceStatus::Paid) {
            $invoice->payments()->create([
                'amount' => $invoice->total,
                'method' => [PaymentMethod::Transfer, PaymentMethod::Cash, PaymentMethod::ToyyibPay][$tenancy->id % 3],
                'reference' => 'REF-'.strtoupper(Str::random(6)),
                'status' => PaymentStatus::Success,
                'paid_at' => $invoice->paid_at,
            ]);
        }
    }
}
