<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\DepositStatus;
use App\Enums\TenancyStatus;
use App\Models\Tenancy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EndTenancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenancy = $this->route('tenancy');

        return $tenancy instanceof Tenancy
            && $tenancy->status === TenancyStatus::Active
            && (bool) $this->user()?->can('update', $tenancy);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $tenancy = $this->route('tenancy');
        $start = $tenancy instanceof Tenancy ? $tenancy->start_date->toDateString() : null;

        return [
            'end_date' => ['required', 'date', 'after_or_equal:'.$start],
            'deposit_status' => ['required', Rule::in([DepositStatus::Refunded->value, DepositStatus::Forfeited->value])],
        ];
    }
}
