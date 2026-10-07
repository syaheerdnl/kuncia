<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use App\Models\Property;
use App\Support\MalaysianStates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by create + update. Authorization depends on whether a property is bound.
 */
class PropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            ? (bool) $this->user()?->can('update', $property)
            : (bool) $this->user()?->can('create', Property::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(PropertyType::class)],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', Rule::in(MalaysianStates::ALL)],
            'postcode' => ['required', 'digits:5'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
