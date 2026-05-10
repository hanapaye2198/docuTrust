<?php

namespace App\Http\Controllers;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use App\Http\Requests\UseTemplateRequest;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateUseController extends Controller
{
    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function store(UseTemplateRequest $request, Template $template): RedirectResponse
    {
        $assigneesByRole = $request->validatedAssigneesByRole($template);
        $documentTitle = $request->validated('document_title');
        $accessPassword = trim((string) $request->validated('access_password', ''));
        $accessPasswordHint = trim((string) $request->validated('access_password_hint', ''));
        $signingMethod = $this->resolveSigningMethod($template);

        $sourcePath = $template->primaryPdfPath() ?? ($template->files[0] ?? null);
        if ($sourcePath === null || ! Storage::disk('public')->exists($sourcePath)) {
            abort(422, __('Template has no document file.'));
        }

        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'pdf';
        $newPath = 'documents/'.Str::uuid()->toString().'.'.$extension;

        $document = DB::transaction(function () use ($template, $assigneesByRole, $documentTitle, $newPath, $sourcePath, $accessPassword, $accessPasswordHint, $signingMethod) {
            $sourceDisk = Storage::disk('public');
            $targetDisk = Storage::disk($this->secureDiskName());

            if (! $sourceDisk->exists($sourcePath)) {
                abort(422, __('Template has no document file.'));
            }

            if (! $targetDisk->put($newPath, $sourceDisk->get($sourcePath))) {
                abort(500, __('Could not copy template file.'));
            }

            $document = Auth::user()->documents()->create([
                'title' => $documentTitle,
                'file_path' => $newPath,
                'email_subject' => $template->email_subject,
                'email_message' => $template->email_message,
                'audit_enabled' => $template->audit_enabled,
                'audit_settings' => $template->audit_settings,
                'access_password_hash' => $accessPassword !== '' ? Hash::make($accessPassword) : null,
                'access_password_hint' => $accessPasswordHint !== '' ? $accessPasswordHint : null,
                'signing_workflow' => $template->document_workflow
                    ? Document::SIGNING_WORKFLOW_SEQUENTIAL
                    : Document::SIGNING_WORKFLOW_PARALLEL,
                'status' => DocumentStatus::Draft,
            ]);

            $signerIdByRole = [];

            foreach ($template->templateSigners()->orderBy('signing_order')->get() as $templateSigner) {
                if (! in_array($templateSigner->role_type->value, TemplateRoleType::activeValues(), true)) {
                    continue;
                }

                $assignee = $assigneesByRole[$templateSigner->role_name] ?? null;
                if ($assignee === null) {
                    abort(422, __('Missing assignee for role: :role', ['role' => $templateSigner->role_name]));
                }

                $normalizedEmail = strtolower($assignee['email']);
                $linkedUserId = $this->resolveSignerUserId($template, $normalizedEmail, $signingMethod, $templateSigner->role_type);

                $signer = DocumentSigner::query()->create([
                    'document_id' => $document->id,
                    'role_name' => $templateSigner->role_name,
                    'role_type' => $templateSigner->role_type,
                    'name' => $assignee['name'],
                    'email' => $normalizedEmail,
                    'signing_method' => $signingMethod,
                    'user_id' => $linkedUserId,
                    'access_token' => (string) Str::uuid(),
                    'status' => DocumentSignerStatus::Pending,
                    'signing_order' => $templateSigner->signing_order,
                    'signed_at' => null,
                    'expires_at' => null,
                ]);

                if ($templateSigner->role_type === TemplateRoleType::Signer) {
                    $signerIdByRole[$templateSigner->role_name] = $signer->id;
                }
            }

            foreach ($template->templateFields as $templateField) {
                if (! array_key_exists($templateField->role_name, $signerIdByRole)) {
                    abort(422, __('Template field references unknown role: :role', ['role' => $templateField->role_name]));
                }

                $signerId = $signerIdByRole[$templateField->role_name];

                SignatureField::query()->create([
                    'document_id' => $document->id,
                    'signer_id' => $signerId,
                    'type' => $templateField->type,
                    'position_data' => $templateField->position_data,
                ]);
            }

            return $document;
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('status', __('Document created from template.'));
    }

    private function resolveSigningMethod(Template $template): SigningMethod
    {
        $method = $template->signing_method instanceof TemplateSigningMethod
            ? $template->signing_method
            : TemplateSigningMethod::AccountVerified;

        return match ($method) {
            TemplateSigningMethod::EmailLink => SigningMethod::EmailLink,
            TemplateSigningMethod::AccountVerified => SigningMethod::AccountVerified,
            TemplateSigningMethod::PkiCertificate => SigningMethod::PkiCertificate,
        };
    }

    private function resolveSignerUserId(Template $template, string $email, SigningMethod $signingMethod, TemplateRoleType $roleType): ?int
    {
        if ($signingMethod !== SigningMethod::AccountVerified || $roleType === TemplateRoleType::Recipient) {
            return null;
        }

        return User::query()
            ->where('organization_id', $template->organization_id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNotNull('email_verified_at')
            ->value('id');
    }
}
