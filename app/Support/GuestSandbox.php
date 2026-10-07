<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBill;
use App\Models\UtilityBillShare;
use App\Models\UtilityMeter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rebuilds the guest landlord's sample data. Only rows owned by the guest
 * landlord are deleted, so real landlords on the same server are never touched.
 */
class GuestSandbox
{
    public function reset(): User
    {
        /** @var array{email: string, password: string, tenant_email: string, tech_email: string} $cfg */
        $cfg = config('kuncia.guest');

        return DB::transaction(function () use ($cfg) {
            $old = User::where('email', $cfg['email'])->first();

            if ($old) {
                $this->wipe($old);
            }

            $guest = User::create([
                'name' => 'Guest Landlord',
                'email' => $cfg['email'],
                'password' => $cfg['password'],
                'role' => UserRole::Landlord,
            ]);
            $guest->forceFill(['email_verified_at' => now(), 'is_guest' => true])->save();

            SampleData::seed($guest, ['tenant' => $cfg['tenant_email'], 'tech' => $cfg['tech_email']], $cfg['password'], guest: true);

            return $guest;
        });
    }

    private function wipe(User $landlord): void
    {
        $propertyIds = Property::where('owner_id', $landlord->id)->pluck('id');
        $unitIds = Unit::whereIn('property_id', $propertyIds)->pluck('id');
        $tenancyIds = Tenancy::whereIn('unit_id', $unitIds)->pluck('id');
        $invoiceIds = Invoice::whereIn('tenancy_id', $tenancyIds)->pluck('id');
        $ticketIds = MaintenanceRequest::whereIn('unit_id', $unitIds)->pluck('id');
        $meterIds = UtilityMeter::whereIn('property_id', $propertyIds)->pluck('id');
        $billIds = UtilityBill::whereIn('utility_meter_id', $meterIds)->pluck('id');

        // Uploaded files (bill scans, ticket photos, property covers)
        $attachments = Attachment::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('attachable_type', (new Invoice)->getMorphClass())->whereIn('attachable_id', $invoiceIds))
                ->orWhere(fn ($w) => $w->where('attachable_type', (new MaintenanceRequest)->getMorphClass())->whereIn('attachable_id', $ticketIds))
                ->orWhere(fn ($w) => $w->where('attachable_type', (new UtilityBill)->getMorphClass())->whereIn('attachable_id', $billIds)))
            ->get();
        foreach ($attachments as $a) {
            Storage::disk('local')->delete($a->path);
            $a->delete();
        }
        foreach (Property::whereIn('id', $propertyIds)->whereNotNull('cover_image')->pluck('cover_image') as $cover) {
            Storage::disk('public')->delete((string) $cover);
        }

        UtilityBillShare::whereIn('utility_bill_id', $billIds)->delete();
        UtilityBill::whereIn('id', $billIds)->delete();
        UtilityMeter::whereIn('id', $meterIds)->delete(); // unit links cascade
        Payment::whereIn('invoice_id', $invoiceIds)->delete();
        Invoice::whereIn('id', $invoiceIds)->delete(); // items cascade
        MaintenanceRequest::whereIn('id', $ticketIds)->delete();
        Tenancy::whereIn('id', $tenancyIds)->delete();
        Unit::whereIn('id', $unitIds)->delete();
        Property::whereIn('id', $propertyIds)->delete();

        // Tenants / staff the guest created (sample ones + any added by visitors)
        User::where('landlord_id', $landlord->id)->delete();
        $landlord->delete();
    }
}
