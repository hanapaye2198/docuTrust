<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotaryAvailabilitySlotRequest extends FormRequest
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
            'selectedDate' => ['required', 'date', 'after_or_equal:today'],
            'newSlotStartTime' => ['required', 'date_format:H:i'],
            'newSlotEndTime' => ['required', 'date_format:H:i', 'after:newSlotStartTime'],
            'newSlotDuration' => ['required', 'integer', 'min:30', 'max:180'],
            'repeatWeeks' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
