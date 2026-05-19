<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\User;
use App\Services\NotarySchedulingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaryJitsiSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_jitsi_provider_generates_room_and_meeting_url(): void
    {
        $requester = User::factory()->create();
        $request = NotaryRequest::factory()->for($requester)->create([
            'status' => NotaryRequestStatus::Submitted,
        ]);
        $document = Document::factory()->for($requester)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signing_order' => 1,
        ]);

        $session = app(NotarySchedulingService::class)->schedule(
            $request,
            now()->addHour(),
            'jitsi',
        );

        $this->assertStringStartsWith('docutrust-'.$request->id.'-', (string) $session->room_name);
        $this->assertStringContainsString('8x8.vc', (string) $session->meeting_url);
    }
}
