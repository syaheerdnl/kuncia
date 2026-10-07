<?php

namespace App\Http\Requests\Utilities;

use App\Enums\UtilityType;
use App\Models\Property;
use App\Models\UtilityMeter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meter = $this->route('meter');

        return $meter instanceof UtilityMeter
            ? $this->user()?->can('update', $meter) ?? false
            : $this->user()?->can('update', $this->property()) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(UtilityType::class)],
            'label' => ['required', 'string', 'max:60'],
            'account_no' => ['nullable', 'string', 'max:40'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', Rule::exists('units', 'id')->where('property_id', $this->property()->id)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'unit_ids.required' => 'Pick at least one unit on this meter.',
            'unit_ids.*.exists' => 'Units must belong to this property.',
        ];
    }

    public function property(): Property
    {
        $meter = $this->route('meter');

        /** @var Property $property */
        $property = $meter instanceof UtilityMeter ? $meter->property : $this->route('property');

        return $property;
    }
}
