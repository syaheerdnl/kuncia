<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isLandlord();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Malaysian mobile, e.g. 012-3456789 or 0112345678
            'phone' => ['required', 'regex:/^01\d-?\d{7,8}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['phone.regex' => 'Use a Malaysian mobile number, e.g. 012-3456789.'];
    }
}
