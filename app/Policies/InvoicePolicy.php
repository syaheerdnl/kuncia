<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->isLandlordOf($user, $invoice) || $this->isTenantOf($user, $invoice);
    }

    /** Landlord records a cash/transfer payment or voids the invoice. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->isLandlordOf($user, $invoice);
    }

    /** Tenant pays online. */
    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->isTenantOf($user, $invoice)
            && in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Overdue], true);
    }

    private function isLandlordOf(User $user, Invoice $invoice): bool
    {
        return $user->isLandlord() && $invoice->tenancy->unit->property->owner_id === $user->id;
    }

    private function isTenantOf(User $user, Invoice $invoice): bool
    {
        return $user->isTenant() && $invoice->tenancy->tenant_id === $user->id;
    }
}
