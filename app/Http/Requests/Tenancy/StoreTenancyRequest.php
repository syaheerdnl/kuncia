<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\UserRole;
use App\Models\Tenancy;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Tenancy::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        /** @var User $landlord */
        $landlord = $this->user();

        return [
            // Only this landlord's tenants and units are accepted.
            'tenant_id' => ['required', Rule::exists('users', 'id')->where(
                fn (Builder $q) => $q->where('landlord_id', $landlord->id)->where('role', UserRole::Tenant->value)
            )],
            'unit_id' => ['required', Rule::exists('units', 'id')->where(
                fn (Builder $q) => $q->whereIn('property_id', $landlord->properties()->select('id'))
            )],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'monthly_rent' => ['required', 'numeric', 'min:1', 'max:100000'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'due_day' => ['required', 'integer', 'between:1,28'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'Pick one of your tenants.',
            'unit_id.exists' => 'Pick one of your units.',
        ];
    }
}
