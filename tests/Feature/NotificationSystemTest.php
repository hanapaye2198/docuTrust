<?php

namespace Tests\Feature;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SignatureFieldType;
use App\Mail\DocumentCompletedMail;
use App\Mail\DocumentSentToSignerMail;
use App\Mail\DocumentSignedMail;
use App\Mail\PendingSignatureReminderMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignatureField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_sent_triggers_email_and_in_app_notification(): void
    {
        Mail::fake();

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

        Mail::assertSent(DocumentSentToSignerMail::class, 1);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.sent',
        ]);
    }

    public function test_signer_completed_triggers_signed_and_completed_notifications(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        $signer = DocumentSigner::factory()->for($document)->create(['status' => DocumentSignerStatus::Pending]);

        $this->post(route('sign.store', $signer->access_token))->assertRedirect();

        Mail::assertSent(DocumentSignedMail::class);
        Mail::assertSent(DocumentCompletedMail::class, 1);

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
        Mail::fake();

        $owner = User::factory()->create();
        $document = Document::factory()->for($owner)->create(['status' => DocumentStatus::Pending]);
        DocumentSigner::factory()->for($document)->create([
            'status' => DocumentSignerStatus::Pending,
        ]);

        $this->assertSame(0, $this->artisan('app:send-pending-signature-reminders'));

        Mail::assertSent(PendingSignatureReminderMail::class, 1);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id,
            'type' => 'document.reminder',
        ]);
    }
}
