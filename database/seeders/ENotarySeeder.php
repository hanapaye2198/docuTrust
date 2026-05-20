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
use App\Models\DocumentHash;
use App\Models\DocumentSigner;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use App\Models\NotaryJournal;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ENotarySeeder extends Seeder
{
    public function run(): void
    {
        // ─── Users ───────────────────────────────────────────────────────────

        $notary = User::query()->updateOrCreate([
            'email' => 'enotary@docutrust.com',
        ], [
            'name' => 'Atty. Maria Santos',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::Notary,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639171234567',
            'mobile_verified_at' => now(),
        ]);

        $notaryAdmin = User::query()->updateOrCreate([
            'email' => 'notaryadmin@docutrust.com',
        ], [
            'organization_id' => $notary->organization_id,
            'name' => 'Admin Reyes',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::NotaryAdmin,
            'organization_role' => OrganizationRole::Admin,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639179876543',
            'mobile_verified_at' => now(),
        ]);

        $client = User::query()->updateOrCreate([
            'email' => 'client@docutrust.com',
        ], [
            'organization_id' => $notary->organization_id,
            'name' => 'Juan Dela Cruz',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::Client,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639185551234',
            'mobile_verified_at' => now(),
        ]);

        $client2 = User::query()->updateOrCreate([
            'email' => 'client2@docutrust.com',
        ], [
            'organization_id' => $notary->organization_id,
            'name' => 'Ana Marie Garcia',
            'password' => 'password',
            'email_verified_at' => now(),
            'role' => UserRole::Client,
            'organization_role' => OrganizationRole::Member,
            'onboarding_step' => OnboardingStep::Completed,
            'mfa_enabled' => true,
            'two_factor_enabled' => false,
            'two_factor_onboarding_completed_at' => now(),
            'mobile_number' => '+639195559876',
            'mobile_verified_at' => now(),
        ]);

        // ─── Notary Credential ───────────────────────────────────────────────

        $credential = NotaryCredential::query()->updateOrCreate([
            'user_id' => $notary->id,
            'commission_number' => 'CN-2026-0001',
        ], [
            'commission_jurisdiction' => 'Davao City, Davao del Sur',
            'commission_issued_at' => now()->subMonths(6)->toDateString(),
            'commission_expires_at' => now()->addMonths(18)->toDateString(),
            'roll_number' => '67890',
            'ibp_number' => 'IBP-XI-2026-0042',
            'ptr_number' => 'PTR-2026-1234',
            'mcle_compliance_number' => 'MCLE-VII-2026-0015',
            'status' => 'active',
        ]);

        // ─── Request 1: Fully Notarized (complete workflow demo) ─────────────

        $request1 = $this->seedFullyNotarizedRequest(
            $client,
            $notary,
            $notaryAdmin,
            $credential,
        );

        // ─── Request 2: Attorney Approved (awaiting register entry) ──────────

        $this->seedApprovedRequest($client2, $notary, $credential);

        // ─── Request 3: Session Scheduled (awaiting video verification) ──────

        $this->seedScheduledRequest($client, $notary);

        // ─── Request 4: Submitted (awaiting identity verification) ───────────

        $this->seedSubmittedRequest($client2, $notary);

        // ─── Request 5: Draft (just created) ─────────────────────────────────

        $this->seedDraftRequest($client, $notary);
        $this->seedPaymentContinuationRequest($client2, $notary, $credential);

        $this->command->info('E-Notary seeder completed: 6 requests including fixed request #8 for payment testing.');
    }

    private function seedFullyNotarizedRequest(
        User $client,
        User $notary,
        User $notaryAdmin,
        NotaryCredential $credential,
    ): NotaryRequest {
        $request = NotaryRequest::query()->updateOrCreate([
            'title' => 'Affidavit of Self-Adjudication — Dela Cruz Property',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'status' => NotaryRequestStatus::Notarized,
            'submitted_at' => now()->subDays(5),
            'approved_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'id_document_type' => 'Passport',
            'id_document_number' => 'P1030912OB',
            'id_document_path' => 'identity/demo-passport.pdf',
            'selfie_path' => 'identity/demo-selfie.jpg',
            'identity_verified_at' => now()->subDays(4),
            'location_verified_at' => now()->subDays(4),
            'location_ip_address' => '120.28.45.100',
            'location_country_code' => 'PH',
            'location_latitude' => 7.0731,
            'location_longitude' => 125.6128,
            'location_vpn_detected' => false,
            'metadata' => [
                'notes' => 'Transfer of inherited property in Davao City. Client is sole heir.',
                'location_verification' => [
                    'verified_at' => now()->subDays(4)->toDateTimeString(),
                    'result' => 'verified',
                    'country_code' => 'PH',
                ],
            ],
        ]);

        // Document (completed with hash)
        $document = Document::query()->updateOrCreate([
            'title' => 'Affidavit of Self-Adjudication',
            'notary_request_id' => $request->id,
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'file_path' => 'documents/demo-affidavit.pdf',
            'status' => DocumentStatus::Completed,
            'sent_at' => now()->subDays(4),
            'signing_workflow' => 'sequential',
            'audit_enabled' => true,
            'final_pdf_path' => 'documents/demo-affidavit-final.pdf',
            'certificate_path' => 'certificates/demo-affidavit-cert.pdf',
        ]);

        // Document signer
        DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => $client->email,
        ], [
            'name' => $client->name,
            'role_name' => 'Affiant',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::AccountVerified,
            'user_id' => $client->id,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
            'signed_at' => now()->subDays(3),
            'access_token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays(7),
        ]);

        // Document hash (blockchain anchored)
        DocumentHash::query()->updateOrCreate([
            'document_id' => $document->id,
        ], [
            'hash' => hash('sha256', 'demo-affidavit-self-adjudication-'.$document->id),
            'transaction_id' => '0x'.Str::random(64),
            'created_at' => now()->subDays(2),
        ]);

        // Session (completed)
        NotarySession::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'room_name' => 'notary-room-001',
        ], [
            'provider_name' => 'manual',
            'status' => 'completed',
            'meeting_url' => 'https://meet.docutrust.com/notary-session-001',
            'host_reference' => Str::uuid()->toString(),
            'scheduled_for' => now()->subDays(3),
            'started_at' => now()->subDays(3)->addMinutes(5),
            'ended_at' => now()->subDays(3)->addMinutes(35),
            'signer_confirmed' => true,
            'signer_confirmed_at' => now()->subDays(3)->subHours(2),
            'verification_checklist' => [
                'face_matches_id' => true,
                'id_valid_not_expired' => true,
                'signer_conscious_aware' => true,
                'signer_agrees_voluntarily' => true,
                'signer_in_philippines' => true,
                'session_recorded' => true,
            ],
            'evidence' => [
                'session_duration_minutes' => 30,
                'notary_observations' => 'Signer appeared alert and aware. ID matched face. Confirmed voluntary execution.',
            ],
        ]);

        // Notarial Register Entry (all 9 fields)
        $entry = NotarialRegisterEntry::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'notary_credential_id' => $credential->id,
            'entry_number' => 1,
            'entry_year' => (int) now()->format('Y'),
        ], [
            'document_id' => $document->id,
            'page_number' => 1,
            'book_number' => 'I',
            'document_title' => 'Affidavit of Self-Adjudication',
            'document_description' => 'Transfer of inherited property rights — Lot 5, Block 12, Matina, Davao City',
            'parties' => [
                ['name' => 'Juan Dela Cruz', 'address' => '123 Rizal St., Matina, Davao City, Davao del Sur 8000'],
            ],
            'witnesses' => [
                ['name' => 'Pedro Reyes', 'address' => '456 Mabini Ave., Davao City'],
                ['name' => 'Rosa Lim', 'address' => '789 Bonifacio St., Davao City'],
            ],
            'competent_evidence' => [
                ['person_name' => 'Juan Dela Cruz', 'id_type' => 'Passport', 'id_number' => 'P1030912OB'],
            ],
            'notarized_at' => now()->subDays(2)->timezone('Asia/Manila'),
            'notarial_act_type' => 'acknowledgment',
            'fees' => 500.00,
            'official_receipt_number' => 'CR: 0001234',
            'notary_signature_path' => $credential->signature_image_path,
            'qr_verification_token' => Str::uuid()->toString(),
        ]);

        // Journal entries (audit trail)
        $this->seedJournalEntries($request, $notary);

        return $request;
    }

    private function seedApprovedRequest(User $client, User $notary, NotaryCredential $credential): NotaryRequest
    {
        $request = NotaryRequest::query()->updateOrCreate([
            'title' => 'Special Power of Attorney — Garcia to Santos',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'status' => NotaryRequestStatus::AttorneyApproved,
            'submitted_at' => now()->subDays(3),
            'approved_at' => now()->subDay(),
            'id_document_type' => 'PhilID',
            'id_document_number' => 'PHN-0012-3456-7890',
            'id_document_path' => 'identity/demo-philid.pdf',
            'selfie_path' => 'identity/demo-selfie-garcia.jpg',
            'identity_verified_at' => now()->subDays(2),
            'location_verified_at' => now()->subDays(2),
            'location_ip_address' => '49.145.72.30',
            'location_country_code' => 'PH',
            'location_latitude' => 14.5995,
            'location_longitude' => 120.9842,
            'location_vpn_detected' => false,
            'metadata' => [
                'notes' => 'SPA for property management. Principal is overseas-bound.',
            ],
        ]);

        // Document (completed, ready for register entry)
        $document = Document::query()->updateOrCreate([
            'title' => 'Special Power of Attorney',
            'notary_request_id' => $request->id,
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'file_path' => 'documents/demo-spa.pdf',
            'status' => DocumentStatus::Completed,
            'sent_at' => now()->subDays(3),
            'signing_workflow' => 'sequential',
            'audit_enabled' => true,
            'final_pdf_path' => 'documents/demo-spa-final.pdf',
            'certificate_path' => 'certificates/demo-spa-cert.pdf',
        ]);

        DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => $client->email,
        ], [
            'name' => $client->name,
            'role_name' => 'Principal',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::EmailLink,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
            'signed_at' => now()->subDays(2),
            'access_token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays(7),
        ]);

        DocumentHash::query()->updateOrCreate([
            'document_id' => $document->id,
        ], [
            'hash' => hash('sha256', 'demo-spa-garcia-'.$document->id),
            'transaction_id' => '0x'.Str::random(64),
            'created_at' => now()->subDay(),
        ]);

        // Completed session
        NotarySession::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'room_name' => 'notary-room-002',
        ], [
            'provider_name' => 'manual',
            'status' => 'completed',
            'meeting_url' => 'https://meet.docutrust.com/notary-session-002',
            'scheduled_for' => now()->subDays(2),
            'started_at' => now()->subDays(2)->addMinutes(3),
            'ended_at' => now()->subDays(2)->addMinutes(25),
            'signer_confirmed' => true,
            'signer_confirmed_at' => now()->subDays(2)->subHour(),
            'verification_checklist' => [
                'face_matches_id' => true,
                'id_valid_not_expired' => true,
                'signer_conscious_aware' => true,
                'signer_agrees_voluntarily' => true,
                'signer_in_philippines' => true,
                'session_recorded' => true,
            ],
        ]);

        return $request;
    }

    private function seedScheduledRequest(User $client, User $notary): NotaryRequest
    {
        $request = NotaryRequest::query()->updateOrCreate([
            'title' => 'Deed of Absolute Sale — Lot 8, Block 3, Tagum City',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'acknowledgment',
            'status' => NotaryRequestStatus::SessionScheduled,
            'submitted_at' => now()->subDays(2),
            'id_document_type' => 'Driver License',
            'id_document_number' => 'N01-12-345678',
            'id_document_path' => 'identity/demo-license.pdf',
            'selfie_path' => 'identity/demo-selfie-delacruz2.jpg',
            'identity_verified_at' => now()->subDay(),
            'location_verified_at' => now()->subDay(),
            'location_ip_address' => '120.28.100.55',
            'location_country_code' => 'PH',
            'location_latitude' => 7.4478,
            'location_longitude' => 125.8087,
            'location_vpn_detected' => false,
            'metadata' => [
                'notes' => 'Sale of residential lot. Both buyer and seller will attend the session.',
            ],
        ]);

        // Document (pending signatures)
        $document = Document::query()->updateOrCreate([
            'title' => 'Deed of Absolute Sale',
            'notary_request_id' => $request->id,
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'file_path' => 'documents/demo-deed-of-sale.pdf',
            'status' => DocumentStatus::Pending,
            'sent_at' => now()->subDay(),
            'signing_workflow' => 'sequential',
            'audit_enabled' => true,
        ]);

        DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => 'seller@example.com',
        ], [
            'name' => 'Juan Dela Cruz',
            'role_name' => 'Seller',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::EmailLink,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
            'signed_at' => now()->subHours(6),
            'access_token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays(7),
        ]);

        DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => 'buyer@example.com',
        ], [
            'name' => 'Roberto Lim',
            'role_name' => 'Buyer',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::EmailLink,
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 2,
            'access_token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays(7),
        ]);

        // Scheduled session (upcoming)
        NotarySession::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'room_name' => 'notary-room-003',
        ], [
            'provider_name' => 'manual',
            'status' => 'scheduled',
            'meeting_url' => 'https://meet.docutrust.com/notary-session-003',
            'scheduled_for' => now()->addDay()->setHour(14)->setMinute(0),
            'signer_confirmed' => true,
            'signer_confirmed_at' => now()->subHours(3),
        ]);

        return $request;
    }

    private function seedSubmittedRequest(User $client, User $notary): NotaryRequest
    {
        return NotaryRequest::query()->updateOrCreate([
            'title' => 'Jurat — Affidavit of Loss (SSS ID)',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'jurat',
            'status' => NotaryRequestStatus::Submitted,
            'submitted_at' => now()->subHours(6),
            'metadata' => [
                'notes' => 'Client lost SSS ID. Needs notarized affidavit of loss for replacement.',
            ],
        ]);
    }

    private function seedDraftRequest(User $client, User $notary): NotaryRequest
    {
        return NotaryRequest::query()->updateOrCreate([
            'title' => 'Oath — Affidavit of Support and Consent',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'request_type' => 'oath',
            'status' => NotaryRequestStatus::Draft,
            'metadata' => [
                'notes' => 'Parent providing affidavit of support for minor child travel abroad.',
                'created_from' => 'manual_form',
            ],
        ]);
    }

    private function seedPaymentContinuationRequest(
        User $client,
        User $notary,
        NotaryCredential $credential,
    ): NotaryRequest {
        $request = NotaryRequest::query()->find(8);

        if (! $request instanceof NotaryRequest) {
            $request = new NotaryRequest;
            $request->id = 8;
        }

        $request->forceFill([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
            'title' => 'test payment',
            'request_type' => 'acknowledgment',
            'status' => NotaryRequestStatus::AttorneySigning,
            'submitted_at' => now()->subMinutes(20),
            'approved_at' => null,
            'rejected_at' => null,
            'completed_at' => null,
            'rejection_reason' => null,
            'metadata' => [
                'created_from' => 'enotary_wizard',
            ],
            'document_path' => null,
            'remarks' => 'asa',
            'identity_verified_at' => null,
            'verified_at' => null,
            'location_verified_at' => null,
            'location_ip_address' => null,
            'location_country_code' => null,
            'location_latitude' => null,
            'location_longitude' => null,
            'location_vpn_detected' => null,
            'created_at' => now()->subMinutes(21),
            'updated_at' => now()->subMinutes(11),
        ])->save();

        $document = Document::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'title' => 'afawada',
        ], [
            'organization_id' => $client->organization_id,
            'user_id' => $notary->id,
            'file_path' => 'documents/demo-payment-request.pdf',
            'status' => DocumentStatus::Completed,
            'sent_at' => now()->subMinutes(13),
            'signing_workflow' => 'sequential',
            'prepared_pdf_path' => 'documents/generated/demo-payment-request-prepared.pdf',
            'final_pdf_path' => 'documents/generated/demo-payment-request-final.pdf',
            'certificate_path' => 'certificates/demo-payment-request-certificate.pdf',
            'archive_storage_disk' => 'local',
            'archive_document_path' => 'documents/generated/demo-payment-request-final.pdf',
            'archive_certificate_path' => 'certificates/demo-payment-request-certificate.pdf',
            'archived_at' => now()->subMinutes(11),
        ]);

        DocumentSigner::query()->updateOrCreate([
            'document_id' => $document->id,
            'email' => $client->email,
        ], [
            'name' => $client->name,
            'role_name' => 'Signer 1',
            'role_type' => TemplateRoleType::Signer,
            'signing_method' => SigningMethod::EmailLink,
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
            'signed_at' => now()->subMinutes(12),
            'access_token' => Str::uuid()->toString(),
            'expires_at' => now()->addDays(7),
        ]);

        \App\Models\NotarySigner::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'email' => 'hannah18.panaligan@gmail.com',
        ], [
            'full_name' => 'dfaada',
            'phone' => '+639276776528',
            'address' => 'Brgy. Matti, Digos City, Davao del Sur, 8002',
            'role' => 'signer',
        ]);

        NotarySession::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'room_name' => 'docutrust-8-ibgyuhapws',
        ], [
            'notary_user_id' => $notary->id,
            'provider_name' => 'jitsi',
            'status' => 'completed',
            'meeting_url' => 'https://8x8.vc/vpaas-magic-cookie-6f5394927a4a4904812f628ebbf691a3/docutrust-8-ibgyuhapws',
            'scheduled_for' => now()->subMinutes(12),
            'started_at' => now()->subMinutes(11),
            'ended_at' => now()->subMinutes(10),
            'verification_checklist' => [
                'face_matches_id' => true,
                'id_valid_not_expired' => true,
                'signer_conscious_aware' => true,
                'signer_agrees_voluntarily' => true,
                'signer_in_philippines' => true,
                'id_shown_on_camera' => true,
            ],
            'evidence' => [],
            'signer_confirmed' => false,
            'signer_confirmed_at' => null,
        ]);

        $entry = NotarialRegisterEntry::query()->updateOrCreate([
            'notary_request_id' => $request->id,
            'entry_number' => 5,
            'entry_year' => (int) now()->format('Y'),
        ], [
            'notary_credential_id' => $credential->id,
            'document_id' => $document->id,
            'page_number' => 1,
            'book_number' => '1',
            'document_title' => 'test payment',
            'document_description' => 'sefsfsfs',
            'parties' => [
                ['name' => 'sfsefs', 'address' => 'sefsefsfsf'],
            ],
            'witnesses' => [],
            'competent_evidence' => [
                ['person_name' => 'sfesfsf', 'id_type' => 'PRC ID', 'id_number' => '234234234'],
            ],
            'notarized_at' => now()->subMinutes(8)->timezone('Asia/Manila'),
            'notarial_act_type' => 'acknowledgment',
            'fees' => 1.00,
            'official_receipt_number' => '34343',
            'notary_signature_path' => $credential->signature_image_path,
            'qr_code_path' => null,
            'qr_verification_token' => 'f5369fb9-77d5-42f5-84d0-2a63fd51fb51',
        ]);

        \App\Models\Payment::query()->where('notary_request_id', $request->id)->delete();

        $journalEntries = [
            [
                'entry_type' => 'document_attached',
                'summary' => 'Linked document "afawada" to this notary request.',
                'legal_assertions' => [
                    'document_id' => $document->id,
                ],
                'recorded_at' => now()->subMinutes(14),
            ],
            [
                'entry_type' => 'session_scheduled',
                'summary' => 'Video session scheduled for '.now()->subMinutes(12)->format('M j, Y g:i A').' via jitsi.',
                'legal_assertions' => [
                    'scheduled_for' => now()->subMinutes(12)->toDateTimeString(),
                    'provider_name' => 'jitsi',
                    'meeting_url' => null,
                ],
                'recorded_at' => now()->subMinutes(13),
            ],
            [
                'entry_type' => 'session_started',
                'summary' => 'Live video verification session started.',
                'legal_assertions' => [
                    'started_at' => now()->subMinutes(11)->toDateTimeString(),
                    'provider_name' => 'jitsi',
                ],
                'recorded_at' => now()->subMinutes(11),
            ],
            [
                'entry_type' => 'session_completed',
                'summary' => 'Live video verification session completed.',
                'legal_assertions' => [
                    'ended_at' => now()->subMinutes(10)->toDateTimeString(),
                    'verification_checklist' => [
                        'face_matches_id' => true,
                        'id_valid_not_expired' => true,
                        'signer_conscious_aware' => true,
                        'signer_agrees_voluntarily' => true,
                        'signer_in_philippines' => true,
                        'id_shown_on_camera' => true,
                    ],
                    'all_checks_passed' => true,
                ],
                'recorded_at' => now()->subMinutes(10),
            ],
            [
                'entry_type' => 'register_entry_created',
                'summary' => 'Notarial register entry 005 created for "test payment" (acknowledgment).',
                'legal_assertions' => [
                    'entry_number' => $entry->entry_number,
                    'entry_year' => $entry->entry_year,
                    'notarial_act_type' => $entry->notarial_act_type,
                    'parties_count' => count($entry->parties ?? []),
                    'witnesses_count' => count($entry->witnesses ?? []),
                    'fees' => 1.00,
                    'official_receipt_number' => '34343',
                    'verification_token' => $entry->qr_verification_token,
                ],
                'recorded_at' => now()->subMinutes(8),
            ],
        ];

        foreach ($journalEntries as $journalEntry) {
            NotaryJournal::query()->updateOrCreate([
                'notary_request_id' => $request->id,
                'entry_type' => $journalEntry['entry_type'],
                'recorded_at' => $journalEntry['recorded_at'],
            ], [
                'notary_user_id' => $notary->id,
                'summary' => $journalEntry['summary'],
                'legal_assertions' => $journalEntry['legal_assertions'],
            ]);
        }

        return $request;
    }

    private function seedJournalEntries(NotaryRequest $request, User $notary): void
    {
        $entries = [
            [
                'entry_type' => 'submission',
                'summary' => 'Notary request submitted for review.',
                'legal_assertions' => ['submitted_by' => 'client'],
                'recorded_at' => now()->subDays(5),
            ],
            [
                'entry_type' => 'identity_verification',
                'summary' => 'Identity verification completed. Document type: Passport, Number: P1030912OB',
                'legal_assertions' => [
                    'identity_verified' => true,
                    'id_document_type' => 'Passport',
                    'id_document_number' => 'P1030912OB',
                    'selfie_captured' => true,
                    'otp_verified' => true,
                ],
                'recorded_at' => now()->subDays(4),
            ],
            [
                'entry_type' => 'location_verification',
                'summary' => 'Location verification completed. Country: PH, VPN detected: No',
                'legal_assertions' => [
                    'location_verified' => true,
                    'country_code' => 'PH',
                    'within_jurisdiction' => true,
                    'vpn_detected' => false,
                    'ip_address' => '120.28.45.100',
                    'coordinates' => ['latitude' => 7.0731, 'longitude' => 125.6128],
                ],
                'recorded_at' => now()->subDays(4)->addMinutes(5),
            ],
            [
                'entry_type' => 'session_scheduled',
                'summary' => 'Video session scheduled for '.now()->subDays(3)->format('M j, Y g:i A').' via manual.',
                'legal_assertions' => [
                    'scheduled_for' => now()->subDays(3)->toDateTimeString(),
                    'provider_name' => 'manual',
                    'meeting_url' => 'https://meet.docutrust.com/notary-session-001',
                ],
                'recorded_at' => now()->subDays(4)->addMinutes(10),
            ],
            [
                'entry_type' => 'session_confirmed',
                'summary' => 'Signer confirmed attendance for the scheduled session.',
                'legal_assertions' => ['confirmed_at' => now()->subDays(3)->subHours(2)->toDateTimeString()],
                'recorded_at' => now()->subDays(3)->subHours(2),
            ],
            [
                'entry_type' => 'session_started',
                'summary' => 'Live video verification session started.',
                'legal_assertions' => [
                    'started_at' => now()->subDays(3)->addMinutes(5)->toDateTimeString(),
                    'provider_name' => 'manual',
                ],
                'recorded_at' => now()->subDays(3)->addMinutes(5),
            ],
            [
                'entry_type' => 'session_completed',
                'summary' => 'Live video verification session completed.',
                'legal_assertions' => [
                    'ended_at' => now()->subDays(3)->addMinutes(35)->toDateTimeString(),
                    'verification_checklist' => [
                        'face_matches_id' => true,
                        'id_valid_not_expired' => true,
                        'signer_conscious_aware' => true,
                        'signer_agrees_voluntarily' => true,
                        'signer_in_philippines' => true,
                        'session_recorded' => true,
                    ],
                    'all_checks_passed' => true,
                ],
                'recorded_at' => now()->subDays(3)->addMinutes(35),
            ],
            [
                'entry_type' => 'approval',
                'summary' => 'Attorney approved the notary request. Identity matched, voluntary consent confirmed, jurisdiction valid.',
                'legal_assertions' => [
                    'identity_matched' => true,
                    'voluntary_consent' => true,
                    'jurisdiction_valid' => true,
                ],
                'recorded_at' => now()->subDays(2),
            ],
            [
                'entry_type' => 'register_entry_created',
                'summary' => 'Notarial register entry 001 created for "Affidavit of Self-Adjudication" (acknowledgment).',
                'legal_assertions' => [
                    'entry_number' => 1,
                    'entry_year' => (int) now()->format('Y'),
                    'notarial_act_type' => 'acknowledgment',
                    'parties_count' => 1,
                    'witnesses_count' => 2,
                    'fees' => 500.00,
                    'official_receipt_number' => 'CR: 0001234',
                ],
                'recorded_at' => now()->subDays(2)->addMinutes(10),
            ],
            [
                'entry_type' => 'digitalization_completed',
                'summary' => 'Digital notarization completed. Seal applied, certificates generated, QR codes created.',
                'legal_assertions' => [
                    'documents_processed' => 1,
                    'register_entries_processed' => 1,
                    'completed_at' => now()->subDay()->timezone('Asia/Manila')->toDateTimeString(),
                ],
                'recorded_at' => now()->subDay(),
            ],
        ];

        foreach ($entries as $entry) {
            NotaryJournal::query()->updateOrCreate([
                'notary_request_id' => $request->id,
                'entry_type' => $entry['entry_type'],
                'recorded_at' => $entry['recorded_at'],
            ], [
                'notary_user_id' => $notary->id,
                'summary' => $entry['summary'],
                'legal_assertions' => $entry['legal_assertions'],
            ]);
        }
    }
}
