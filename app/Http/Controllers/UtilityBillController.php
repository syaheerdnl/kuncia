<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\UtilityBill;
use App\Models\UtilityMeter;
use App\Services\Ai\BillReadException;
use App\Services\Ai\GeminiBillReader;
use App\Services\Utilities\UtilityBillService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Enter a meter bill once; it is split between the tenants on that meter. */
class UtilityBillController extends Controller
{
    public static function scanKey(UtilityMeter $meter): string
    {
        return "meter_scan.{$meter->id}";
    }

    public function store(Request $request, UtilityMeter $meter, UtilityBillService $service): RedirectResponse
    {
        Gate::authorize('update', $meter);

        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'bill' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'],
            'use_scan' => ['boolean'],
        ]);

        /** @var array{file_path?: string, file_name?: string}|null $scan */
        $scan = $request->session()->get(self::scanKey($meter));
        $file = null;

        if ($request->boolean('use_scan') && isset($scan['file_path'], $scan['file_name'])) {
            $file = ['path' => $scan['file_path'], 'name' => $scan['file_name']];
        } elseif (($upload = $request->file('bill')) instanceof UploadedFile) {
            $file = ['path' => (string) $upload->store('bills', 'local'), 'name' => $upload->getClientOriginalName()];
        }

        $bill = $service->record(
            $meter,
            CarbonImmutable::createFromFormat('Y-m', $data['period'])->startOfMonth(),
            (float) $data['amount'],
            $file,
        );

        $request->session()->forget(self::scanKey($meter));

        $count = $bill->shares()->count();
        $waiting = $bill->shares()->whereNull('invoice_item_id')->count();
        $message = match (true) {
            $count === 0 => 'Bill saved. No unit was occupied that month, so nobody was charged.',
            $waiting > 0 => "Bill split between {$count} tenant(s). {$waiting} share(s) will go on their next invoice.",
            default => "Bill split between {$count} tenant(s) and added to their invoices.",
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /** Upload the bill; Gemini reads it; the landlord confirms in the dialog. */
    public function scan(Request $request, UtilityMeter $meter, GeminiBillReader $reader): RedirectResponse
    {
        Gate::authorize('update', $meter);

        $request->validate([
            'bill' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:5120'],
        ]);

        /** @var UploadedFile $upload */
        $upload = $request->file('bill');

        try {
            $result = $reader->read((string) file_get_contents($upload->getRealPath()), (string) $upload->getMimeType());
        } catch (BillReadException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        $this->forgetScan($request, $meter);

        $request->session()->put(self::scanKey($meter), [
            ...$result,
            'file_path' => (string) $upload->store('bills', 'local'),
            'file_name' => $upload->getClientOriginalName(),
        ]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Bill read. Check the month and amount, then save.']);

        return back();
    }

    public function dismiss(Request $request, UtilityMeter $meter): RedirectResponse
    {
        Gate::authorize('update', $meter);
        $this->forgetScan($request, $meter);

        return back();
    }

    public function destroy(UtilityBill $bill, UtilityBillService $service): RedirectResponse
    {
        Gate::authorize('delete', $bill);

        $service->remove($bill);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bill removed from the meter and from unpaid invoices.']);

        return back();
    }

    /** Drop a pending scan and its file (unless a saved bill uses it). */
    private function forgetScan(Request $request, UtilityMeter $meter): void
    {
        /** @var array{file_path?: string}|null $old */
        $old = $request->session()->pull(self::scanKey($meter));

        if (isset($old['file_path']) && ! Attachment::where('path', $old['file_path'])->exists()) {
            Storage::disk('local')->delete($old['file_path']);
        }
    }
}
