<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SendReminderJob;
use App\Livewire\DocumentSignersManager;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class EmailResendAndReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_invitation_dispatches_email_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
            'email_subject' => 'Custom subject',
            'email_message' => 'Custom message body',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('resendInvitation', $signer->id)
            ->assertHasNoErrors();

        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id
                && $job->recipientEmail === $signer->email
                && $job->type === SendDocumentEmailJob::TYPE_SENT_TO_SIGNER
                && $job->signUrl !== null;
        });
    }

    public function test_resend_invitation_blocked_for_non_pending_document(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Draft,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('resendInvitation', $signer->id);

        Queue::assertNotPushed(SendDocumentEmailJob::class);
    }

    public function test_resend_invitation_blocked_for_completed_signer(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('resendInvitation', $signer->id);

        Queue::assertNotPushed(SendDocumentEmailJob::class);
    }

    public function test_send_reminder_dispatches_reminder_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
            'email_subject' => 'Custom subject',
            'email_message' => 'Custom message body',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('sendReminder', $signer->id)
            ->assertHasNoErrors();

        Queue::assertPushed(SendReminderJob::class, function (SendReminderJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id;
        });
    }

    public function test_send_reminder_blocked_for_draft_document(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Draft,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('sendReminder', $signer->id);

        Queue::assertNotPushed(SendReminderJob::class);
    }

    public function test_send_reminder_blocked_for_signed_signer(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Signed,
        ]);

        $this->actingAs($owner);

        Livewire::test(DocumentSignersManager::class, ['documentId' => $document->id])
            ->call('sendReminder', $signer->id);

        Queue::assertNotPushed(SendReminderJob::class);
    }
}
