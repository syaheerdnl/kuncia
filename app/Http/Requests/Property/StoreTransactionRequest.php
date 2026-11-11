<?php

namespace App\Http\Requests\Property;

use App\Enums\TransactionType;
use App\Models\Property;
use App\Models\Tenancy;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Record a deposit received or money paid to a tenant. Route has {property}. */
class StoreTransactionRequest extends FormRequest
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
            // Only a tenancy on this property, so one landlord cannot write on another's.
            'tenancy_id' => [
                'required', 'integer',
                Rule::exists('tenancies', 'id')->where(
                    fn ($q) => $q->whereIn('unit_id', Unit::where('property_id', $property->id)->select('id')),
                ),
            ],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:150'],
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

                // The table keeps one row per tenancy, kind, day, amount and note.
                $exists = Tenancy::find($this->integer('tenancy_id'))?->transactions()
                    ->sameEntry(
                        $this->string('type')->value(),
                        $this->string('occurred_on')->value(),
                        $this->string('amount')->value(),
                        $this->filled('note') ? $this->string('note')->value() : null,
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('amount', 'This exact entry (same tenant, kind, date, amount and note) is already recorded.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tenancy_id.exists' => 'Pick a tenant of this property.',
            'occurred_on.before_or_equal' => 'The date cannot be in the future.',
        ];
    }
}
