<?php

namespace App\Http\Requests;

use App\Enums\TemplateRoleType;
use App\Models\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UseTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Template $template */
        $template = $this->route('template');

        return $this->user() !== null
            && $this->user()->can('view', $template);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_title' => ['required', 'string', 'max:255'],
            'assignees' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Template $template */
            $template = $this->route('template');
            $assignees = $this->input('assignees', []);

            foreach ($template->templateSigners()->where('role_type', TemplateRoleType::Signer)->orderBy('signing_order')->get() as $templateSigner) {
                $role = $templateSigner->role_name;
                $name = data_get($assignees, $role.'.name');
                $email = data_get($assignees, $role.'.email');
                if (! is_string($name) || $name === '') {
                    $validator->errors()->add('assignees.'.$role.'.name', __('Enter a name for :role.', ['role' => $role]));
                }
                if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('assignees.'.$role.'.email', __('Enter a valid email for :role.', ['role' => $role]));
                }
            }
        });
    }

    /**
     * @return array<string, array{name: string, email: string}>
     */
    public function validatedAssigneesByRole(Template $template): array
    {
        $signerRoleNames = $template->templateSigners()
            ->where('role_type', TemplateRoleType::Signer)
            ->orderBy('signing_order')
            ->pluck('role_name')
            ->all();

        /** @var array{assignees: array<string, array{name?: string, email?: string}>} $data */
        $data = $this->validated();
        $assignees = $data['assignees'];

        $out = [];
        foreach ($signerRoleNames as $roleName) {
            $out[$roleName] = [
                'name' => (string) data_get($assignees, $roleName.'.name'),
                'email' => (string) data_get($assignees, $roleName.'.email'),
            ];
        }

        return $out;
    }
}
