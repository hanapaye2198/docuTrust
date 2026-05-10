<?php

namespace Database\Seeders;

use App\Enums\SignatureFieldType;
use App\Enums\TemplateRoleType;
use App\Enums\TemplateSigningMethod;
use App\Models\Tag;
use App\Models\Template;
use App\Models\TemplateField;
use App\Models\TemplateSigner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'demo@docutrust.com')->firstOrFail();

        $tagByName = collect(['HR', 'Legal', 'Sales', 'Operations'])
            ->mapWithKeys(fn (string $name) => [
                $name => Tag::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'name' => $name,
                ]),
            ]);

        $templates = [
            [
                'name' => 'CV Template',
                'file' => 'templates/cv-template.pdf',
                'subject' => 'Please review and sign this CV package',
                'message' => 'Please complete your signature so we can finalize this profile.',
                'roles' => ['Candidate', 'Recruiter'],
                'tags' => ['HR'],
            ],
            [
                'name' => 'Contract Template',
                'file' => 'templates/contract-template.pdf',
                'subject' => 'Contract ready for signature',
                'message' => 'This contract is ready for your review and signature.',
                'roles' => ['Client', 'Company Representative', 'Legal Reviewer'],
                'tags' => ['Legal', 'Sales'],
            ],
            [
                'name' => 'Agreement Template',
                'file' => 'templates/agreement-template.pdf',
                'subject' => 'Agreement requires your signature',
                'message' => 'Please review the agreement terms and sign at your earliest convenience.',
                'roles' => ['Partner A', 'Partner B'],
                'tags' => ['Legal', 'Operations'],
            ],
        ];

        foreach ($templates as $index => $payload) {
            $this->storeDemoPdf($payload['file']);

            $template = Template::query()->create([
                'user_id' => $user->id,
                'name' => $payload['name'],
                'files' => [$payload['file']],
                'document_workflow' => true,
                'email_subject' => $payload['subject'],
                'email_message' => $payload['message'],
                'signing_method' => TemplateSigningMethod::AccountVerified,
                'audit_enabled' => true,
                'audit_settings' => Template::defaultAuditSettings(),
            ]);

            foreach ($payload['roles'] as $roleIndex => $roleName) {
                TemplateSigner::query()->create([
                    'template_id' => $template->id,
                    'role_name' => $roleName,
                    'role_type' => TemplateRoleType::Signer,
                    'signing_order' => $roleIndex + 1,
                ]);
            }

            TemplateField::query()->create([
                'template_id' => $template->id,
                'role_name' => $payload['roles'][0],
                'type' => SignatureFieldType::Signature,
                'position_data' => [
                    'page' => 1,
                    'x' => 120 + ($index * 10),
                    'y' => 520,
                    'width' => 180,
                    'height' => 40,
                ],
            ]);

            $template->tags()->sync(
                collect($payload['tags'])
                    ->map(fn (string $tagName) => $tagByName[$tagName]->id)
                    ->all()
            );
        }
    }

    private function storeDemoPdf(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            return;
        }

        $content = <<<'PDF'
%PDF-1.1
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 69 >>
stream
BT
/F1 24 Tf
72 720 Td
(DocuTrust Template) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f
0000000010 00000 n
0000000063 00000 n
0000000120 00000 n
0000000246 00000 n
0000000375 00000 n
trailer
<< /Size 6 /Root 1 0 R >>
startxref
445
%%EOF
PDF;

        Storage::disk('public')->put($path, $content);
    }
}
