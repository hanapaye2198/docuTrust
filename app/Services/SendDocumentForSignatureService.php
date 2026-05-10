<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Events\DocumentSent;
use App\Jobs\GenerateDocumentPdfJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Support\Str;
use RuntimeException;

class SendDocumentForSignatureService
{
    public function send(Document $document): void
    {
        $document->refresh()->load(['documentSigners', 'signatureFields']);

        if ($document->usesSequentialSigningWorkflow()) {
            $this->normalizeSequentialSigningOrder($document);
            $document->refresh()->load(['documentSigners', 'signatureFields']);
        }

        if ($document->status !== DocumentStatus::Draft) {
            throw new RuntimeException(__('Only draft documents can be sent for signature.'));
        }

        if (! $document->hasActionableParticipants()) {
            throw new RuntimeException(__('Add at least one signer or approver before sending.'));
        }

        if (! $document->hasSignatureFields()) {
            throw new RuntimeException(__('Add at least one signature field on the Prepare page before sending.'));
        }

        $signersWithoutFields = $document->signersMissingFields()
            ->pluck('name')
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->values();

        if ($signersWithoutFields->isNotEmpty()) {
            throw new RuntimeException(__('Every signer must have at least one signature field before sending. Missing: :signers', [
                'signers' => $signersWithoutFields->implode(', '),
            ]));
        }

        if (! $document->workflowConfigurationIsValid()) {
            throw new RuntimeException(__('Sequential signing requires every signer to have a unique signing order.'));
        }

        $accountLinkedSignerMissingUser = $document->documentSigners
            ->first(fn (DocumentSigner $signer): bool => $signer->requiresAction() && $signer->signingMethod() === SigningMethod::AccountVerified && $signer->user_id === null);

        if ($accountLinkedSignerMissingUser !== null) {
            throw new RuntimeException(__('Signer :name must be linked to a verified DocuTrust account before sending.', [
                'name' => $accountLinkedSignerMissingUser->name,
            ]));
        }

        $document->update([
            'status' => DocumentStatus::Pending,
            'sent_at' => now(),
        ]);

        $document->documentSigners()->get()->each(function (DocumentSigner $signer): void {
            $signer->update([
                'access_token' => (string) Str::uuid(),
                'expires_at' => now()->addDays(7),
            ]);
        });

        GenerateDocumentPdfJob::dispatchSync($document->id, 'prepared');
        $document->refresh()->load('documentSigners');

        event(new DocumentSent($document));
    }

    private function normalizeSequentialSigningOrder(Document $document): void
    {
        $document->documentSigners()
            ->orderByRaw('CASE WHEN signing_order IS NULL OR signing_order < 1 THEN 999999 ELSE signing_order END')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(function (DocumentSigner $signer, int $index): void {
                $signer->update(['signing_order' => $index + 1]);
            });
    }
}
