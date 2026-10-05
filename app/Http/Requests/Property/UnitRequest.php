<?php

namespace App\Http\Requests\Property;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store: route has {property}. Update: route has {unit} (shallow).
 */
class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');
        $property = $this->route('property');

        if ($unit instanceof Unit) {
            return (bool) $this->user()?->can('update', $unit);
        }

        return $property instanceof Property && (bool) $this->user()?->can('update', $property);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $unit = $this->route('unit');
        $property = $this->route('property');
        $propertyId = $unit instanceof Unit ? $unit->property_id : ($property instanceof Property ? $property->id : null);

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('units')->where('property_id', $propertyId)->ignore($unit instanceof Unit ? $unit->id : null),
            ],
            'type' => ['required', Rule::enum(UnitType::class)],
            'monthly_rent' => ['required', 'numeric', 'min:1', 'max:100000'],
            'deposit' => ['required', 'numeric', 'min:0', 'max:100000'],
            // "occupied" is controlled by tenancies, not set by hand.
            'status' => ['sometimes', Rule::in([UnitStatus::Vacant->value, UnitStatus::Maintenance->value])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.unique' => 'This unit code already exists in this property.'];
    }
}
