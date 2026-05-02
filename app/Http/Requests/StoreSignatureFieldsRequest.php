<?php

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSignatureFieldsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = $this->input('fields');
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            if (is_array($decoded)) {
                $this->merge(['fields' => $decoded]);
            }
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Document $document */
        $document = $this->route('document');

        return $this->user() !== null
            && $this->user()->can('update', $document)
            && $document->status === DocumentStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*.signer_id' => ['required', 'integer', 'exists:document_signers,id'],
            'fields.*.type' => ['required', new Enum(SignatureFieldType::class)],
            'fields.*.page_number' => ['required', 'integer', 'min:1'],
            'fields.*.position_data' => ['required', 'array'],
            'fields.*.position_data.x' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.y' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.width' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.height' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
