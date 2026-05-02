<?php

namespace App\Http\Requests;

use App\Enums\SignatureFieldType;
use App\Enums\TemplateRoleType;
use App\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTemplateFieldsRequest extends FormRequest
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

    public function authorize(): bool
    {
        /** @var Template $template */
        $template = $this->route('template');

        return $this->user() !== null
            && $this->user()->can('update', $template);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*.role_name' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', new Enum(SignatureFieldType::class)],
            'fields.*.position_data' => ['required', 'array'],
            'fields.*.position_data.x' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.y' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.width' => ['required', 'numeric', 'between:0,1'],
            'fields.*.position_data.height' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    /**
     * @return array<int, array{role_name: string, type: SignatureFieldType, position_data: array{x: float, y: float, width: float, height: float}}>
     */
    public function validatedFieldsForTemplate(Template $template): array
    {
        /** @var array{fields: array<int, array<string, mixed>>} $validated */
        $validated = $this->validated();

        $allowedRoles = $template->templateSigners()
            ->where('role_type', TemplateRoleType::Signer)
            ->pluck('role_name')
            ->all();

        $out = [];
        foreach ($validated['fields'] as $field) {
            $roleName = $field['role_name'];
            if (! in_array($roleName, $allowedRoles, true)) {
                abort(422, __('Invalid role for a signature field.'));
            }
            $type = $field['type'] instanceof SignatureFieldType
                ? $field['type']
                : SignatureFieldType::from((string) $field['type']);

            $out[] = [
                'role_name' => $roleName,
                'type' => $type,
                'position_data' => $field['position_data'],
            ];
        }

        return $out;
    }
}
