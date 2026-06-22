<?php

namespace Tests\Unit\Signature;

use App\Enums\DocumentStatus;
use App\Exceptions\SadNotFoundException;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\TrustAuthorizationSession;
use App\Services\Signature\SadLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SadLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sad_creates_trust_authorization_session(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();

        $session = app(SadLifecycleService::class)->storeSad(
            documentId: $document->id,
            signerId: $signer->id,
            credentialId: 'cred-001',
            sad: 'plaintext-sad',
            ttlSeconds: 300,
        );

        $this->assertDatabaseHas('trust_authorization_sessions', [
            'id' => $session->id,
            'document_id' => $document->id,
            'document_signer_id' => $signer->id,
            'credential_id' => 'cred-001',
            'status' => 'authorized',
        ]);
        $this->assertNotSame('plaintext-sad', $session->fresh()->sad);
        $this->assertTrue($session->fresh()->expires_at->between(
            now()->addSeconds(295),
            now()->addSeconds(305),
        ));
    }

    public function test_consume_sad_returns_decrypted_value_and_marks_consumed(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();
        $session = TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'document_id' => $document->id,
            'status' => 'authorized',
            'consumed_at' => null,
            'expires_at' => now()->addMinutes(5),
            'sad' => encrypt('test-sad-value'),
        ]);

        $sad = app(SadLifecycleService::class)->consumeSad($document->id, $signer->id);

        $this->assertSame('test-sad-value', $sad);
        $session->refresh();
        $this->assertNotNull($session->consumed_at);
        $this->assertSame('consumed', $session->status);
    }

    public function test_consume_sad_throws_if_session_expired(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();
        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'document_id' => $document->id,
            'status' => 'authorized',
            'consumed_at' => null,
            'expires_at' => now()->subMinute(),
            'sad' => encrypt('expired-sad'),
        ]);

        $this->expectException(SadNotFoundException::class);

        app(SadLifecycleService::class)->consumeSad($document->id, $signer->id);
    }

    public function test_consume_sad_throws_if_already_consumed(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create();
        TrustAuthorizationSession::factory()->for($signer, 'signer')->create([
            'document_id' => $document->id,
            'status' => 'authorized',
            'consumed_at' => now()->subSeconds(10),
            'expires_at' => now()->addMinutes(5),
            'sad' => encrypt('consumed-sad'),
        ]);

        $this->expectException(SadNotFoundException::class);

        app(SadLifecycleService::class)->consumeSad($document->id, $signer->id);
    }

    public function test_is_valid_returns_false_when_no_session_exists(): void
    {
        $this->assertFalse(app(SadLifecycleService::class)->isValid(999999, 999999));
    }

    public function test_expire_old_sessions_marks_expired_records(): void
    {
        $document = Document::factory()->create(['status' => DocumentStatus::Pending]);
        $expiredSignerA = DocumentSigner::factory()->for($document)->create();
        $expiredSignerB = DocumentSigner::factory()->for($document)->create();
        $futureSigner = DocumentSigner::factory()->for($document)->create();

        $expiredA = TrustAuthorizationSession::factory()->for($expiredSignerA, 'signer')->create([
            'document_id' => $document->id,
            'expires_at' => now()->subHour(),
            'status' => 'authorized',
        ]);
        $expiredB = TrustAuthorizationSession::factory()->for($expiredSignerB, 'signer')->create([
            'document_id' => $document->id,
            'expires_at' => now()->subHour(),
            'status' => 'authorized',
        ]);
        $future = TrustAuthorizationSession::factory()->for($futureSigner, 'signer')->create([
            'document_id' => $document->id,
            'expires_at' => now()->addHour(),
            'status' => 'authorized',
        ]);

        $expiredCount = app(SadLifecycleService::class)->expireOldSessions();

        $this->assertSame(2, $expiredCount);
        $this->assertSame('expired', $expiredA->fresh()->status);
        $this->assertSame('expired', $expiredB->fresh()->status);
        $this->assertSame('authorized', $future->fresh()->status);
    }
}
