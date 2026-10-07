<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && in_array($invoice->status, [InvoiceStatus::Unpaid, InvoiceStatus::Overdue], true)
            && (bool) $this->user()?->can('update', $invoice);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');
        $max = app(InvoiceService::class)->outstanding($invoice);

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$max],
            // Online payments come through the gateway, not this form.
            'method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Transfer->value])],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['amount.max' => 'Amount is more than the balance due.'];
    }
}
