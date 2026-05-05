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
use App\Services\DocumentPdfStampingService;
use App\Services\DocumentHashService;
use App\Services\PkiSignatureService;
use App\Services\SignerCertificateService;
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
        if ($signer === null || ! $this->hasViewAccessLink($signer)) {
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
        if ($signer === null || ! $this->hasViewAccessLink($signer)) {
            return $this->invalidLinkResponse();
        }

        $document = Document::query()->findOrFail($signer->document_id);

        // Use the original source PDF for the live signing view so the interactive
        // overlay and the visible document share the same coordinate basis.
        return PublicPdfStream::inlineResponse($document->sourcePdfPath() ?: $document->activeSigningPdfPath());
    }

    public function sign(string $token, DocumentPdfStampingService $documentPdfStampingService): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveSignerFromToken($token);
            if ($signer === null || ! $this->hasViewAccessLink($signer)) {
                return $this->invalidLinkResponse();
            }

            $document = Document::query()
                ->with('documentSigners')
                ->findOrFail($signer->document_id);

            $signingError = $this->canSignerModifyFields($document, $signer);
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
                $documentPdfStampingService->generateFinalPdf($document->fresh());
                $completedDocument = $document->fresh();
                SignatureAuditLogger::documentCompleted($completedDocument, (string) request()->ip());
                GenerateCertificateJob::dispatchSync($completedDocument->id);
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
        DocumentPdfStampingService $documentPdfStampingService,
        PkiSignatureService $pkiSignatureService,
        SignerCertificateService $signerCertificateService,
    ): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveSignerFromToken($token);
            if ($signer === null || ! $this->hasViewAccessLink($signer)) {
                return $this->invalidLinkResponse();
            }

            $document = Document::query()
                ->with('documentSigners')
                ->findOrFail($signer->document_id);

            $signingError = $this->canSignerModifyFields($document, $signer);
            if ($signingError !== null) {
                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', $signingError);
            }

            $field = SignatureField::query()->findOrFail($request->validated('signature_field_id'));

            if ($field->document_id !== $document->id || $field->signer_id !== $signer->id) {
                abort(403);
            }

            $this->ensureSignerKeyPair($signer, $pkiSignatureService);
            $signerCertificate = $signerCertificateService->getOrIssueForSigner($signer->fresh());

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

            $signatureImagePath = $this->storeSubmittedSignatureImage($request->validated('signature_image'));
            $existingSignature = Signature::query()
                ->where('signature_field_id', $field->id)
                ->where('signer_id', $signer->id)
                ->first();

            $signature = Signature::query()->updateOrCreate(
                [
                    'signature_field_id' => $field->id,
                ],
                [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'signer_certificate_id' => $signerCertificate->id,
                'signature_path' => $signatureImagePath,
                'signature_value' => $signatureValue,
                'signature_hash' => $documentHash,
                'public_key_fingerprint' => $pkiSignatureService->fingerprint($signer->signing_public_key),
                'signature_algorithm' => 'RSA-SHA256',
                'position_data' => null,
                ]
            );

            $this->deleteStoredSignatureIfReplaced($existingSignature, $signatureImagePath);

            SignatureAuditLogger::fieldSigned($document, $signer, (string) $request->ip());

            $this->completeSignerIfAllFieldsSigned($signer, $document, $documentPdfStampingService);

            $fieldType = $field->type->value;
            $isSignatureField = in_array($fieldType, ['signature', 'signature_left', 'signature_right'], true);

            return redirect()->route('sign.show', $this->signerRouteToken($signer))
                ->with('status', $existingSignature !== null
                    ? ($isSignatureField ? __('Signature updated.') : __('Field updated.'))
                    : ($isSignatureField ? __('Signature saved.') : __('Field saved.')));
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
        if ($signer === null || ! $this->hasViewAccessLink($signer)) {
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
            'Content-Type' => $disk->mimeType($signature->signature_path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=600, stale-while-revalidate=3600',
        ]);
    }

    private function resolveSignerFromToken(string $token): ?DocumentSigner
    {
        return DocumentSigner::query()->where('access_token', $token)->first();
    }

    private function hasViewAccessLink(DocumentSigner $signer): bool
    {
        if ($signer->access_token === null || $signer->access_token === '') {
            return false;
        }

        if ($signer->expires_at !== null && $signer->expires_at->isPast()) {
            return false;
        }

        return $signer->document()
            ->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Completed])
            ->exists();
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

    private function canSignerModifyFields(Document $document, DocumentSigner $signer): ?string
    {
        if (in_array($document->status, [DocumentStatus::Declined, DocumentStatus::Cancelled], true)) {
            return __('This document can no longer be signed.');
        }

        if ($document->status !== DocumentStatus::Pending) {
            return __('This document is not available for signing.');
        }

        if ($signer->status === DocumentSignerStatus::Pending) {
            return $this->canSignerSign($document, $signer);
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

    private function completeSignerIfAllFieldsSigned(DocumentSigner $signer, Document $document, DocumentPdfStampingService $documentPdfStampingService): void
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

        if ($signer->status === DocumentSignerStatus::Signed) {
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
            $documentPdfStampingService->generateFinalPdf($document->fresh());
            $completedDocument = $document->fresh();
            SignatureAuditLogger::documentCompleted($completedDocument, (string) request()->ip());
            GenerateCertificateJob::dispatchSync($completedDocument->id);
            event(new DocumentCompleted($completedDocument));
        }
    }

    private function storeSubmittedSignatureImage(?string $dataUrl): ?string
    {
        if (! is_string($dataUrl) || $dataUrl === '') {
            return null;
        }

        if (! preg_match('/^data:image\/(?P<type>png|jpeg|jpg|webp);base64,(?P<data>.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode((string) $matches['data'], true);
        if ($binary === false) {
            return null;
        }

        $extension = $matches['type'] === 'jpeg' ? 'jpg' : (string) $matches['type'];
        $path = 'signatures/'.\Illuminate\Support\Str::uuid()->toString().'.'.$extension;
        Storage::disk($this->secureDiskName())->put($path, $binary);

        return $path;
    }

    private function deleteStoredSignatureIfReplaced(?Signature $existingSignature, ?string $newPath): void
    {
        $existingPath = $existingSignature?->signature_path;
        if (! is_string($existingPath) || $existingPath === '' || $existingPath === $newPath) {
            return;
        }

        $disk = Storage::disk($this->secureDiskName());
        if ($disk->exists($existingPath)) {
            $disk->delete($existingPath);
        }
    }
}
