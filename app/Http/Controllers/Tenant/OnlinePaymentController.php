<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Payments\ToyyibPayClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OnlinePaymentController extends Controller
{
    public function __construct(
        private ToyyibPayClient $toyyibpay,
        private InvoiceService $invoices,
    ) {}

    /** Tenant clicks "Pay online": create a bill and send them to ToyyibPay. */
    public function pay(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('pay', $invoice);

        if (! $this->toyyibpay->configured()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Online payment is not enabled yet.']);

            return back();
        }

        /** @var User $tenant */
        $tenant = $request->user();
        $amount = $this->invoices->outstanding($invoice);

        try {
            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'method' => PaymentMethod::ToyyibPay,
                'status' => PaymentStatus::Pending,
            ]);

            $billCode = $this->toyyibpay->createBill(
                $invoice,
                $tenant,
                $amount,
                route('payments.toyyibpay.return'),
                route('payments.toyyibpay.callback'),
                'PAY-'.$payment->id,
            );
            $payment->update(['bill_code' => $billCode]);
        } catch (Throwable $e) {
            Log::error('ToyyibPay bill creation failed', ['invoice' => $invoice->id, 'error' => $e->getMessage()]);
            if (isset($payment)) {
                $payment->delete();
            }
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Payment gateway is unavailable. Please try again later.']);

            return back();
        }

        // Full-page redirect to an external site (works for Inertia + normal requests)
        return Inertia::location($this->toyyibpay->paymentUrl($billCode));
    }

    /** Browser comes back from ToyyibPay. Verify with the API before trusting it. */
    public function handleReturn(Request $request): RedirectResponse
    {
        $payment = Payment::where('bill_code', (string) $request->query('billcode'))->first();

        if (! $payment || Gate::denies('view', $payment->invoice)) {
            abort(404);
        }

        $status = $this->toyyibpay->status((string) $payment->bill_code);

        if ($status['paid']) {
            $this->invoices->confirmOnlinePayment($payment, $status['reference']);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment received. Thank you!']);
        } elseif ((string) $request->query('status_id') === '3') {
            $this->invoices->failOnlinePayment($payment);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Payment failed or was cancelled.']);
        } else {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Payment is still processing. We will update it shortly.']);
        }

        return to_route('my.invoices.show', $payment->invoice_id);
    }

    /** Server-to-server notification from ToyyibPay. */
    public function callback(Request $request): Response
    {
        $data = $request->only(['refno', 'status', 'reason', 'billcode', 'order_id', 'amount', 'hash']);

        if (! $this->toyyibpay->validCallback($data)) {
            Log::warning('ToyyibPay callback with bad hash', ['billcode' => $data['billcode'] ?? null]);
            abort(403);
        }

        $payment = Payment::where('bill_code', (string) ($data['billcode'] ?? ''))->first();

        if ($payment) {
            match ((string) ($data['status'] ?? '')) {
                '1' => $this->invoices->confirmOnlinePayment($payment, (string) ($data['refno'] ?? '')),
                '3' => $this->invoices->failOnlinePayment($payment),
                default => null,
            };
        }

        return response('OK');
    }
}
