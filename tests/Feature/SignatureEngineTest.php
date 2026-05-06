<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureField;
use App\Models\TrustAuthorizationSession;
use App\Models\User;
use App\Services\PkiSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignatureEngineTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function putValidPdf(string $path): void
    {
        Storage::disk('local')->put($path, Pdf::loadHTML('<h1>DocuTrust</h1>')->output());
    }

    private function runQueuedCompletionWork(Document $document): void
    {
        $document->refresh();

        if ($document->status !== DocumentStatus::Completed) {
            return;
        }

        app()->call([new GenerateDocumentPdfJob($document->id, 'final'), 'handle']);
        app()->call([new GenerateCertificateJob($document->id), 'handle']);
    }

    /**
     * @return array{
     *   certificate_pem: string,
     *   issuer_certificate_pem: string,
     *   public_key_pem: string,
     *   private_key_pem: string
     * }
     */
    private function makeRemoteManagedCertificateChain(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => (string) config('docutrust.pki.openssl_config_path'),
            'x509_extensions' => 'v3_ca',
        ];

        $issuerKey = openssl_pkey_new($config);
        openssl_pkey_export($issuerKey, $issuerPrivateKeyPem, null, $config);
        $issuerDn = [
            'commonName' => 'Remote Signing Root',
            'organizationName' => 'DocuTrust Remote Provider',
            'organizationalUnitName' => 'Trust Service Provider',
            'countryName' => 'PH',
        ];
        $issuerCsr = openssl_csr_new($issuerDn, $issuerKey, $config);
        $issuerCert = openssl_csr_sign($issuerCsr, null, $issuerKey, 3650, $config, 7001);
        openssl_x509_export($issuerCert, $issuerCertificatePem);

        $signerConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => (string) config('docutrust.pki.openssl_config_path'),
            'x509_extensions' => 'usr_cert',
        ];
        $signerKey = openssl_pkey_new($signerConfig);
        openssl_pkey_export($signerKey, $signerPrivateKeyPem, null, $signerConfig);
        $signerDetails = openssl_pkey_get_details($signerKey);
        $signerDn = [
            'commonName' => 'Remote Managed Signer',
            'emailAddress' => 'remote@example.test',
            'organizationName' => 'DocuTrust',
            'organizationalUnitName' => 'Signer',
            'countryName' => 'PH',
        ];
        $signerCsr = openssl_csr_new($signerDn, $signerKey, $signerConfig);
        $issuerCertResource = openssl_x509_read($issuerCertificatePem);
        $signerCert = openssl_csr_sign($signerCsr, $issuerCertResource, $issuerKey, 825, $signerConfig, 7002);
        openssl_x509_export($signerCert, $signerCertificatePem);

        return [
            'certificate_pem' => $signerCertificatePem,
            'issuer_certificate_pem' => $issuerCertificatePem,
            'public_key_pem' => (string) ($signerDetails['key'] ?? ''),
            'private_key_pem' => $signerPrivateKeyPem,
        ];
    }

    public function test_guest_cannot_visit_prepare_page(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->get(route('documents.prepare', $document))->assertRedirect(route('login'));
    }

    public function test_owner_can_visit_prepare_page_when_draft(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->get(route('documents.prepare', $document))
            ->assertOk()
            ->assertSee(__('Prepare document'))
            ->assertSee('template-prepare-config')
            ->assertSee('fabric-canvas');
    }

    public function test_prepare_page_uses_source_pdf_stream_even_when_prepared_pdf_exists(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $sourcePath = 'documents/source-prepare.pdf';
        $preparedPath = 'documents/generated/source-prepare-stamped.pdf';
        Storage::disk('local')->put($sourcePath, Pdf::loadHTML('<h1>Source PDF</h1>')->output());
        Storage::disk('local')->put($preparedPath, Pdf::loadHTML('<h1>Prepared PDF</h1>')->output());

        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $sourcePath,
            'prepared_pdf_path' => $preparedPath,
        ]);
        DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->get(route('documents.prepare', $document))
            ->assertOk()
            ->assertSee('template-prepare-config')
            ->assertSee('source=1');
    }

    public function test_owner_is_redirected_to_document_page_when_prepare_has_no_signers(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);

        $this->actingAs($user)
            ->get(route('documents.prepare', $document))
            ->assertRedirect(route('documents.show', $document));

        $this->actingAs($user)
            ->get(route('documents.prepare', $document), ['referer' => route('documents.show', $document)])
            ->assertRedirect(route('documents.show', $document))
            ->assertSessionHas('error', 'Add at least one signer before preparing fields.');
    }

    public function test_owner_can_save_signature_fields(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)->post(route('documents.signature-fields.store', $document), [
            'fields' => [
                [
                    'signer_id' => $signer->id,
                    'type' => 'signature',
                    'page_number' => 2,
                    'position_data' => [
                        'x' => 0.1,
                        'y' => 0.2,
                        'width' => 0.25,
                        'height' => 0.08,
                    ],
                ],
            ],
        ])->assertRedirect(route('documents.prepare', $document));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature->value,
            'page_number' => 2,
        ]);

        $this->assertDatabaseHas('signature_audit_events', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'action' => SignatureAuditEvent::ACTION_PLACED,
        ]);
    }

    public function test_owner_can_save_extended_field_types(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)->post(route('documents.signature-fields.store', $document), [
            'fields' => [
                [
                    'signer_id' => $signer->id,
                    'type' => SignatureFieldType::Email->value,
                    'page_number' => 1,
                    'position_data' => [
                        'x' => 0.15,
                        'y' => 0.25,
                        'width' => 0.26,
                        'height' => 0.06,
                    ],
                ],
                [
                    'signer_id' => $signer->id,
                    'type' => SignatureFieldType::Checkbox->value,
                    'page_number' => 1,
                    'position_data' => [
                        'x' => 0.45,
                        'y' => 0.25,
                        'width' => 0.09,
                        'height' => 0.055,
                    ],
                ],
            ],
        ])->assertRedirect(route('documents.prepare', $document));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Email->value,
            'page_number' => 1,
        ]);

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Checkbox->value,
            'page_number' => 1,
        ]);
    }

    public function test_prepare_rejects_field_geometry_that_exceeds_page_bounds(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/prepare-invalid-geometry.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->from(route('documents.prepare', $document))
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $signer->id,
                        'type' => SignatureFieldType::Signature->value,
                        'page_number' => 1,
                        'position_data' => [
                            'x' => 0.9,
                            'y' => 0.2,
                            'width' => 0.2,
                            'height' => 0.08,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('documents.prepare', $document))
            ->assertSessionHasErrors('fields.0.position_data');

        $this->assertDatabaseCount('signature_fields', 0);
    }

    public function test_prepare_rejects_field_page_number_beyond_pdf_page_count(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/prepare-invalid-page.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->from(route('documents.prepare', $document))
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $signer->id,
                        'type' => SignatureFieldType::Signature->value,
                        'page_number' => 2,
                        'position_data' => [
                            'x' => 0.1,
                            'y' => 0.2,
                            'width' => 0.2,
                            'height' => 0.08,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('documents.prepare', $document))
            ->assertSessionHasErrors('fields.0.page_number');

        $this->assertDatabaseCount('signature_fields', 0);
    }

    public function test_signer_can_complete_signature_field_after_radio_field_completion(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/radio-then-signature.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $radioField = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Radio,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.08,
                'height' => 0.05,
            ],
        ]);

        $signatureField = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.25,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $radioField->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $signer->refresh();
        $this->assertSame(DocumentSignerStatus::Pending, $signer->status);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $signatureField->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $this->assertDatabaseHas('signatures', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $radioField->id,
        ]);

        $this->assertDatabaseHas('signatures', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $signatureField->id,
        ]);

        $signer->refresh();
        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
    }

    public function test_signer_can_submit_signature_for_field(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/signer-submit-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending, 'file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);
        $field = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $this->runQueuedCompletionWork($document);

        $this->assertDatabaseHas('signatures', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $field->id,
        ]);

        $signature = Signature::query()->where('signature_field_id', $field->id)->first();
        $this->assertNotNull($signature);
        $this->assertSame(64, strlen((string) $signature->signature_hash));
        $this->assertNotNull($signature->signature_value);
        $this->assertNotNull($signature->signature_path);
        $this->assertTrue(Storage::disk('local')->exists($signature->signature_path));
        $this->assertSame(64, strlen((string) $signature->public_key_fingerprint));
        $this->assertSame('RSA-SHA256', $signature->signature_algorithm);
        $this->assertSame('app_managed', $signature->signing_provider);
        $this->assertNull($signature->signing_provider_reference);
        $this->assertNull($signature->signing_provider_payload);
        $this->assertNotNull($signature->signer_certificate_id);

        $signer->refresh();
        $this->assertNotNull($signer->signing_public_key);
        $this->assertNotNull($signer->signing_private_key);

        $signerCertificate = SignerCertificate::query()->find($signature->signer_certificate_id);
        $this->assertNotNull($signerCertificate);
        $this->assertSame($signer->id, $signerCertificate->document_signer_id);
        $this->assertSame((string) $signer->signing_public_key, $signerCertificate->public_key_pem);
        $this->assertSame('active', $signerCertificate->status);
        $this->assertStringContainsString('BEGIN CERTIFICATE', $signerCertificate->certificate_pem);

        $parsedSignerCertificate = openssl_x509_parse((string) $signerCertificate->certificate_pem);
        $this->assertIsArray($parsedSignerCertificate);
        $this->assertSame((string) $signerCertificate->serial_number, strtoupper((string) ($parsedSignerCertificate['serialNumberHex'] ?? '')));
        $this->assertSame('CA:FALSE', (string) ($parsedSignerCertificate['extensions']['basicConstraints'] ?? ''));

        $certificateAuthority = $signerCertificate->certificateAuthority()->first();
        $this->assertNotNull($certificateAuthority);
        $parsedAuthorityCertificate = openssl_x509_parse((string) $certificateAuthority->certificate_pem);
        $this->assertIsArray($parsedAuthorityCertificate);
        $this->assertSame((string) $certificateAuthority->serial_number, strtoupper((string) ($parsedAuthorityCertificate['serialNumberHex'] ?? '')));
        $this->assertStringContainsString('CA:TRUE', (string) ($parsedAuthorityCertificate['extensions']['basicConstraints'] ?? ''));

        $isValid = app(PkiSignatureService::class)->verifySignature(
            (string) $signature->signature_hash,
            (string) $signature->signature_value,
            (string) $signer->signing_public_key,
        );
        $this->assertTrue($isValid);
    }

    public function test_remote_managed_field_submission_requires_active_trust_authorization(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/remote-managed-trust-required.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'remote_credential_id' => 'credential-required-001',
        ]);
        $field = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->postJson(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Start trust authorization before completing your assigned fields.');

        $this->assertDatabaseMissing('signatures', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $field->id,
        ]);
    }

    public function test_legacy_sign_is_blocked_when_document_has_any_signature_fields(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signerWithField = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);
        $signerWithoutField = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signerWithField->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->post(route('sign.store', $signerWithoutField))
            ->assertRedirect(route('sign.show', $signerWithoutField->access_token));

        $signerWithoutField->refresh();
        $this->assertSame(DocumentSignerStatus::Pending, $signerWithoutField->status);
    }

    public function test_owner_can_stream_document_pdf(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'documents/test-doc.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);

        $this->actingAs($user)
            ->get(route('documents.stream', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_guest_can_stream_pdf_for_signing_session(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $path = 'documents/sign-doc.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 fake');
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->get(route('sign.document.pdf', $signer))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_signature_field_save_creates_audit_events(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/completed-cert-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $document->update(['file_path' => $path]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);
        $field = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $this->runQueuedCompletionWork($document);

        $this->assertDatabaseHas('signature_audit_events', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'action' => SignatureAuditEvent::ACTION_SIGNED,
        ]);

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertNotNull($document->final_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($document->final_pdf_path));
        $this->assertNotNull($document->certificate_path);
        $this->assertTrue(Storage::disk('local')->exists($document->certificate_path));

        $this->assertDatabaseHas('signature_audit_events', [
            'document_id' => $document->id,
            'action' => SignatureAuditEvent::ACTION_COMPLETED,
        ]);
    }

    public function test_owner_can_view_and_download_generated_certificate(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'certificate_path' => 'certificates/sample-certificate.pdf',
        ]);
        Storage::disk('local')->put('certificates/sample-certificate.pdf', '%PDF-1.4 cert');

        $this->actingAs($user)
            ->get(route('documents.certificate.show', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->get(route('documents.certificate.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_signing_link_is_invalid_for_unknown_token(): void
    {
        $this->get(route('sign.show', 'missing-token'))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_expired_signing_link_is_blocked(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'access_token' => (string) Str::uuid(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');

        $this->post(route('sign.store', $signer->access_token))
            ->assertForbidden()
            ->assertSee('Link expired or invalid');
    }

    public function test_remote_managed_backend_seals_document_using_provider_response(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.base_url', 'https://remote-signing.test');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');
        config()->set('services.remote_signing.api_mode', 'csc');
        config()->set('services.remote_signing.csc.sign_hash_endpoint', '/csc/v1/signatures/signHash');
        config()->set('services.remote_signing.csc.timestamp_enabled', true);
        config()->set('services.remote_signing.csc.timestamp_endpoint', '/csc/v1/signatures/timestamp');
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/remote-managed-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'remote_credential_id' => 'credential-signer-001',
        ]);
        $field = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-signer-001',
            'authorization_reference' => 'auth-ref-remote-001',
            'sad' => 'sad-remote-001',
        ]);

        $chain = $this->makeRemoteManagedCertificateChain();

        Http::fake([
            'https://remote-signing.test/csc/v1/signatures/signHash' => function ($request) use ($chain) {
                $payload = $request->data();
                $this->assertSame('credential-signer-001', $payload['credentialID']);
                $this->assertSame('sad-remote-001', $payload['SAD']);
                $this->assertSame('2.16.840.1.101.3.4.2.1', $payload['hashAlgo']);
                $this->assertSame('1.2.840.113549.1.1.11', $payload['signAlgo']);
                $this->assertSame(1, $payload['numSignatures']);
                $this->assertCount(1, $payload['hashes']);
                $this->assertIsString($payload['clientData']);

                $clientData = json_decode((string) base64_decode((string) $payload['clientData'], true), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('auth-ref-remote-001', $clientData['authorization_reference'] ?? null);

                $decodedHash = base64_decode((string) $payload['hashes'][0], true);
                $this->assertNotFalse($decodedHash);
                $documentHash = bin2hex($decodedHash);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $documentHash);

                $signatureValue = app(PkiSignatureService::class)->signHash(
                    $documentHash,
                    $chain['private_key_pem'],
                );

                return Http::response([
                    'signatures' => [$signatureValue],
                    'signAlgo' => 'RSA-SHA256',
                    'credentialID' => 'credential-signer-001',
                    'transactionID' => 'remote-sign-ref-001',
                    'certificates' => [
                        $chain['certificate_pem'],
                        $chain['issuer_certificate_pem'],
                    ],
                    'public_key_pem' => $chain['public_key_pem'],
                    'SCAL' => '2',
                    'authMode' => 'explicit_otp',
                    'signingTime' => '2026-05-06T09:00:00Z',
                    'validationInfo' => [
                        'policy' => 'csc',
                    ],
                    'evidence' => [
                        'authentication_method' => 'otp',
                    ],
                ]);
            },
            'https://remote-signing.test/csc/v1/signatures/timestamp' => function ($request) {
                $payload = $request->data();
                $this->assertSame('2.16.840.1.101.3.4.2.1', $payload['hashAlgo']);
                $this->assertIsString($payload['hash']);
                $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $payload['nonce']);

                return Http::response([
                    'timestamp' => base64_encode('rfc3161-token'),
                    'transactionID' => 'remote-ts-ref-001',
                ]);
            },
            'http://127.0.0.1:3001/anchor' => Http::response([
                'transactionHash' => '0xremoteproof123',
            ]),
        ]);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertDatabaseHas('document_hashes', [
            'document_id' => $document->id,
            'transaction_id' => '0xremoteproof123',
        ]);

        $signature = Signature::query()->where('signature_field_id', $field->id)->firstOrFail();
        $this->assertNotNull($signature->signature_value);
        $this->assertNotNull($signature->signature_hash);
        $this->assertSame('trust_service_provider', $signature->signing_provider);
        $this->assertSame('remote-sign-ref-001', $signature->signing_provider_reference);
        $this->assertSame('credential-signer-001', $signature->signing_provider_payload['credential_id'] ?? null);
        $this->assertSame('remote-sign-ref-001', $signature->signing_provider_payload['transaction_id'] ?? null);
        $this->assertSame('2', $signature->signing_provider_payload['scal'] ?? null);
        $this->assertSame('explicit_otp', $signature->signing_provider_payload['authentication_mode'] ?? null);
        $this->assertSame('otp', $signature->signing_provider_payload['authentication_method'] ?? null);
        $this->assertSame('csc', $signature->signing_provider_payload['validation_info']['policy'] ?? null);
        $this->assertSame(base64_encode('rfc3161-token'), $signature->signing_provider_payload['timestamp_token'] ?? null);
        $this->assertSame('remote-ts-ref-001', $signature->signing_provider_payload['timestamp_transaction_id'] ?? null);
        $this->assertSame((string) $signature->signature_hash, $signature->signing_provider_payload['timestamp_hash'] ?? null);
        $this->assertSame('2.16.840.1.101.3.4.2.1', $signature->signing_provider_payload['timestamp_hash_algorithm'] ?? null);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) ($signature->signing_provider_payload['timestamp_request_nonce'] ?? ''));

        $signerCertificate = SignerCertificate::query()->find($signature->signer_certificate_id);
        $this->assertNotNull($signerCertificate);
        $this->assertSame('provider_managed', $signerCertificate->certificate_source);
        $this->assertSame('trust_service_provider', $signerCertificate->provider_name);
        $this->assertSame('remote-sign-ref-001', $signerCertificate->provider_reference);
        $this->assertNull($signerCertificate->certificate_authority_id);
        $this->assertNotNull($signerCertificate->issuer_certificate_pem);
    }

    public function test_remote_managed_backend_uses_active_trust_authorization_session_when_present(): void
    {
        config()->set('docutrust.pki.signing_backend', 'remote_managed');
        config()->set('services.remote_signing.base_url', 'https://remote-signing.test');
        config()->set('services.remote_signing.provider_name', 'trust_service_provider');
        config()->set('services.remote_signing.api_mode', 'csc');
        config()->set('services.remote_signing.csc.sign_hash_endpoint', '/csc/v1/signatures/signHash');
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/remote-managed-with-auth-session.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
            'remote_credential_id' => 'credential-signer-002',
        ]);
        $field = SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => [
                'x' => 0.1,
                'y' => 0.1,
                'width' => 0.2,
                'height' => 0.05,
            ],
        ]);

        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'provider_name' => 'trust_service_provider',
            'credential_id' => 'credential-signer-002',
            'authorization_reference' => 'auth-ref-001',
            'sad' => 'sad-authorization-token',
        ]);

        $chain = $this->makeRemoteManagedCertificateChain();

        Http::fake([
            'https://remote-signing.test/csc/v1/signatures/signHash' => function ($request) use ($chain) {
                $payload = $request->data();
                $this->assertSame('credential-signer-002', $payload['credentialID']);
                $this->assertSame('sad-authorization-token', $payload['SAD']);

                $clientData = json_decode((string) base64_decode((string) $payload['clientData'], true), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('auth-ref-001', $clientData['authorization_reference'] ?? null);

                $decodedHash = base64_decode((string) $payload['hashes'][0], true);
                $this->assertNotFalse($decodedHash);
                $documentHash = bin2hex($decodedHash);

                $signatureValue = app(PkiSignatureService::class)->signHash(
                    $documentHash,
                    $chain['private_key_pem'],
                );

                return Http::response([
                    'signatures' => [$signatureValue],
                    'signAlgo' => 'RSA-SHA256',
                    'credentialID' => 'credential-signer-002',
                    'transactionID' => 'remote-sign-ref-002',
                    'certificates' => [
                        $chain['certificate_pem'],
                        $chain['issuer_certificate_pem'],
                    ],
                    'public_key_pem' => $chain['public_key_pem'],
                    'SCAL' => '2',
                    'authMode' => 'explicit_otp',
                ]);
            },
            'http://127.0.0.1:3001/anchor' => Http::response([
                'transactionHash' => '0xremoteproof456',
            ]),
        ]);

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $signature = Signature::query()->where('signature_field_id', $field->id)->firstOrFail();
        $this->assertSame('remote-sign-ref-002', $signature->signing_provider_reference);
    }
}
