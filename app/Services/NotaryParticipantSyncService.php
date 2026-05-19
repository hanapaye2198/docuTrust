<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\NotaryRequest;
use App\Models\NotarySigner;
use RuntimeException;

class NotaryParticipantSyncService
{
    public function syncRequestSignersToDocument(Document $document): void
    {
        if ($document->notary_request_id === null) {
            return;
        }

        $document->loadMissing(['notaryRequest.signers', 'documentSigners']);

        $request = $document->notaryRequest;
        if ($request === null) {
            return;
        }

        $requestSigners = $request->signers;
        if ($requestSigners->isEmpty()) {
            return;
        }

        $externalDocumentSigners = $document->documentSigners
            ->filter(fn (DocumentSigner $signer): bool => $this->isRequestManagedDocumentSigner($signer, $request));

        $matchedDocumentSignerIds = [];
        $nextSigningOrder = max(
            1,
            ((int) $document->documentSigners->max('signing_order')) + 1,
        );

        foreach ($requestSigners as $requestSigner) {
            $documentSigner = $this->resolveMatchingDocumentSigner($requestSigner, $externalDocumentSigners, $matchedDocumentSignerIds);

            if ($documentSigner === null) {
                $createdSigner = $document->documentSigners()->create([
                    'name' => $requestSigner->full_name,
                    'email' => $requestSigner->email,
                    'role_type' => TemplateRoleType::Signer,
                    'signing_method' => SigningMethod::EmailLink,
                    'status' => 'pending',
                    'signing_order' => $nextSigningOrder++,
                ]);

                $matchedDocumentSignerIds[] = (int) $createdSigner->id;

                continue;
            }

            $matchedDocumentSignerIds[] = (int) $documentSigner->id;

            $documentSigner->update([
                'name' => $requestSigner->full_name,
                'email' => $requestSigner->email,
                'role_type' => TemplateRoleType::Signer,
                'signing_method' => SigningMethod::EmailLink,
            ]);
        }
    }

    public function syncRequestSignersToDocuments(NotaryRequest $request): void
    {
        $request->loadMissing('documents');

        foreach ($request->documents as $document) {
            $this->syncRequestSignersToDocument($document);
        }
    }

    public function removeRequestSigner(NotarySigner $requestSigner): void
    {
        $requestSigner->loadMissing('notaryRequest.documents.documentSigners');

        $request = $requestSigner->notaryRequest;
        if ($request === null) {
            $requestSigner->delete();

            return;
        }

        foreach ($request->documents as $document) {
            $documentSigner = $this->resolveMatchingDocumentSigner($requestSigner, $document->documentSigners, []);

            if ($documentSigner === null) {
                continue;
            }

            if ($document->status !== DocumentStatus::Draft) {
                throw new RuntimeException(__('This signer is already tied to an in-progress signing document and cannot be removed.'));
            }

            if ($documentSigner->signatures()->exists() || $documentSigner->hasCompletedAction()) {
                throw new RuntimeException(__('This signer already has signing activity and cannot be removed.'));
            }

            $documentSigner->signatureFields()->delete();
            $documentSigner->delete();
        }

        $requestSigner->delete();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DocumentSigner>  $documentSigners
     * @param  list<int>  $excludedSignerIds
     */
    private function resolveMatchingDocumentSigner(
        NotarySigner $requestSigner,
        \Illuminate\Support\Collection $documentSigners,
        array $excludedSignerIds
    ): ?DocumentSigner {
        $email = $this->normalize($requestSigner->email);
        $name = $this->normalize($requestSigner->full_name);

        $byEmail = $documentSigners->first(function (DocumentSigner $signer) use ($email, $excludedSignerIds): bool {
            return ! in_array((int) $signer->id, $excludedSignerIds, true)
                && $this->normalize($signer->email) === $email;
        });

        if ($byEmail instanceof DocumentSigner) {
            return $byEmail;
        }

        $byName = $documentSigners->first(function (DocumentSigner $signer) use ($name, $excludedSignerIds): bool {
            return ! in_array((int) $signer->id, $excludedSignerIds, true)
                && $this->normalize($signer->name) === $name;
        });

        return $byName instanceof DocumentSigner ? $byName : null;
    }

    private function isRequestManagedDocumentSigner(DocumentSigner $signer, NotaryRequest $request): bool
    {
        return $signer->roleType() === TemplateRoleType::Signer
            && (int) $signer->user_id !== (int) $request->notary_user_id;
    }

    private function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
