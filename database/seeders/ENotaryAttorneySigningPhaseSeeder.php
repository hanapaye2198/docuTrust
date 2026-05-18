<?php

namespace Database\Seeders;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\OnboardingStep;
use App\Enums\OrganizationRole;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ENotaryAttorneySigningPhaseSeeder extends Seeder
{
    private const SIGNATURE_PNG_DATA = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function run(): void
    {
        $notary = User::query()->updateOrCreate([
            'email' => 'atty-phase-notary@docutrust.test',
        ], [
            'name' => 'Atty. Phase Tester',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::Notary,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639171111111',
            'mobile_verified_at' => now(),
        ]);

        $client = User::query()->updateOrCreate([
            'email' => 'atty-phase-client@docutrust.test',
        ], [
            'organization_id' => $notary->organization_id,
            'name' => 'Client Phase Tester',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::Client,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => false,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639172222222',
            'mobile_verified_at' => now(),
        ]);

        $request = NotaryRequest::query()->updateOrCreate([
            'title' => 'Attorney Signing Phase Demo Request',
        ], [
            'organization_id' => $notary->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'status' => NotaryRequestStatus::AttorneyApproved,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subHours(6),
            'id_document_type' => 'Passport',
            'id_document_number' => 'PH-ATTY-PHASE-001',
            'id_document_path' => 'identity/attorney-phase-passport.pdf',
            'selfie_path' => 'identity/attorney-phase-selfie.jpg',
            'identity_verified_at' => now()->subDays(2),
            'location_verified_at' => now()->subDays(2),
            'location_ip_address' => '120.28.45.100',
            'location_country_code' => 'PH',
            'location_latitude' => 7.0731,
            'location_longitude' => 125.6128,
            'location_vpn_detected' => false,
            'metadata' => [
                'notes' => 'Seeded for fast testing of the attorney signing phase.',
            ],
        ]);

        $documentPath = 'documents/attorney-signing-phase-demo.pdf';
        $signaturePath = 'signatures/attorney-signing-phase-client.png';

        $this->ensureDemoPdfExists($documentPath);
        $this->ensureSignaturePngExists($signaturePath);

        $document = Document::query()->updateOrCreate([
            'title' => 'Attorney Signing Phase Demo Document',
            'notary_request_id' => $request->id,
        ], [
            'organization_id' => $notary->organization_id,
            'user_id' => $client->id,
            'file_path' => $documentPath,
            'status' => DocumentStatus::Pending,
            'sent_at' => now()->subDay(),
            'signing_workflow' => Document::SIGNING_WORKFLOW_SEQUENTIAL,
            'audit_enabled' => true,
        ]);

        $clientSigner = DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => 'phase-signer-1@docutrust.test',
        ], [
            'name' => 'Completed Client Signer',
            'role_name' => 'Principal',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::EmailLink,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
            'signed_at' => now()->subHours(12),
            'access_token' => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);

        $attorneySigner = DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => $notary->email,
        ], [
            'name' => $notary->name,
            'role_name' => 'Notary',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $notary->id,
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
            'signed_at' => null,
            'access_token' => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);

        $clientField = SignatureField::query()->updateOrCreate([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
            'page_number' => 1,
            'type' => 'signature',
        ], [
            'position_data' => [
                'x' => 0.12,
                'y' => 0.68,
                'width' => 0.24,
                'height' => 0.06,
            ],
        ]);

        SignatureField::query()->updateOrCreate([
            'document_id' => $document->id,
            'signer_id' => $attorneySigner->id,
            'page_number' => 1,
            'type' => 'signature',
        ], [
            'position_data' => [
                'x' => 0.58,
                'y' => 0.68,
                'width' => 0.24,
                'height' => 0.06,
            ],
        ]);

        Signature::query()->updateOrCreate([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
            'signature_field_id' => $clientField->id,
        ], [
            'signature_path' => $signaturePath,
            'submitted_value' => null,
            'signature_value' => null,
            'signature_hash' => hash('sha256', 'attorney-signing-phase-demo-'.$document->id),
            'public_key_fingerprint' => null,
            'signature_algorithm' => 'RSA-SHA256',
            'position_data' => null,
        ]);

        NotarySession::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'room_name' => 'attorney-signing-phase-demo-room',
        ], [
            'provider_name' => 'manual',
            'status' => 'completed',
            'meeting_url' => 'https://meet.docutrust.test/attorney-signing-phase-demo',
            'host_reference' => (string) Str::uuid(),
            'scheduled_for' => now()->subDay(),
            'started_at' => now()->subDay()->addMinutes(5),
            'ended_at' => now()->subDay()->addMinutes(25),
            'signer_confirmed' => true,
            'signer_confirmed_at' => now()->subDay()->subHour(),
            'verification_checklist' => [
                'face_matches_id' => true,
                'id_valid_not_expired' => true,
                'signer_conscious_aware' => true,
                'signer_agrees_voluntarily' => true,
                'signer_in_philippines' => true,
                'id_shown_on_camera' => true,
            ],
        ]);

        $this->command?->info('Seeded attorney signing phase e-notary demo data.');
        $this->command?->line('Notary login: atty-phase-notary@docutrust.test / password');
        $this->command?->line('Client login: atty-phase-client@docutrust.test / password');
        $this->command?->line(sprintf('Request ID: %d | Document ID: %d | Attorney signer ID: %d', $request->id, $document->id, $attorneySigner->id));
    }

    private function ensureDemoPdfExists(string $path): void
    {
        $disk = Storage::disk((string) config('filesystems.docutrust_disk', 'local'));

        if ($disk->exists($path)) {
            return;
        }

        $pdf = Pdf::loadHTML(<<<HTML
<html>
<body style="font-family: DejaVu Sans, sans-serif; font-size: 14px; padding: 32px;">
    <h1>Attorney Signing Phase Demo</h1>
    <p>This PDF is seeded for fast testing of the e-notary attorney signing phase.</p>
    <p>The client signer field is placed on the lower left.</p>
    <p>The attorney signer field is placed on the lower right.</p>
</body>
</html>
HTML)->output();

        $disk->put($path, $pdf);
    }

    private function ensureSignaturePngExists(string $path): void
    {
        $disk = Storage::disk((string) config('filesystems.docutrust_disk', 'local'));

        if ($disk->exists($path)) {
            return;
        }

        $disk->put($path, base64_decode(self::SIGNATURE_PNG_DATA, true));
    }
}
