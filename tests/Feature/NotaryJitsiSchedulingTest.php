<?php

namespace Tests\Feature;

use App\Enums\NotaryRequestStatus;
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
            'status' => NotaryRequestStatus::IdentityVerified,
        ]);

        $session = app(NotarySchedulingService::class)->schedule(
            $request,
            now()->addHour(),
            'jitsi',
        );

        $this->assertStringStartsWith('docutrust-'.$request->id.'-', (string) $session->room_name);
        $this->assertStringContainsString('meet.jit.si', (string) $session->meeting_url);
    }
}
