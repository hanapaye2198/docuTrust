<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotaryAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::livewireRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function livewireRules(): array
    {
        return [
            'selectedSlotId' => ['required', 'integer', Rule::exists('notary_availability_slots', 'id')],
            'notaryRequestId' => ['nullable', 'integer', Rule::exists('notary_requests', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
