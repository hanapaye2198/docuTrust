<?php

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use setasign\Fpdi\Fpdi;
use Throwable;

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

        if ($this->user() === null || ! $this->user()->can('update', $document)) {
            return false;
        }

        // Allow Draft status (normal flow)
        if ($document->status === DocumentStatus::Draft) {
            return true;
        }

        // Allow Pending status for eNOTARY attorney signing phase
        if ($document->status === DocumentStatus::Pending
            && $document->notary_request_id !== null
            && $this->user()->role->value === 'notary'
            && (int) $document->notaryRequest->notary_user_id === (int) $this->user()->id) {
            return true;
        }

        return false;
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Document $document */
            $document = $this->route('document');
            $fields = $this->validatedFields();

            foreach ($fields as $index => $field) {
                $position = $field['position_data'] ?? [];
                $x = (float) ($position['x'] ?? 0);
                $y = (float) ($position['y'] ?? 0);
                $width = (float) ($position['width'] ?? 0);
                $height = (float) ($position['height'] ?? 0);

                if ($width <= 0.0001 || $height <= 0.0001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field size must be greater than zero.'));
                }

                if (($x + $width) > 1.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the right edge of the page.'));
                }

                if (($y + $height) > 1.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the bottom edge of the page.'));
                }

                if (! $document->documentSigners()->whereKey((int) $field['signer_id'])->exists()) {
                    $validator->errors()->add("fields.$index.signer_id", __('Invalid signer for this document.'));
                }
            }

            $pageCount = $this->resolveDocumentPageCount($document);
            if ($pageCount === null) {
                return;
            }

            foreach ($fields as $index => $field) {
                $pageNumber = (int) ($field['page_number'] ?? 0);
                if ($pageNumber < 1 || $pageNumber > $pageCount) {
                    $validator->errors()->add(
                        "fields.$index.page_number",
                        __('Field references page :page, but the document only has :count page(s).', [
                            'page' => $pageNumber,
                            'count' => $pageCount,
                        ])
                    );
                }
            }
        });
    }

    /**
     * @return array<int, array{signer_id:int, type:string, page_number:int, position_data:array{x:float,y:float,width:float,height:float}}>
     */
    private function validatedFields(): array
    {
        $fields = $this->input('fields', []);

        return is_array($fields) ? $fields : [];
    }

    private function resolveDocumentPageCount(Document $document): ?int
    {
        $path = $document->sourcePdfPath();
        if (! is_string($path) || $path === '') {
            return null;
        }

        $disk = Storage::disk((string) config('filesystems.docutrust_disk', 'local'));
        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $pdf = new Fpdi;

            return $pdf->setSourceFile($disk->path($path));
        } catch (Throwable) {
            return null;
        }
    }
}
