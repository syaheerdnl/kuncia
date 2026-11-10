<?php

namespace App\Http\Requests\Property;

use App\Enums\PaymentMethod;
use App\Enums\TenancyStatus;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Make up the invoice for a month that was missed, and optionally record its payment. Route has {property}. */
class StoreRentMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && (bool) $this->user()?->can('update', $property);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var Property $property */
        $property = $this->route('property');

        return [
            // Only a running tenancy on this property.
            'tenancy_id' => [
                'required', 'integer',
                Rule::exists('tenancies', 'id')
                    ->where('status', TenancyStatus::Active->value)
                    ->where(fn ($q) => $q->whereIn('unit_id', Unit::where('property_id', $property->id)->select('id'))),
            ],
            'month' => ['required', 'date_format:Y-m'],
            'paid' => ['boolean'],
            'amount' => ['required_if:paid,true', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'paid_at' => ['required_if:paid,true', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required_if:paid,true', 'nullable', Rule::in([PaymentMethod::Cash->value, PaymentMethod::Transfer->value])],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $month = CarbonImmutable::parse($this->string('month')->value().'-01');
                $tenancy = Tenancy::find($this->integer('tenancy_id'));

                if ($month->gt(CarbonImmutable::today()->startOfMonth())) {
                    $validator->errors()->add('month', 'That month has not started yet.');
                } elseif ($tenancy && $month->lt($tenancy->start_date->startOfMonth())) {
                    $validator->errors()->add('month', 'That is before this tenant moved in.');
                } elseif ($tenancy && $tenancy->end_date !== null && $month->gt($tenancy->end_date)) {
                    $validator->errors()->add('month', 'That is after this tenancy ended.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tenancy_id.exists' => 'Pick a current tenant of this property.',
            'amount.required_if' => 'Enter the amount received.',
            'paid_at.required_if' => 'Enter the date it was received.',
            'method.required_if' => 'Pick how it was paid.',
        ];
    }
}
