<?php

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'fields.*.type' => ['required', Rule::in(SignatureFieldType::placeableValues())],
            'fields.*.page_number' => ['required', 'integer', 'min:1'],
            'fields.*.position_data' => ['required', 'array'],
            'fields.*.position_data.x' => ['required', 'numeric'],
            'fields.*.position_data.y' => ['required', 'numeric'],
            'fields.*.position_data.width' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.height' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.angle' => ['sometimes', 'numeric', 'between:0,360'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Document $document */
            $document = $this->route('document');
            $fields = $this->validatedFields();
            $signatureFieldsBySignerPage = [];
            $sealFieldsBySignerPage = [];

            foreach ($fields as $index => $field) {
                $position = $field['position_data'] ?? [];
                $x = (float) ($position['x'] ?? 0);
                $y = (float) ($position['y'] ?? 0);
                $width = (float) ($position['width'] ?? 0);
                $height = (float) ($position['height'] ?? 0);
                $angle = (float) ($position['angle'] ?? 0);

                if ($width <= 0.0001 || $height <= 0.0001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field size must be greater than zero.'));
                }

                [$minX, $minY, $maxX, $maxY] = $this->resolveFieldBounds($x, $y, $width, $height, $angle);

                if ($minX < -0.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the left edge of the page.'));
                }

                if ($minY < -0.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the top edge of the page.'));
                }

                if ($maxX > 1.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the right edge of the page.'));
                }

                if ($maxY > 1.000001) {
                    $validator->errors()->add("fields.$index.position_data", __('Field extends past the bottom edge of the page.'));
                }

                $signer = $document->documentSigners()->whereKey((int) $field['signer_id'])->first();

                if (! $signer) {
                    $validator->errors()->add("fields.$index.signer_id", __('Invalid signer for this document.'));

                    continue;
                }

                // Validate page is within signer's allowed pages
                $pageNumber = (int) ($field['page_number'] ?? 0);
                $fieldType = (string) ($field['type'] ?? '');
                if ($this->isSignaturePlacementType($fieldType)) {
                    $signatureKey = ((int) $field['signer_id']).':'.$pageNumber;

                    if (isset($signatureFieldsBySignerPage[$signatureKey])) {
                        $validator->errors()->add(
                            "fields.$index.type",
                            __('Each signer can only have one signature field per page.')
                        );
                    }

                    $signatureFieldsBySignerPage[$signatureKey] = true;
                }

                if ($fieldType === SignatureFieldType::Seal->value) {
                    $sealKey = ((int) $field['signer_id']).':'.$pageNumber;

                    if (isset($sealFieldsBySignerPage[$sealKey])) {
                        $validator->errors()->add(
                            "fields.$index.type",
                            __('Each signer can only have one seal field per page.')
                        );
                    }

                    $sealFieldsBySignerPage[$sealKey] = true;
                }

                if (! $signer->isAllowedOnPage($pageNumber)) {
                    $validator->errors()->add(
                        "fields.$index.page_number",
                        __('Signer ":name" is not assigned to page :page.', [
                            'name' => $signer->name,
                            'page' => $pageNumber,
                        ])
                    );
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

    private function isSignaturePlacementType(string $type): bool
    {
        return in_array($type, [
            SignatureFieldType::Signature->value,
            SignatureFieldType::SignatureLeft->value,
            SignatureFieldType::SignatureRight->value,
        ], true);
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

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function resolveFieldBounds(float $x, float $y, float $width, float $height, float $angle): array
    {
        if (abs($angle) < 0.01) {
            return [$x, $y, $x + $width, $y + $height];
        }

        $centerX = $x + ($width / 2);
        $centerY = $y + ($height / 2);
        $radians = deg2rad($angle);
        $cos = cos($radians);
        $sin = sin($radians);
        $halfWidth = $width / 2;
        $halfHeight = $height / 2;
        $corners = [
            [-$halfWidth, -$halfHeight],
            [$halfWidth, -$halfHeight],
            [$halfWidth, $halfHeight],
            [-$halfWidth, $halfHeight],
        ];

        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        foreach ($corners as [$cornerX, $cornerY]) {
            $rotatedX = ($cornerX * $cos) - ($cornerY * $sin) + $centerX;
            $rotatedY = ($cornerX * $sin) + ($cornerY * $cos) + $centerY;
            $minX = min($minX, $rotatedX);
            $minY = min($minY, $rotatedY);
            $maxX = max($maxX, $rotatedX);
            $maxY = max($maxY, $rotatedY);
        }

        return [$minX, $minY, $maxX, $maxY];
    }
}
