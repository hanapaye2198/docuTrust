<?php

namespace App\Http\Controllers;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Events\DocumentCompleted;
use App\Events\DocumentSignerCompleted;
use App\Http\Requests\StoreDocumentSignatureRequest;
use App\Jobs\GenerateCertificateJob;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Services\DocumentHashService;
use App\Services\PkiSignatureService;
use App\Services\SignatureAuditLogger;
use App\Support\PublicPdfStream;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignDocumentController extends Controller
{
    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function show(string $token): View|Response
    {
        $signer = $this->resolveSignerFromToken($token);
        if ($signer === null || ! $this->hasValidAccessLink($signer)) {
            return $this->invalidLinkResponse();
        }

        $signer->load([
            'document' => fn ($q) => $q->withCount('signatureFields'),
            'document.signatureFields',
            'signatures' => fn ($q) => $q->whereNotNull('signature_field_id'),
        ]);

        $document = $signer->document;

        $fieldsForSigner = $document->signatureFields
            ->where('signer_id', $signer->id)
            ->values();

        $signedByFieldId = [];
        foreach ($signer->signatures as $signature) {
            if ($signature->signature_field_id !== null && is_string($signature->signature_path) && $signature->signature_path !== '') {
                $signedByFieldId[$signature->signature_field_id] = route('sign.signature.image', [
                    'token' => $this->signerRouteToken($signer),
                    'signatureField' => $signature->signature_field_id,
                ]);
            }
        }

        return view('sign.show', [
            'signer' => $signer,
            'pdfUrl' => route('sign.document.pdf', $this->signerRouteToken($signer)),
            'documentHasSignatureFields' => ($document->signature_fields_count > 0),
            'fieldsJson' => $fieldsForSigner->map(fn (SignatureField $f) => [
                'id' => $f->id,
                'type' => $f->type->value,
                'page_number' => $f->page_number ?? 1,
                'position_data' => $f->position_data,
            ])->values()->all(),
            'signedByFieldId' => $signedByFieldId,
        ]);
    }

    public function streamPdf(string $token): StreamedResponse|Response
    {
        $signer = $this->resolveSignerFromToken($token);
        if ($signer === null || ! $this->hasValidAccessLink($signer)) {
            return $this->invalidLinkResponse();
        }

        $document = Document::query()->findOrFail($signer->document_id);

        return PublicPdfStream::inlineResponse($document->primaryPdfPath());
    }

    public function sign(string $token): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveSignerFromToken($token);
            if ($signer === null || ! $this->hasValidAccessLink($signer)) {
                return $this->invalidLinkResponse();
            }

            $document = Document::query()
                ->with('documentSigners')
                ->findOrFail($signer->document_id);

            $signingError = $this->canSignerSign($document, $signer);
            if ($signingError !== null) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', $signingError);
            }

            if ($document->signatureFields()->exists()) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', __('This document must be signed using the signature fields on the document.'));
            }

            $hasFieldsForSigner = $document->signatureFields()
                ->where('signer_id', $signer->id)
                ->exists();

            if ($hasFieldsForSigner) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', __('Please complete each signature field on the document.'));
            }

            $signer->update([
                'status' => DocumentSignerStatus::Signed,
                'signed_at' => now(),
            ]);
            event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

            $document->refresh()->load('documentSigners');

            if ($document->allSignersHaveSigned()) {
                $document->update(['status' => DocumentStatus::Completed]);
                $completedDocument = $document->fresh();
                SignatureAuditLogger::documentCompleted($completedDocument, (string) request()->ip());
                GenerateCertificateJob::dispatch($completedDocument->id);
                event(new DocumentCompleted($completedDocument));
            }

            Log::channel('audit')->info('Document signed (legacy flow)', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'ip_address' => (string) request()->ip(),
            ]);

            return redirect()->route('sign.show', $this->signerRouteToken($signer))
                ->with('status', __('Thank you. Your signature has been recorded.'));
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Document signing failed', [
                'token' => $token,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'ip_address' => (string) request()->ip(),
            ]);

            return redirect()->route('sign.show', $token)
                ->with('error', __('Unable to complete signing right now. Please try again.'));
        }
    }

    public function storeSignature(
        StoreDocumentSignatureRequest $request,
        string $token,
        DocumentHashService $documentHashService,
        PkiSignatureService $pkiSignatureService,
    ): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveSignerFromToken($token);
            if ($signer === null || ! $this->hasValidAccessLink($signer)) {
                return $this->invalidLinkResponse();
            }

            $document = Document::query()
                ->with('documentSigners')
                ->findOrFail($signer->document_id);

            $signingError = $this->canSignerSign($document, $signer);
            if ($signingError !== null) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', $signingError);
            }

            $field = SignatureField::query()->findOrFail($request->validated('signature_field_id'));

            if ($field->document_id !== $document->id || $field->signer_id !== $signer->id) {
                abort(403);
            }

            if (Signature::query()->where('signature_field_id', $field->id)->exists()) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', __('This field has already been signed.'));
            }

            $this->ensureSignerKeyPair($signer, $pkiSignatureService);

            if (! is_string($signer->signing_private_key) || $signer->signing_private_key === '') {
                throw new \RuntimeException('Signer private key unavailable.');
            }

            if (! is_string($signer->signing_public_key) || $signer->signing_public_key === '') {
                throw new \RuntimeException('Signer public key unavailable.');
            }

            $documentHash = $documentHashService->generateHashForDocument($document);
            $signatureValue = $pkiSignatureService->signHash($documentHash, $signer->signing_private_key);
            $signatureIsValid = $pkiSignatureService->verifySignature($documentHash, $signatureValue, $signer->signing_public_key);

            if (! $signatureIsValid) {
                throw new \RuntimeException('Digital signature verification failed after signing.');
            }

            Signature::query()->create([
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'signature_field_id' => $field->id,
                'signature_path' => null,
                'signature_value' => $signatureValue,
                'signature_hash' => $documentHash,
                'public_key_fingerprint' => $pkiSignatureService->fingerprint($signer->signing_public_key),
                'position_data' => null,
            ]);

            SignatureAuditLogger::fieldSigned($document, $signer, (string) $request->ip());

            $this->completeSignerIfAllFieldsSigned($signer, $document);

            return redirect()->route('sign.show', $this->signerRouteToken($signer))
                ->with('status', __('Signature saved.'));
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Field signature submission failed', [
                'token' => $token,
                'signature_field_id' => $request->input('signature_field_id'),
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'ip_address' => (string) $request->ip(),
            ]);

            return redirect()->route('sign.show', $token)
                ->with('error', __('Unable to save your signature right now. Please try again.'));
        }
    }

    public function streamSignatureImage(string $token, SignatureField $signatureField): StreamedResponse|Response
    {
        $signer = $this->resolveSignerFromToken($token);
        if ($signer === null || ! $this->hasValidAccessLink($signer)) {
            return $this->invalidLinkResponse();
        }

        if ($signatureField->document_id !== $signer->document_id || $signatureField->signer_id !== $signer->id) {
            abort(404);
        }

        $signature = Signature::query()
            ->where('signature_field_id', $signatureField->id)
            ->first();

        if ($signature === null || ! is_string($signature->signature_path) || $signature->signature_path === '') {
            abort(404);
        }

        $disk = Storage::disk($this->secureDiskName());
        if (! $disk->exists($signature->signature_path)) {
            abort(404);
        }

        $content = $disk->get($signature->signature_path);

        return response($content, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=600, stale-while-revalidate=3600',
        ]);
    }

    private function resolveSignerFromToken(string $token): ?DocumentSigner
    {
        return DocumentSigner::query()->where('access_token', $token)->first();
    }

    private function hasValidAccessLink(DocumentSigner $signer): bool
    {
        if ($signer->access_token === null || $signer->access_token === '') {
            return false;
        }

        if ($signer->expires_at !== null && $signer->expires_at->isPast()) {
            return false;
        }

        return $signer->document()->where('status', DocumentStatus::Pending)->exists();
    }

    private function signerRouteToken(DocumentSigner $signer): string
    {
        return $signer->access_token ?? (string) $signer->id;
    }

    private function invalidLinkResponse(): Response
    {
        return response()->view('sign.invalid', [
            'message' => __('Link expired or invalid'),
        ], 403);
    }

    private function canSignerSign(Document $document, DocumentSigner $signer): ?string
    {
        if ($signer->status === DocumentSignerStatus::Signed) {
            return __('You have already signed this document.');
        }

        if (in_array($document->status, [DocumentStatus::Declined, DocumentStatus::Cancelled], true)) {
            return __('This document can no longer be signed.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            return __('This document is not available for signing.');
        }

        if (! $this->usesSequentialSigning($document)) {
            return null;
        }

        if ($signer->signing_order === null) {
            return null;
        }

        $hasUnsignedPreviousSigner = $document->documentSigners->contains(function (DocumentSigner $otherSigner) use ($signer): bool {
            if ($otherSigner->id === $signer->id) {
                return false;
            }

            if ($otherSigner->signing_order === null) {
                return false;
            }

            if ($otherSigner->signing_order >= $signer->signing_order) {
                return false;
            }

            return $otherSigner->status !== DocumentSignerStatus::Signed;
        });

        if ($hasUnsignedPreviousSigner) {
            return __('You cannot sign yet. Previous signer has not completed signing.');
        }

        return null;
    }

    private function usesSequentialSigning(Document $document): bool
    {
        return $document->documentSigners->contains(
            fn (DocumentSigner $documentSigner): bool => $documentSigner->signing_order !== null
        );
    }

    private function ensureSignerKeyPair(DocumentSigner $signer, PkiSignatureService $pkiSignatureService): void
    {
        if (
            is_string($signer->signing_public_key) && $signer->signing_public_key !== ''
            && is_string($signer->signing_private_key) && $signer->signing_private_key !== ''
        ) {
            return;
        }

        $keys = $pkiSignatureService->generateKeyPair();
        $signer->update([
            'signing_public_key' => $keys['public_key'],
            'signing_private_key' => $keys['private_key'],
        ]);
        $signer->refresh();
    }

    private function completeSignerIfAllFieldsSigned(DocumentSigner $signer, Document $document): void
    {
        $fieldIds = $document->signatureFields()
            ->where('signer_id', $signer->id)
            ->pluck('id');

        if ($fieldIds->isEmpty()) {
            return;
        }

        $signedCount = Signature::query()
            ->where('signer_id', $signer->id)
            ->whereIn('signature_field_id', $fieldIds)
            ->count();

        if ($signedCount < $fieldIds->count()) {
            return;
        }

        $signer->update([
            'status' => DocumentSignerStatus::Signed,
            'signed_at' => now(),
        ]);
        event(new DocumentSignerCompleted($document->fresh(), $signer->fresh()));

        $document->refresh()->load('documentSigners');

        if ($document->allSignersHaveSigned()) {
            $document->update(['status' => DocumentStatus::Completed]);
            $completedDocument = $document->fresh();
            SignatureAuditLogger::documentCompleted($completedDocument, (string) request()->ip());
            GenerateCertificateJob::dispatch($completedDocument->id);
            event(new DocumentCompleted($completedDocument));
        }
    }
}
