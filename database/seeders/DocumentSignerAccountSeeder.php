<?php

namespace Database\Seeders;

use App\Enums\DocumentStatus;
use App\Enums\EkycStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\UserRole;
use App\Enums\UserWorkspace;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo accounts for the document signing workspace only.
 *
 * Run: php artisan db:seed --class=DocumentSignerAccountSeeder
 */
class DocumentSignerAccountSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string, mobile: string}>
     */
    private const ACCOUNTS = [
        [
            'email' => 'docusigner1@docutrust.tech',
            'name' => 'Doc Signer One',
            'mobile' => '+639171000101',
        ],
        [
            'email' => 'docusigner2@docutrust.tech',
            'name' => 'Doc Signer Two',
            'mobile' => '+639171000102',
        ],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $index => $account) {
            $user = User::query()->updateOrCreate([
                'email' => $account['email'],
            ], [
                'name' => $account['name'],
                'password' => DatabaseSeeder::DEMO_PASSWORD,
                'email_verified_at' => now(),
                'role' => UserRole::Client,
                'workspace' => UserWorkspace::Signing,
                'organization_role' => OrganizationRole::Member,
                'onboarding_step' => OnboardingStep::Completed,
                'ekyc_status' => EkycStatus::Verified,
                'mfa_enabled' => true,
                'two_factor_enabled' => false,
                'two_factor_onboarding_completed_at' => now(),
                'mobile_number' => $account['mobile'],
                'mobile_verified_at' => now(),
            ]);

            if ($index === 1) {
                $this->seedSampleDocuments($user);
            }

            $this->command?->info("Document signer: {$account['email']} / ".DatabaseSeeder::DEMO_PASSWORD);
        }
    }

    private function seedSampleDocuments(User $user): void
    {
        $documents = [
            ['title' => 'Consulting Agreement', 'status' => DocumentStatus::Pending],
            ['title' => 'Statement of Work', 'status' => DocumentStatus::Draft],
        ];

        foreach ($documents as $index => $payload) {
            $filePath = sprintf('documents/docusigner2-demo-%02d.pdf', $index + 1);
            $this->storeDemoPdf($filePath);

            Document::query()->firstOrCreate([
                'user_id' => $user->id,
                'title' => $payload['title'],
            ], [
                'file_path' => $filePath,
                'files' => [$filePath],
                'status' => $payload['status'],
                'sent_at' => $payload['status'] === DocumentStatus::Pending
                    ? now()->subDay()
                    : null,
            ]);
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
(DocuTrust Document) Tj
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
