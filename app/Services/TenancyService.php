<?php

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\TenancyStatus;
use App\Enums\UnitStatus;
use App\Models\Tenancy;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenancyService
{
    /**
     * Move a tenant into a unit. The unit row is locked so two requests
     * cannot book the same unit at the same time.
     *
     * @param  array<string, mixed>  $data  validated StoreTenancyRequest data
     */
    public function start(array $data): Tenancy
    {
        return DB::transaction(function () use ($data) {
            $unit = Unit::whereKey($data['unit_id'])->lockForUpdate()->firstOrFail();

            if ($unit->status !== UnitStatus::Vacant || $unit->activeTenancy()->exists()) {
                throw ValidationException::withMessages(['unit_id' => 'This unit is no longer vacant.']);
            }

            $tenancy = $unit->tenancies()->create([
                ...$data,
                'status' => TenancyStatus::Active,
                'deposit_status' => DepositStatus::Held,
            ]);

            $unit->update(['status' => UnitStatus::Occupied]);

            return $tenancy;
        });
    }

    /** End a tenancy and free the unit. */
    public function end(Tenancy $tenancy, CarbonInterface $endDate, DepositStatus $deposit): void
    {
        DB::transaction(function () use ($tenancy, $endDate, $deposit) {
            $tenancy->update([
                'status' => TenancyStatus::Ended,
                'end_date' => $endDate,
                'deposit_status' => $deposit,
            ]);

            $tenancy->unit->update(['status' => UnitStatus::Vacant]);
        });
    }
}
