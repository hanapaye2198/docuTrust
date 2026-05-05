<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentSignatureRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $signatureImage = $this->input('signature_image');
        if (is_string($signatureImage) && $signatureImage !== '') {
            $this->merge([
                'signature_image' => trim($signatureImage),
            ]);
        }
    }

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
        return [
            'signature_field_id' => ['required', 'integer', 'exists:signature_fields,id'],
            'signature_image' => ['nullable', 'string', 'max:8000000', 'regex:/^data:image\/(png|jpeg|jpg|webp);base64,[A-Za-z0-9+\/=\r\n]+$/'],
            'submitted_value' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
