<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Jobs\SendDocumentEmailJob;
use App\Jobs\SendReminderJob;
use App\Mail\ReminderMail;
use App\Mail\SignerInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_sent_triggers_email_and_in_app_notification(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Draft]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);
        SignatureField::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'type' => SignatureFieldType::Signature,
            'position_data' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.05],
        ]);

        $this->actingAs($owner);
        LivewireVolt::test('documents.show', ['document' => $document])->call('sendForSignature');

        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id
                && $job->recipientEmail === $signer->email
                && $job->type === SendDocumentEmailJob::TYPE_SENT_TO_SIGNER;
        });

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.sent',
        ]);
    }

    public function test_signer_completed_triggers_signed_and_completed_notifications(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->post(route('sign.store', $signer->access_token))->assertRedirect();

        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id
                && $job->recipientEmail === $signer->email
                && $job->type === SendDocumentEmailJob::TYPE_SIGNED;
        });

        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $signer, $owner): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id
                && $job->recipientEmail === $owner->email
                && $job->type === SendDocumentEmailJob::TYPE_SIGNED;
        });

        Queue::assertPushed(SendDocumentEmailJob::class, function (SendDocumentEmailJob $job) use ($document, $owner): bool {
            return $job->documentId === $document->id
                && $job->signerId === null
                && $job->recipientEmail === $owner->email
                && $job->type === SendDocumentEmailJob::TYPE_COMPLETED;
        });

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.signed',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.completed',
        ]);
    }

    public function test_pending_signature_reminder_command_sends_emails_and_in_app_notification(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->assertSame(0, $this->artisan('app:send-pending-signature-reminders'));

        Queue::assertPushed(SendReminderJob::class, function (SendReminderJob $job) use ($document, $signer): bool {
            return $job->documentId === $document->id
                && $job->signerId === $signer->id;
        });

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.reminder',
        ]);
    }

    public function test_signer_invitation_mail_carries_document_password_hint(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
            'access_password_hash' => 'hashed-password-placeholder',
            'access_password_hint' => 'Shared in chat',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        (new SendDocumentEmailJob(
            documentId: $document->id,
            signerId: $signer->id,
            recipientEmail: $signer->email,
            type: SendDocumentEmailJob::TYPE_SENT_TO_SIGNER,
            signUrl: route('sign.show', $signer->access_token),
        ))->handle();

        Mail::assertQueued(SignerInvitationMail::class, function (SignerInvitationMail $mail): bool {
            return $mail->requiresDocumentPassword === true
                && $mail->documentPasswordHint === 'Shared in chat';
        });
    }

    public function test_signature_reminder_mail_carries_document_password_hint(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create([
            'status' => DocumentStatus::Pending,
            'access_password_hash' => 'hashed-password-placeholder',
            'access_password_hint' => 'Shared in chat',
        ]);
        $signer = DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        (new SendReminderJob(
            documentId: $document->id,
            signerId: $signer->id,
        ))->handle();

        Mail::assertQueued(ReminderMail::class, function (ReminderMail $mail): bool {
            return $mail->requiresDocumentPassword === true
                && $mail->documentPasswordHint === 'Shared in chat';
        });
    }
}
