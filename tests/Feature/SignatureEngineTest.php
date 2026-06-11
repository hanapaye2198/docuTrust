<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Enums\SignatureFieldType;
use App\Enums\SigningMethod;
use App\Jobs\GenerateCertificateJob;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\Signature;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureField;
use App\Models\SignerCertificate;
use App\Models\TrustAuthorizationSession;
use App\Models\User;
use App\Services\DocumentPdfStampingService;
use App\Services\PkiSignatureService;
use App\Services\SignerCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
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

    private function storeTinySignaturePng(): string
    {
        $path = 'signatures/'.Str::uuid()->toString().'.png';
        [, $encoded] = explode(',', self::TINY_PNG_DATA_URL, 2);
        Storage::disk('local')->put($path, base64_decode($encoded, true));

        return $path;
    }

    private function provisionSignerCrypto(DocumentSigner $signer): void
    {
        $keys = app(PkiSignatureService::class)->generateKeyPair();

        $signer->update([
            'signing_public_key' => $keys['public_key'],
            'signing_private_key' => $keys['private_key'],
        ]);

        app(SignerCertificateService::class)->getOrIssueForSigner($signer->fresh());
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

    public function test_final_pdf_generation_uses_source_pdf_not_prepared_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $sourcePath = 'documents/source-final.pdf';
        $preparedPath = 'documents/generated/source-final-prepared.pdf';

        Storage::disk('local')->put($sourcePath, Pdf::loadHTML('<h1>Source PDF</h1>')->output());
        Storage::disk('local')->put($preparedPath, Pdf::loadHTML('<h1>Prepared PDF</h1><div style="page-break-after: always;"></div><h1>Page Two</h1>')->output());

        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $sourcePath,
            'prepared_pdf_path' => $preparedPath,
        ]);

        $generatedPath = app(DocumentPdfStampingService::class)->generateFinalPdf($document);

        $this->assertNotNull($generatedPath);
        $this->assertTrue(Storage::disk('local')->exists($generatedPath));

        $content = Storage::disk('local')->get($generatedPath);
        preg_match_all('/\/Type\s*\/Page\b/', $content, $matches);

        $this->assertSame(1, count($matches[0]));
    }

    public function test_attorney_prepare_page_only_hydrates_attorney_fields_and_uses_signed_preview_stream(): void
    {
        Storage::fake('local');

        $client = User::factory()->client()->create();
        $notary = User::factory()->notary()->create([
            'organization_id' => $client->organization_id,
        ]);

        $notaryRequest = NotaryRequest::factory()->create([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_user_id' => $notary->id,
        ]);

        $path = 'documents/attorney-prepare-source.pdf';
        $this->putValidPdf($path);

        $document = Document::factory()->create([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_request_id' => $notaryRequest->id,
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);

        $clientSigner = DocumentSigner::factory()->for($document)->create([
            'name' => 'Client Signer',
            'email' => 'client-signer@example.test',
        ]);
        $attorneySigner = DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'name' => 'Attorney Signer',
            'email' => $notary->email,
        ]);

        $clientField = SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
        ]);
        $attorneyField = SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $attorneySigner->id,
        ]);

        Signature::query()->create([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
            'signature_field_id' => $clientField->id,
            'signature_path' => $this->storeTinySignaturePng(),
            'signature_algorithm' => 'RSA-SHA256',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.documents.prepare', $document))
            ->assertOk()
            ->assertSee('signed_preview=1')
            ->assertSee('"signer_id":'.$attorneySigner->id, false)
            ->assertDontSee('"signer_id":'.$clientSigner->id, false)
            ->assertSee('Attorney Signer', false)
            ->assertDontSee('Client Signer', false);

        $this->actingAs($notary)
            ->post(route('notary.documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $clientSigner->id,
                        'type' => SignatureFieldType::Signature->value,
                        'page_number' => 1,
                        'position_data' => [
                            'x' => 0.1,
                            'y' => 0.2,
                            'width' => 0.25,
                            'height' => 0.08,
                        ],
                    ],
                ],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('signature_fields', 2);
        $this->assertDatabaseHas('signature_fields', ['id' => $clientField->id]);
        $this->assertDatabaseHas('signature_fields', ['id' => $attorneyField->id]);
    }

    public function test_authenticated_attorney_signing_stream_uses_signed_preview_when_prior_signatures_exist(): void
    {
        Storage::fake('local');

        $client = User::factory()->client()->create();
        $notary = User::factory()->notary()->create([
            'organization_id' => $client->organization_id,
        ]);

        $path = 'documents/attorney-account-sign-source.pdf';
        $preparedPath = 'documents/generated/attorney-account-sign-prepared.pdf';
        $this->putValidPdf($path);
        $this->putValidPdf($preparedPath);

        $document = Document::factory()->create([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
            'prepared_pdf_path' => $preparedPath,
        ]);

        $clientSigner = DocumentSigner::factory()->for($document)->create();
        $attorneySigner = DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'name' => 'Attorney Signer',
            'email' => $notary->email,
        ]);

        $clientField = SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
            'type' => SignatureFieldType::Signature,
        ]);
        SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $attorneySigner->id,
            'type' => SignatureFieldType::Signature,
        ]);

        Signature::query()->create([
            'document_id' => $document->id,
            'signer_id' => $clientSigner->id,
            'signature_field_id' => $clientField->id,
            'signature_path' => $this->storeTinySignaturePng(),
            'signature_algorithm' => 'RSA-SHA256',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.sign.account.document.pdf', ['signerId' => $attorneySigner->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $generatedFiles = collect(Storage::disk('local')->allFiles('documents/generated'));

        $this->assertTrue(
            $generatedFiles->contains(fn (string $file): bool => str_contains($file, $document->id.'-signed_preview-')),
            'Expected the authenticated attorney signing stream to generate a signed preview PDF.'
        );
    }

    public function test_authenticated_notary_signature_save_returns_notary_signature_image_route(): void
    {
        Storage::fake('local');

        $client = User::factory()->client()->create();
        $notary = User::factory()->notary()->create([
            'organization_id' => $client->organization_id,
        ]);

        $path = 'documents/notary-signature-route-source.pdf';
        $this->putValidPdf($path);

        $document = Document::factory()->create([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);

        $attorneySigner = DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'name' => 'Attorney Signer',
            'email' => $notary->email,
        ]);

        $field = SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $attorneySigner->id,
            'type' => SignatureFieldType::Signature,
        ]);

        $response = $this->actingAs($notary)->postJson(
            route('notary.sign.account.signature.store', ['signerId' => $attorneySigner->id]),
            [
                'signature_field_id' => $field->id,
                'signature_image' => self::TINY_PNG_DATA_URL,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'field.image_url',
                fn (?string $url) => is_string($url)
                    && str_contains($url, '/notary/account-sign/'.$attorneySigner->id.'/signature-image/'.$field->id)
            );
    }

    public function test_authenticated_notary_signature_save_returns_notary_workflow_redirect_when_attorney_completes_signing(): void
    {
        Storage::fake('local');

        $client = User::factory()->enotarySigner()->create();
        $notary = User::factory()->for($client->organization)->notary()->create();
        $request = NotaryRequest::factory()->for($client)->create([
            'organization_id' => $client->organization_id,
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $path = 'documents/notary-signature-workflow-return.pdf';
        $this->putValidPdf($path);

        $document = Document::factory()->create([
            'organization_id' => $client->organization_id,
            'user_id' => $client->id,
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
        ]);

        $attorneySigner = DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'name' => 'Attorney Signer',
            'email' => $notary->email,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $field = SignatureField::factory()->create([
            'document_id' => $document->id,
            'signer_id' => $attorneySigner->id,
            'type' => SignatureFieldType::Signature,
        ]);

        $this->actingAs($notary)->postJson(
            route('notary.sign.account.signature.store', ['signerId' => $attorneySigner->id]),
            [
                'signature_field_id' => $field->id,
                'signature_image' => self::TINY_PNG_DATA_URL,
            ]
        )
            ->assertOk()
            ->assertJsonPath('redirect_url', route('notary.requests.show', [
                'notaryRequest' => $request->id,
                'tab' => 'closing',
            ]))
            ->assertJsonPath('summary.remaining', 0)
            ->assertJsonPath('summary.signer_status', DocumentSignerStatus::Signed->value);
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
        ])->assertRedirect(route('documents.prepare', ['document' => $document, 'page' => 1]));

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

    public function test_owner_can_save_signature_fields_from_json_form_payload(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/prepare-json-payload.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $payload = json_encode([
            [
                'signer_id' => $signer->id,
                'type' => SignatureFieldType::Signature->value,
                'page_number' => 1,
                'position_data' => [
                    'x' => 0.1,
                    'y' => 0.2,
                    'width' => 0.25,
                    'height' => 0.08,
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($user)
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => $payload,
            ])
            ->assertRedirect(route('documents.prepare', ['document' => $document, 'page' => 1]));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature->value,
            'page_number' => 1,
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
                    'type' => SignatureFieldType::Initials->value,
                    'page_number' => 1,
                    'position_data' => [
                        'x' => 0.45,
                        'y' => 0.25,
                        'width' => 0.12,
                        'height' => 0.055,
                    ],
                ],
            ],
        ])->assertRedirect(route('documents.prepare', ['document' => $document, 'page' => 1]));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Email->value,
            'page_number' => 1,
        ]);

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Initials->value,
            'page_number' => 1,
        ]);
    }

    public function test_prepare_rejects_removed_toggle_field_types(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->from(route('documents.prepare', $document))
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $signer->id,
                        'type' => SignatureFieldType::Checkbox->value,
                        'page_number' => 1,
                        'position_data' => [
                            'x' => 0.15,
                            'y' => 0.25,
                            'width' => 0.1,
                            'height' => 0.05,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('documents.prepare', $document))
            ->assertSessionHasErrors('fields.0.type');

        $this->assertDatabaseMissing('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Checkbox->value,
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

    public function test_prepare_allows_rotated_field_that_visually_fits_near_right_edge(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/prepare-rotated-right-edge.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $signer->id,
                        'type' => SignatureFieldType::Signature->value,
                        'page_number' => 1,
                        'position_data' => [
                            'x' => 0.83,
                            'y' => 0.55,
                            'width' => 0.08,
                            'height' => 0.25,
                            'angle' => 90,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('documents.prepare', ['document' => $document, 'page' => 1]));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature->value,
            'page_number' => 1,
        ]);
    }

    public function test_prepare_allows_rotated_field_with_serialized_origin_outside_unit_interval(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/prepare-rotated-origin-outside-unit.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Draft,
            'file_path' => $path,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $this->actingAs($user)
            ->post(route('documents.signature-fields.store', $document), [
                'fields' => [
                    [
                        'signer_id' => $signer->id,
                        'type' => SignatureFieldType::Signature->value,
                        'page_number' => 1,
                        'position_data' => [
                            'x' => -0.06,
                            'y' => 0.40,
                            'width' => 0.25,
                            'height' => 0.08,
                            'angle' => 90,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('documents.prepare', ['document' => $document, 'page' => 1]));

        $this->assertDatabaseHas('signature_fields', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature->value,
            'page_number' => 1,
        ]);
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
            'signing_method' => SigningMethod::PkiCertificate,
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

    public function test_password_protected_document_requires_unlock_before_signing(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/password-protected-signing.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
            'access_password_hash' => Hash::make('shared-secret'),
            'access_password_hint' => 'Shared in chat',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
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

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertSee('Document password required')
            ->assertSee('Shared in chat');

        $this->get(route('sign.document.pdf', $signer))
            ->assertStatus(423)
            ->assertSee('Enter the document password to continue.');

        $this->postJson(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])
            ->assertStatus(423)
            ->assertJsonPath('message', 'Enter the document password to continue.');
    }

    public function test_password_protected_document_can_be_unlocked_and_signed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/password-unlock-signing.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Pending,
            'file_path' => $path,
            'access_password_hash' => Hash::make('shared-secret'),
            'access_password_hint' => 'Shared in chat',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
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

        $this->post(route('sign.unlock', $signer), [
            'password' => 'shared-secret',
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $this->get(route('sign.document.pdf', $signer))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->post(route('sign.signature.store', $signer), [
            'signature_field_id' => $field->id,
            'signature_image' => self::TINY_PNG_DATA_URL,
        ])->assertRedirect(route('sign.show', $signer->access_token));

        $this->runQueuedCompletionWork($document);

        $signature = Signature::query()->where('signature_field_id', $field->id)->first();
        $this->assertNotNull($signature);
        $this->assertSame('app_managed', $signature->signing_provider);
        $this->assertNotNull($signature->signature_hash);
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

    public function test_owner_can_stream_completed_document_from_archive_disk(): void
    {
        config()->set('filesystems.disks.archive_testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/archive-testing'),
            'throw' => false,
        ]);
        config()->set('filesystems.docutrust_archive_disk', 'archive_testing');

        Storage::fake('local');
        Storage::fake('archive_testing');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => 'documents/source.pdf',
            'final_pdf_path' => 'documents/generated/final-local.pdf',
            'archive_storage_disk' => 'archive_testing',
            'archive_document_path' => 'archives/documents/1/final-archived.pdf',
            'archived_at' => now(),
        ]);
        Storage::disk('archive_testing')->put('archives/documents/1/final-archived.pdf', '%PDF-1.4 archived final');

        $this->actingAs($user)
            ->get(route('documents.stream', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_completed_document_stream_self_heals_missing_final_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/heal-stream-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $path,
            'final_pdf_path' => null,
            'certificate_path' => null,
            'archive_document_path' => null,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        $this->provisionSignerCrypto($signer);
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
        Signature::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $field->id,
            'signature_path' => $this->storeTinySignaturePng(),
            'signature_algorithm' => 'RSA-SHA256',
        ]);

        $this->actingAs($user)
            ->get(route('documents.stream', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $document->refresh();
        $this->assertNotNull($document->final_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($document->final_pdf_path));
        $this->assertNotNull($document->certificate_path);
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
        $this->assertNotNull($document->archived_at);
        $this->assertNotNull($document->archive_document_path);
        $this->assertNotNull($document->archive_certificate_path);

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

    public function test_owner_can_download_final_signed_document(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'final_pdf_path' => 'documents/generated/sample-final.pdf',
            'archive_storage_disk' => 'local',
            'archive_document_path' => 'documents/generated/sample-final.pdf',
            'archived_at' => now(),
        ]);
        Storage::disk('local')->put('documents/generated/sample-final.pdf', '%PDF-1.4 final');

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_owner_can_download_final_signed_document_from_archive_disk(): void
    {
        config()->set('filesystems.disks.archive_testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/archive-testing-download'),
            'throw' => false,
        ]);
        config()->set('filesystems.docutrust_archive_disk', 'archive_testing');

        Storage::fake('local');
        Storage::fake('archive_testing');

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'archive_storage_disk' => 'archive_testing',
            'archive_document_path' => 'archives/documents/77/final.pdf',
            'archived_at' => now(),
        ]);
        Storage::disk('archive_testing')->put('archives/documents/77/final.pdf', '%PDF-1.4 archived final');

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_completed_document_download_self_heals_missing_final_pdf(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $path = 'documents/heal-download-source.pdf';
        $this->putValidPdf($path);
        $document = Document::factory()->for($user)->create([
            'status' => DocumentStatus::Completed,
            'file_path' => $path,
            'final_pdf_path' => null,
            'certificate_path' => null,
            'archive_document_path' => null,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        $this->provisionSignerCrypto($signer);
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
        Signature::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signature_field_id' => $field->id,
            'signature_path' => $this->storeTinySignaturePng(),
            'signature_algorithm' => 'RSA-SHA256',
        ]);

        $this->actingAs($user)
            ->get(route('documents.download', $document))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $document->refresh();
        $this->assertNotNull($document->final_pdf_path);
        $this->assertNotNull($document->archive_document_path);
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

    public function test_notified_signer_is_not_treated_as_already_signed(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $document = Document::factory()->for($user)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Notified,
        ]);

        $this->get(route('sign.show', $signer->access_token))
            ->assertOk()
            ->assertDontSee('You have already signed this document.');

        $this->post(route('sign.store', $signer->access_token))
            ->assertRedirect(route('sign.show', $signer->access_token))
            ->assertSessionHas('status', 'Thank you. Your signature has been recorded.');

        $signer->refresh();
        $document->refresh();

        $this->assertSame(DocumentSignerStatus::Signed, $signer->status);
        $this->assertSame(DocumentStatus::Completed, $document->status);
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
            'signing_method' => SigningMethod::PkiCertificate,
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
            'signing_method' => SigningMethod::PkiCertificate,
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
