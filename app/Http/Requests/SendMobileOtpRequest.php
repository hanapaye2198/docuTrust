<?php

namespace App\Http\Requests;

use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;

class SendMobileOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<PhilippineMobileNumber|string>>
     */
    public function rules(): array
    {
        return [
            'mobile_number' => ['required', 'string', 'max:32', new PhilippineMobileNumber],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile_number.required' => __('Enter your mobile number to receive a verification code.'),
        ];
    }
}
