<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Jobs\SendReminderJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\User;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySigningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotarySigningProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_progress_summary_counts_completed_client_signers(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Done Signer',
            'signing_order' => 1,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Pending Signer',
            'signing_order' => 2,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $summary = app(NotarySigningProgressService::class)->summarize($request, $notary->id);

        $this->assertTrue($summary['visible']);
        $this->assertSame('awaiting_signatures', $summary['phase']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(50, $summary['percent']);
        $this->assertSame('Waiting for signatures', $summary['phase_label']);
        $this->assertStringContainsString('Pending Signer', $summary['summary']);
        $this->assertSame(
            ['sent', 'signatures', 'video', 'attorney', 'finalization'],
            collect($summary['tracker_steps'])->pluck('key')->all(),
        );
        $this->assertSame('current', collect($summary['tracker_steps'])->firstWhere('key', 'signatures')['state']);
    }

    public function test_notary_request_show_displays_compact_signer_status_on_document_page(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Hannah Faye',
            'signing_order' => 1,
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee('Document')
            ->assertSee('Signer status')
            ->assertSee('Waiting for signatures')
            ->assertSee('Hannah Faye')
            ->assertDontSee('Live polling')
            ->assertDontSee('Document completion')
            ->assertDontSee('data-live-signing-progress', false);
    }

    public function test_document_page_route_renders_compact_signer_status_without_full_tracker(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Realtime Signer',
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($notary)
            ->get(route('notary.requests.show', [$request, 'document']))
            ->assertOk()
            ->assertSee('Document')
            ->assertSee('Signer status')
            ->assertSee('Waiting for signatures')
            ->assertSee('Realtime Signer')
            ->assertDontSee('Live polling')
            ->assertDontSee('Document completion')
            ->assertDontSee('data-live-signing-progress', false)
            ->assertDontSee('Case progress');
    }

    public function test_signers_page_route_renders_without_repetitive_page_tabs_or_sidebar_progress(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'full_name' => 'Page Route Signer',
            'email' => 'page-route@example.test',
        ]);

        $this->actingAs($notary)
            ->get(route('notary.requests.show', [$request, 'signers']))
            ->assertOk()
            ->assertSee('Page Route Signer')
            ->assertSee('Workflow steps')
            ->assertSee('Document')
            ->assertSee('Signers &amp; video', false)
            ->assertSee('Attorney signature')
            ->assertDontSee('aria-label="Case pages"', false)
            ->assertDontSee('Case progress');
    }

    public function test_refresh_signing_status_updates_pending_tracker_state(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Polling Signer',
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($notary);

        $component = LivewireVolt::test('notary-requests.show', [
            'notaryRequest' => $request,
            'page' => 'document',
        ])->assertSee('Polling Signer')
            ->assertSee('Pending');

        $signer->update([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $component
            ->call('refreshSigningStatus')
            ->assertSee('Signed')
            ->assertSee('Completed');
    }

    public function test_send_signer_reminder_dispatches_reminder_job(): void
    {
        Queue::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('sendSignerReminder', $document->id, $signer->id)
            ->assertHasNoErrors();

        Queue::assertPushed(SendReminderJob::class, function (SendReminderJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id;
        });
    }

    public function test_signing_progress_phase_awaiting_video_after_all_client_signatures(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $summary = app(NotarySigningProgressService::class)->summarize($request, $notary->id);

        $this->assertSame('awaiting_video', $summary['phase']);
        $this->assertTrue($summary['all_client_signatures_complete']);
        $this->assertFalse($summary['video_verification_complete']);
    }

    public function test_pending_document_with_completed_client_signatures_prompts_video_verification(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => 'Signed Client',
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $summary = app(NotarySigningProgressService::class)->summarize($request, $notary->id);

        $this->assertSame('awaiting_video', $summary['phase']);
        $this->assertSame('current', collect($summary['tracker_steps'])->firstWhere('key', 'video')['state']);
        $this->assertSame('upcoming', collect($summary['tracker_steps'])->firstWhere('key', 'attorney')['state']);

        $this->actingAs($notary)
            ->get(route('notary.requests.show', [$request, 'document']))
            ->assertOk()
            ->assertSee('Video verification required')
            ->assertSee('Go to video verification')
            ->assertDontSee('Your turn: sign the contract');
    }

    public function test_signing_progress_phase_awaiting_attorney_after_video_sessions_complete(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionCompleted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'completed',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
            'ended_at' => now(),
        ]);

        $summary = app(NotarySigningProgressService::class)->summarize($request, $notary->id);
        $workflow = app(NotaryRequestWorkflowService::class);

        $this->assertSame('awaiting_attorney_signature', $summary['phase']);
        $this->assertTrue($summary['video_verification_complete']);
        $this->assertFalse($summary['attorney_has_signed']);
        $this->assertTrue($workflow->canBeginAttorneySigning($request));
    }

    public function test_notary_request_show_prompts_attorney_sign_after_video_verification(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionCompleted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'completed',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request, 'page' => 'document'])
            ->assertDontSee('Your turn: sign the contract')
            ->assertSee('Attorney Signature')
            ->assertSee('Now add your attorney signature fields')
            ->assertSee('Prepare attorney fields')
            ->assertDontSee('Send video links to signers')
            ->assertDontSee('Send video links to all signers');
    }

    public function test_sign_as_attorney_redirects_to_prepare_page_after_video_verification(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionCompleted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'completed',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('signAsAttorney', $document->id)
            ->assertRedirect(route('notary.documents.prepare', $document));
    }

    public function test_prepare_attorney_fields_link_opens_prepare_page_from_completed_document(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionCompleted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'completed',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
            'ended_at' => now(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.documents.prepare', $document))
            ->assertOk();

        $document->refresh();
        $this->assertSame(DocumentStatus::Pending, $document->status);
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $document->id,
            'user_id' => $notary->id,
            'status' => DocumentSignerStatus::Pending->value,
        ]);
    }

    public function test_attorney_prepare_page_redirects_to_tracker_until_video_conference_is_completed(): void
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
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'scheduled',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.documents.prepare', $document))
            ->assertRedirect(route('notary.requests.show', [$request, 'document']))
            ->assertSessionHas('error', 'Complete the video conference before attorney signing.');

        $document->refresh();
        $this->assertSame(DocumentStatus::Completed, $document->status);
        $this->assertDatabaseMissing('document_signers', [
            'document_id' => $document->id,
            'user_id' => $notary->id,
        ]);
    }

    public function test_stale_attorney_signer_cannot_open_locked_prepare_page_before_video_completion(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::SessionScheduled,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        DocumentSigner::factory()->for($document)->create([
            'name' => $notary->name,
            'email' => $notary->email,
            'user_id' => $notary->id,
            'status' => DocumentSignerStatus::Pending,
            'signing_order' => 999,
        ]);

        $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'scheduled',
            'room_name' => 'docutrust-test',
            'meeting_url' => 'https://meet.jit.si/docutrust-test',
            'scheduled_for' => now(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.documents.prepare', $document))
            ->assertRedirect(route('notary.requests.show', [$request, 'document']))
            ->assertSessionHas('error', 'Complete the video conference before attorney signing.');
    }

    public function test_authenticated_attorney_sign_page_shows_continue_process_button_after_signing(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::AttorneySigning,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        $attorneySigner = DocumentSigner::factory()->for($document)->create([
            'user_id' => $notary->id,
            'email' => $notary->email,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary)
            ->get(route('notary.sign.account.show', $attorneySigner->id))
            ->assertOk()
            ->assertSee('Continue process')
            ->assertSee(route('notary.requests.show', ['notaryRequest' => $request->id, 'tab' => 'closing']), false);
    }
}
