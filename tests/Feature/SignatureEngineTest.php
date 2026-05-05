<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignerCertificate;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\PkiSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $isValid = app(PkiSignatureService::class)->verifySignature(
            (string) $signature->signature_hash,
            (string) $signature->signature_value,
            (string) $signer->signing_public_key,
        );
        $this->assertTrue($isValid);
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
}
