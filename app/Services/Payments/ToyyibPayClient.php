<?php

namespace App\Services\Payments;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the ToyyibPay API.
 *
 * @see https://toyyibpay.com/apireference/
 */
class ToyyibPayClient
{
    /** Online payment shows only when sandbox/live keys are set in .env. */
    public function configured(): bool
    {
        return filled(config('services.toyyibpay.secret_key')) && filled(config('services.toyyibpay.category_code'));
    }

    private function base(): string
    {
        return rtrim((string) config('services.toyyibpay.url'), '/');
    }

    private function secret(): string
    {
        return (string) config('services.toyyibpay.secret_key');
    }

    /** Create a fixed-amount FPX bill and return its BillCode. */
    public function createBill(Invoice $invoice, User $payer, float $amount, string $returnUrl, string $callbackUrl, string $reference): string
    {
        $response = Http::asForm()->timeout(20)->post($this->base().'/index.php/api/createBill', [
            'userSecretKey' => $this->secret(),
            'categoryCode' => (string) config('services.toyyibpay.category_code'),
            // ToyyibPay allows only letters, numbers, space and "_" here.
            'billName' => $this->clean('Rent '.$invoice->invoice_no, 30),
            'billDescription' => $this->clean('Kuncia rent '.$invoice->period->format('F Y'), 100),
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => (int) round($amount * 100), // cents
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => $callbackUrl,
            'billExternalReferenceNo' => $reference,
            'billTo' => $payer->name,
            'billEmail' => $payer->email,
            'billPhone' => preg_replace('/\D/', '', (string) $payer->phone),
            'billPaymentChannel' => 0, // FPX only
            'billExpiryDays' => 3,
        ]);

        $code = $response->json('0.BillCode');

        if (! $response->successful() || ! is_string($code) || $code === '') {
            throw new RuntimeException('ToyyibPay createBill failed: '.$response->body());
        }

        return $code;
    }

    public function paymentUrl(string $billCode): string
    {
        return $this->base().'/'.$billCode;
    }

    /**
     * Ask ToyyibPay whether the bill was really paid (never trust the redirect alone).
     *
     * @return array{paid: bool, amount: float, reference: string|null}
     */
    public function status(string $billCode): array
    {
        $rows = Http::asForm()->timeout(20)
            ->post($this->base().'/index.php/api/getBillTransactions', ['billCode' => $billCode])
            ->json();

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && (string) ($row['billpaymentStatus'] ?? '') === '1') {
                return [
                    'paid' => true,
                    'amount' => (float) ($row['billpaymentAmount'] ?? 0),
                    'reference' => isset($row['billpaymentInvoiceNo']) ? (string) $row['billpaymentInvoiceNo'] : null,
                ];
            }
        }

        return ['paid' => false, 'amount' => 0.0, 'reference' => null];
    }

    /**
     * Callback hash = MD5(userSecretKey + status + order_id + refno + "ok").
     *
     * @param  array<string, mixed>  $data
     */
    public function validCallback(array $data): bool
    {
        $expected = md5($this->secret().($data['status'] ?? '').($data['order_id'] ?? '').($data['refno'] ?? '').'ok');

        return is_string($data['hash'] ?? null) && hash_equals($expected, $data['hash']);
    }

    private function clean(string $text, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/[^A-Za-z0-9 _]/', '_', $text)), 0, $max);
    }
}
