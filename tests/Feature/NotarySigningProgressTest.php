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
use App\Models\SignatureField;
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
        $this->assertStringContainsString('Pending Signer', $summary['summary']);
    }

    public function test_notary_request_show_displays_signing_progress_after_send(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Draft,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'name' => 'Hannah Faye',
            'signing_order' => 1,
        ]);

        SignatureField::factory()->for($document)->create([
            'signer_id' => $signer->id,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('sendLinkedDocument', $document->id)
            ->assertSee('Signing progress')
            ->assertSee('Hannah Faye')
            ->assertSee('Resend')
            ->assertSee('Reminder');
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

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->assertSee('Your turn: sign the contract')
            ->assertSee('Sign as attorney')
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
