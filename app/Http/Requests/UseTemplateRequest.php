<?php

namespace App\Http\Requests;

use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use App\Models\Template;
use App\Models\User;
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
            'access_password' => ['nullable', 'string', 'min:6', 'max:255', 'same:access_password_confirmation'],
            'access_password_confirmation' => ['nullable', 'string', 'max:255'],
            'access_password_hint' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Template $template */
            $template = $this->route('template');
            $assignees = $this->input('assignees', []);
            $requiresExistingAccount = $template->signing_method === TemplateSigningMethod::AccountVerified;

            foreach ($template->templateSigners()->whereIn('role_type', TemplateRoleType::activeValues())->orderBy('signing_order')->get() as $templateSigner) {
                $role = $templateSigner->role_name;
                $name = data_get($assignees, $role.'.name');
                $email = data_get($assignees, $role.'.email');
                if (! is_string($name) || $name === '') {
                    $validator->errors()->add('assignees.'.$role.'.name', __('Enter a name for :role.', ['role' => $role]));
                }
                if (! is_string($email) || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add('assignees.'.$role.'.email', __('Enter a valid email for :role.', ['role' => $role]));

                    continue;
                }

                if ($requiresExistingAccount && $templateSigner->role_type !== TemplateRoleType::Recipient) {
                    $linkedUserExists = User::query()
                        ->where('organization_id', $template->organization_id)
                        ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                        ->whereNotNull('email_verified_at')
                        ->exists();

                    if (! $linkedUserExists) {
                        $validator->errors()->add(
                            'assignees.'.$role.'.email',
                            __('This signer method requires an existing verified DocuTrust account in your organization.')
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, array{name: string, email: string}>
     */
    public function validatedAssigneesByRole(Template $template): array
    {
        $participantRoleNames = $template->templateSigners()
            ->whereIn('role_type', TemplateRoleType::activeValues())
            ->orderBy('signing_order')
            ->pluck('role_name')
            ->all();

        /** @var array{assignees: array<string, array{name?: string, email?: string}>} $data */
        $data = $this->validated();
        $assignees = $data['assignees'];

        $out = [];
        foreach ($participantRoleNames as $roleName) {
            $out[$roleName] = [
                'name' => (string) data_get($assignees, $roleName.'.name'),
                'email' => (string) data_get($assignees, $roleName.'.email'),
            ];
        }

        return $out;
    }
}
