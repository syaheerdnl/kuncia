<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Tenancy;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.toyyibpay.url' => 'https://dev.toyyibpay.com',
        'services.toyyibpay.secret_key' => 'test-secret',
        'services.toyyibpay.category_code' => 'cat123',
    ]);

    $tenancy = Tenancy::factory()->create(['start_date' => '2026-09-01', 'monthly_rent' => 450]);
    $this->invoice = app(InvoiceService::class)->generateFor($tenancy, CarbonImmutable::parse('2026-10-01'));
    $this->tenant = $tenancy->tenant;
});

function fakeToyyib(bool $paid): void
{
    Http::fake([
        'dev.toyyibpay.com/index.php/api/createBill' => Http::response([['BillCode' => 'abc123']]),
        'dev.toyyibpay.com/index.php/api/getBillTransactions' => Http::response([[
            'billpaymentStatus' => $paid ? '1' : '2',
            'billpaymentAmount' => '450.00',
            'billpaymentInvoiceNo' => 'TP-REF-1',
        ]]),
    ]);
}

function callbackData(string $status = '1', string $secret = 'test-secret'): array
{
    $data = ['refno' => 'TP-REF-1', 'status' => $status, 'billcode' => 'abc123', 'order_id' => 'PAY-1', 'amount' => '450.00'];
    $data['hash'] = md5($secret.$data['status'].$data['order_id'].$data['refno'].'ok');

    return $data;
}

test('pay creates a pending payment and sends the tenant to ToyyibPay', function () {
    fakeToyyib(false);

    $this->actingAs($this->tenant)
        ->post(route('my.invoices.pay', $this->invoice))
        ->assertRedirect('https://dev.toyyibpay.com/abc123');

    $payment = Payment::sole();
    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->method)->toBe(PaymentMethod::ToyyibPay)
        ->and($payment->bill_code)->toBe('abc123')
        ->and($payment->amount)->toBe('450.00');

    Http::assertSent(fn ($req) => str_contains($req->url(), 'createBill')
        && $req['billAmount'] === 45000
        && $req['categoryCode'] === 'cat123'
        && ! str_contains($req['billName'], '-'));
});

test('return url marks invoice paid only after the API confirms', function () {
    fakeToyyib(true);
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice));

    $this->actingAs($this->tenant)
        ->get(route('payments.toyyibpay.return', ['billcode' => 'abc123', 'status_id' => '1']))
        ->assertRedirect(route('my.invoices.show', $this->invoice));

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Success);
});

test('a faked success redirect does not mark the invoice paid', function () {
    fakeToyyib(false); // API says not paid
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice));

    $this->actingAs($this->tenant)->get(route('payments.toyyibpay.return', ['billcode' => 'abc123', 'status_id' => '1']));

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and(Payment::sole()->status)->toBe(PaymentStatus::Pending);
});

test('failed return marks the attempt failed', function () {
    fakeToyyib(false);
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice));

    $this->actingAs($this->tenant)->get(route('payments.toyyibpay.return', ['billcode' => 'abc123', 'status_id' => '3']));

    expect(Payment::sole()->status)->toBe(PaymentStatus::Failed)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

test('valid callback pays once even if sent twice', function () {
    fakeToyyib(false);
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice));
    auth()->logout();

    $this->post(route('payments.toyyibpay.callback'), callbackData())->assertOk();
    $this->post(route('payments.toyyibpay.callback'), callbackData())->assertOk();

    expect(Payment::where('status', PaymentStatus::Success)->count())->toBe(1)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

test('callback with a wrong hash is rejected', function () {
    fakeToyyib(false);
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice));
    auth()->logout();

    $this->post(route('payments.toyyibpay.callback'), callbackData('1', 'wrong-secret'))->assertForbidden();

    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

test('only the invoice tenant can pay, and not twice', function () {
    fakeToyyib(false);

    $this->actingAs(User::factory()->tenant()->create())->post(route('my.invoices.pay', $this->invoice))->assertForbidden();

    $this->invoice->update(['status' => InvoiceStatus::Paid]);
    $this->actingAs($this->tenant)->post(route('my.invoices.pay', $this->invoice))->assertForbidden();
});

test('gateway error keeps the invoice unpaid and shows a message', function () {
    Http::fake(['dev.toyyibpay.com/*' => Http::response('[KEY-DID-NOT-EXIST]', 200)]);

    $this->actingAs($this->tenant)->from(route('my.invoices.show', $this->invoice))
        ->post(route('my.invoices.pay', $this->invoice))
        ->assertRedirect(route('my.invoices.show', $this->invoice));

    expect(Payment::count())->toBe(0);
});
