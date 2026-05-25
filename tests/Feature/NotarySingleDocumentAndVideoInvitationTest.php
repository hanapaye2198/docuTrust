<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\NotaryRequestStatus;
use App\Events\DocumentCompleted;
use App\Events\DocumentSignerCompleted;
use App\Mail\NotarySignerVideoInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use App\Models\SignatureField;
use App\Models\User;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySignerVideoInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotarySingleDocumentAndVideoInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_document_upload_is_rejected_for_enotary_request(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);

        Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
        ]);

        $this->actingAs($notary);

        $this->expectException(\RuntimeException::class);

        app(NotaryRequestWorkflowService::class)->assertCanAttachDocument($request->fresh());
    }

    public function test_document_completed_sends_per_signer_video_invitations(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $signerA = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'alice@example.test',
        ]);
        $signerB = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'bob@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $signerA->email,
            'name' => $signerA->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        DocumentSigner::factory()->for($document)->create([
            'email' => $signerB->email,
            'name' => $signerB->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        app(NotarySignerVideoInvitationService::class)->handleDocumentCompleted($document);

        $this->assertDatabaseCount('notary_sessions', 2);
        $this->assertDatabaseHas('notary_sessions', [
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signerA->id,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('notary_sessions', [
            'notary_request_id' => $request->id,
            'notary_signer_id' => $signerB->id,
            'status' => 'scheduled',
        ]);

        Mail::assertQueued(NotarySignerVideoInvitationMail::class, 2);
        Mail::assertQueued(NotarySignerVideoInvitationMail::class, function (NotarySignerVideoInvitationMail $mail) use ($signerA): bool {
            return $mail->hasTo($signerA->email);
        });
        Mail::assertQueued(NotarySignerVideoInvitationMail::class, function (NotarySignerVideoInvitationMail $mail) use ($signerB): bool {
            return $mail->hasTo($signerB->email);
        });

        $this->assertSame(NotaryRequestStatus::SessionScheduled, $request->fresh()->status);
    }

    public function test_video_join_route_redirects_to_meeting_url(): void
    {
        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
        ]);
        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create();

        $session = $request->sessions()->create([
            'notary_user_id' => $notary->id,
            'notary_signer_id' => $party->id,
            'provider_name' => 'jitsi',
            'status' => 'scheduled',
            'room_name' => 'docutrust-test-room',
            'meeting_url' => 'https://meet.jit.si/docutrust-test-room',
            'access_token' => 'test-video-token-123',
            'scheduled_for' => now()->addDay(),
        ]);

        $response = $this->get(route('enotary.video.join', ['token' => $session->access_token]));

        $response->assertRedirect('https://meet.jit.si/docutrust-test-room');
    }

    public function test_send_linked_document_submits_draft_request(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Draft,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Draft,
        ]);

        $signer = DocumentSigner::factory()->for($document)->create([
            'signing_order' => 1,
        ]);

        SignatureField::factory()->for($document)->create([
            'signer_id' => $signer->id,
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request])
            ->call('sendLinkedDocument', $document->id)
            ->assertHasNoErrors();

        $this->assertSame(NotaryRequestStatus::Submitted, $request->fresh()->status);
        $this->assertSame(DocumentStatus::Pending, $document->fresh()->status);
    }

    public function test_document_completed_event_triggers_video_invitations_for_enotary_document(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
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

        event(new DocumentCompleted($document));

        Mail::assertQueued(NotarySignerVideoInvitationMail::class);
    }

    public function test_signer_completed_event_triggers_video_invitations_when_all_parties_signed(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Pending,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
        ]);

        $documentSigner = DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'name' => $party->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        event(new DocumentSignerCompleted($document, $documentSigner));

        Mail::assertQueued(NotarySignerVideoInvitationMail::class);
        $this->assertDatabaseHas('notary_sessions', [
            'notary_request_id' => $request->id,
            'notary_signer_id' => $party->id,
        ]);
    }

    public function test_each_signed_party_receives_a_unique_video_link(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $signerA = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'alice@example.test',
        ]);
        $signerB = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'bob@example.test',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $signerA->email,
            'name' => $signerA->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        DocumentSigner::factory()->for($document)->create([
            'email' => $signerB->email,
            'name' => $signerB->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $service = app(NotarySignerVideoInvitationService::class);
        $service->inviteAllSignersWhenReady($request->fresh(['signers', 'sessions', 'notary', 'documents.documentSigners']));

        $sessions = $request->fresh(['sessions'])->sessions;
        $this->assertCount(2, $sessions);
        $this->assertNotSame($sessions[0]->access_token, $sessions[1]->access_token);
        $this->assertNotSame($sessions[0]->room_name, $sessions[1]->room_name);
        $this->assertNotSame(
            $service->signerVideoJoinUrl($sessions[0]),
            $service->signerVideoJoinUrl($sessions[1]),
        );

        $parties = $service->partiesForVideoVerification($request->fresh(['signers', 'sessions', 'documents.documentSigners']));
        $this->assertCount(2, $parties);
        $this->assertCount(2, collect($parties)->pluck('join_url')->unique());
    }

    public function test_video_tab_lists_signed_parties_with_join_links(): void
    {
        Mail::fake();

        $notary = User::factory()->notary()->create();
        $request = NotaryRequest::factory()->for($notary)->create([
            'notary_user_id' => $notary->id,
            'status' => NotaryRequestStatus::Submitted,
        ]);

        $document = Document::factory()->for($notary)->create([
            'notary_request_id' => $request->id,
            'status' => DocumentStatus::Completed,
        ]);

        $party = NotarySigner::factory()->for($request, 'notaryRequest')->create([
            'email' => 'signer@example.test',
            'full_name' => 'Jane Signer',
        ]);

        DocumentSigner::factory()->for($document)->create([
            'email' => $party->email,
            'name' => $party->full_name,
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->actingAs($notary);

        LivewireVolt::test('notary-requests.show', ['notaryRequest' => $request->fresh()])
            ->call('openVideoSessionWorkspace')
            ->assertHasNoErrors()
            ->assertSee('Parties — individual video links')
            ->assertSee('Jane Signer')
            ->assertSee('Personal video link for Jane Signer')
            ->assertSee('enotary/video/');

        Mail::assertSent(NotarySignerVideoInvitationMail::class);
    }
}
