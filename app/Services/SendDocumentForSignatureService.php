<?php

namespace App\Services;

use App\Enums\DocumentStatus;
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

        if ($document->status !== DocumentStatus::Draft) {
            throw new RuntimeException(__('Only draft documents can be sent for signature.'));
        }

        if (! $document->hasDocumentSigners()) {
            throw new RuntimeException(__('Add at least one signer before sending.'));
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

        GenerateDocumentPdfJob::dispatch($document->id, 'prepared');
        $document->refresh()->load('documentSigners');

        event(new DocumentSent($document));
    }
}
