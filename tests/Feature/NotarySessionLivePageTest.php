<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Models\NotarySigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotarySessionLivePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_session_page_shows_verification_actions_for_assigned_notary(): void
    {
        [$notary, $request, $session] = $this->createLiveSessionCase();

        $this->actingAs($notary)
            ->get(route('notary.requests.session.live', [$request, $session]))
            ->assertOk()
            ->assertSee('Signer verified')
            ->assertSee('Cancel session')
            ->assertSee('Connecting to video room');
    }

    public function test_mount_starts_scheduled_session_for_assigned_notary(): void
    {
        [$notary, $request, $session] = $this->createLiveSessionCase(status: 'scheduled');

        $this->actingAs($notary)
            ->get(route('notary.requests.session.live', [$request, $session]));

        $this->assertDatabaseHas('notary_sessions', [
            'id' => $session->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_verify_signer_completes_session_and_redirects_to_request(): void
    {
        [$notary, $request, $session] = $this->createLiveSessionCase(status: 'in_progress');

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.session-live', [
            'notaryRequest' => $request,
            'session' => $session,
        ])
            ->call('verifySigner')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary.requests.show', $request));

        $this->assertDatabaseHas('notary_sessions', [
            'id' => $session->id,
            'status' => 'completed',
        ]);
    }

    public function test_cancel_session_marks_session_cancelled(): void
    {
        [$notary, $request, $session] = $this->createLiveSessionCase(status: 'in_progress');

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.session-live', [
            'notaryRequest' => $request,
            'session' => $session,
        ])
            ->call('cancelSession')
            ->assertHasNoErrors()
            ->assertRedirect(route('notary.requests.show', $request));

        $this->assertDatabaseHas('notary_sessions', [
            'id' => $session->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * @return array{0: User, 1: NotaryRequest, 2: NotarySession}
     */
    private function createLiveSessionCase(string $status = 'scheduled'): array
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Maria Client',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'name' => $party->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $session = $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => $status,
            'room_name' => 'docutrust-test-live-room',
            'meeting_url' => 'https://meet.jit.si/docutrust-test-live-room',
            'access_token' => 'live-session-token',
            'scheduled_for' => now()->addHour(),
            'started_at' => $status === 'in_progress' ? now() : null,
        ]);

        return [$notary, $request, $session];
    }
}
