<?php

namespace App\Console\Commands;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentNotificationService;
use Illuminate\Console\Command;

class SendPendingSignatureReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-pending-signature-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for pending signatures';

    /**
     * Execute the console command.
     */
    public function handle(DocumentNotificationService $notificationService): int
    {
        $documents = Document::query()
            ->with(['documentSigners', 'user'])
            ->where('status', DocumentStatus::Pending)
            ->whereHas('documentSigners', function ($query): void {
                $query->where('status', DocumentSignerStatus::Pending)
                    ->whereIn('role_type', ['signer', 'approver']);
            })
            ->get();

        $reminderCount = 0;

        foreach ($documents as $document) {
            $documentReminderCount = 0;

            foreach ($document->documentSigners as $signer) {
                if ($signer->status !== DocumentSignerStatus::Pending) {
                    continue;
                }

                if (! $signer->requiresAction()) {
                    continue;
                }

                if ($signer->access_token === null || $signer->access_token === '') {
                    continue;
                }

                if ($signer->expires_at !== null && $signer->expires_at->isPast()) {
                    continue;
                }

                $notificationService->sendReminder($document, $signer);
                $reminderCount++;
                $documentReminderCount++;
            }

            if ($documentReminderCount > 0) {
                $notificationService->createInAppNotification(
                    $document->user_id,
                    'document.reminder',
                    __('Sent :count reminder(s) for ":title".', [
                        'count' => $documentReminderCount,
                        'title' => $document->title,
                    ])
                );
            }
        }

        $this->info("Sent {$reminderCount} reminder email(s).");

        return self::SUCCESS;
    }
}
