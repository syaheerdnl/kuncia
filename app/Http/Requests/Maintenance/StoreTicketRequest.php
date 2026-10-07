<?php

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenancePriority;
use App\Models\MaintenanceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', MaintenanceRequest::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['required', Rule::enum(MaintenancePriority::class)],
            'photos' => ['nullable', 'array', 'max:3'],
            'photos.*' => ['image', 'max:5120'],
        ];
    }
}
