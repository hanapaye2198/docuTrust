<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        if (is_string($title)) {
            $this->merge([
                'title' => trim(strip_tags($title)),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesForUpload();
    }

    /**
     * Shared rules for Livewire upload validation.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesForUpload(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'extensions:pdf', 'max:51200'],
        ];
    }
}
