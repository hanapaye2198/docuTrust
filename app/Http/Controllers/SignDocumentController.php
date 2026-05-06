<?php

namespace App\Http\Controllers;

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Http\Requests\StartTrustAuthorizationRequest;
use App\Http\Requests\StoreDocumentSignatureRequest;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Models\TrustAuthorizationSession;
use App\Services\DocumentSigningWorkflowService;
use App\Services\FieldSignatureCaptureService;
use App\Support\PublicPdfStream;
use App\Trust\Authorization\TrustAuthorizationRequiredException;
use App\Trust\Authorization\TrustAuthorizationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignDocumentController extends Controller
{
    private const DOCUMENT_UNLOCK_SESSION_PREFIX = 'document_access_unlocked:';

    public function __construct(
        private readonly DocumentSigningWorkflowService $documentSigningWorkflowService,
        private readonly FieldSignatureCaptureService $fieldSignatureCaptureService,
        private readonly TrustAuthorizationWorkflowService $trustAuthorizationWorkflowService,
    ) {}

    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function show(string $token): View|Response
    {
        $signer = $this->resolveAccessibleSigner($token, [
            'document' => fn ($q) => $q->withCount('signatureFields'),
            'document.signatureFields',
            'signatures' => fn ($q) => $q->whereNotNull('signature_field_id'),
        ]);

        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        $document = $signer->document;
        $documentAccessProtected = $document->hasAccessPassword();
        $documentAccessLocked = $documentAccessProtected && ! $this->documentAccessUnlocked($document);
        $signingAvailabilityMessage = ! $documentAccessLocked
            ? $this->documentSigningWorkflowService->canSignerModifyFields($document->loadMissing('documentSigners'), $signer)
            : null;
        $trustAuthorizationEnabled = (string) config('docutrust.pki.signing_backend', 'app_managed') === 'remote_managed';
        $trustAuthorizationSession = $trustAuthorizationEnabled
            ? $signer->trustAuthorizationSessions()
                ->latest('id')
                ->first()
            : null;
        $trustAuthorizationSessionActive = $trustAuthorizationSession !== null
            && $trustAuthorizationSession->status === 'authorized'
            && ($trustAuthorizationSession->expires_at === null || $trustAuthorizationSession->expires_at->isFuture());

        $fieldsForSigner = $document->signatureFields
            ->where('signer_id', $signer->id)
            ->values();

        $signedByFieldId = [];
        foreach ($signer->signatures as $signature) {
            if ($signature->signature_field_id === null) {
                continue;
            }

            $signedByFieldId[$signature->signature_field_id] = $this->signatureFieldResponsePayload($signature, $signer);
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
            'documentAccessProtected' => $documentAccessProtected,
            'documentAccessLocked' => $documentAccessLocked,
            'documentAccessHint' => $document->access_password_hint,
            'signingAvailabilityMessage' => $signingAvailabilityMessage,
            'trustAuthorizationEnabled' => $trustAuthorizationEnabled,
            'trustAuthorizationSession' => $trustAuthorizationSession !== null
                ? $this->trustAuthorizationSessionPayload($trustAuthorizationSession)
                : null,
            'trustAuthorizationSessionActive' => $trustAuthorizationSessionActive,
        ]);
    }

    public function streamPdf(string $token): StreamedResponse|Response
    {
        $signer = $this->resolveAccessibleSigner($token, ['document']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return $this->documentPasswordRequiredResponse();
        }

        $document = $signer->document;

        // Use the original source PDF for the live signing view so the interactive
        // overlay and the visible document share the same coordinate basis.
        return PublicPdfStream::inlineResponse($document->sourcePdfPath() ?: $document->activeSigningPdfPath());
    }

    public function sign(string $token): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
            if ($signer === null) {
                return $this->invalidLinkResponse();
            }

            if (! $this->documentAccessUnlocked($signer->document)) {
                return redirect()->route('sign.show', $token)
                    ->with('error', __('Enter the document password to continue.'));
            }

            $document = $signer->document;

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

            $this->documentSigningWorkflowService->completeLegacySigning($signer, (string) request()->ip());

            Log::channel('audit')->info('Document signed (legacy flow)', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'ip_address' => (string) request()->ip(),
            ]);

            return redirect()->route('sign.show', $this->signerRouteToken($signer))
                ->with('status', __('Thank you. Your signature has been recorded.'));
        } catch (TrustAuthorizationRequiredException $exception) {
            return redirect()->route('sign.show', $token)
                ->with('error', $exception->getMessage());
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
    ): RedirectResponse|Response|JsonResponse
    {
        try {
            $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
            if ($signer === null) {
                return $this->invalidLinkResponse();
            }

            if (! $this->documentAccessUnlocked($signer->document)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('Enter the document password to continue.'),
                    ], 423);
                }

                return redirect()->route('sign.show', $token)
                    ->with('error', __('Enter the document password to continue.'));
            }

            $document = $signer->document;

            $signingError = $this->canSignerModifyFields($document, $signer);
            if ($signingError !== null) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $signingError,
                    ], 422);
                }

                return redirect()->route('sign.show', $this->signerRouteToken($signer))
                    ->with('error', $signingError);
            }

            $field = SignatureField::query()->findOrFail($request->validated('signature_field_id'));
            $captureResult = $this->fieldSignatureCaptureService->capture(
                $signer,
                $field,
                $request->validated('submitted_value'),
                $request->validated('signature_image'),
                (string) $request->ip(),
            );
            $this->documentSigningWorkflowService->completeSignerIfAllFieldsSigned(
                $captureResult->signer,
                $captureResult->document,
                (string) $request->ip(),
            );

            if ($request->expectsJson()) {
                $document = $captureResult->document->fresh();
                $signer = $captureResult->signer->fresh();
                $signature = $captureResult->signature->fresh();

                $signedCount = Signature::query()
                    ->where('document_id', $document->id)
                    ->where('signer_id', $signer->id)
                    ->whereNotNull('signature_field_id')
                    ->count();

                $assignedCount = $document->signatureFields()
                    ->where('signer_id', $signer->id)
                    ->count();

                return response()->json([
                    'message' => $captureResult->message,
                    'field' => [
                        'id' => $field->id,
                        ...$this->signatureFieldResponsePayload($signature, $signer),
                    ],
                    'summary' => [
                        'assigned' => $assignedCount,
                        'completed' => $signedCount,
                        'remaining' => max(0, $assignedCount - $signedCount),
                        'progress_percent' => $assignedCount > 0
                            ? (int) round(($signedCount / $assignedCount) * 100)
                            : 0,
                        'can_edit_fields' => $signer->status === DocumentSignerStatus::Pending
                            && $document->status === DocumentStatus::Pending,
                        'signer_status' => $signer->status->value,
                        'document_status' => $document->status->value,
                    ],
                ]);
            }

            return redirect()->route('sign.show', $this->signerRouteToken($signer))
                ->with('status', $captureResult->message);
        } catch (TrustAuthorizationRequiredException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()->route('sign.show', $token)
                ->with('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Field signature submission failed', [
                'token' => $token,
                'signature_field_id' => $request->input('signature_field_id'),
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'ip_address' => (string) $request->ip(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Unable to save your signature right now. Please try again.'),
                ], 500);
            }

            return redirect()->route('sign.show', $token)
                ->with('error', __('Unable to save your signature right now. Please try again.'));
        }
    }

    public function streamSignatureImage(string $token, SignatureField $signatureField): StreamedResponse|Response
    {
        $signer = $this->resolveAccessibleSigner($token);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return $this->documentPasswordRequiredResponse();
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

    public function startTrustAuthorization(StartTrustAuthorizationRequest $request, string $token): JsonResponse|Response
    {
        $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return response()->json([
                'message' => __('Enter the document password to continue.'),
            ], 423);
        }

        $signingError = $this->canSignerModifyFields($signer->document, $signer);
        if ($signingError !== null) {
            return response()->json([
                'message' => $signingError,
            ], 422);
        }

        try {
            $session = $this->trustAuthorizationWorkflowService->startForSigner(
                $signer,
                (int) ($request->validated('num_signatures') ?? 1),
            );

            return response()->json([
                'message' => $session->status === 'pending'
                    ? __('Trust authorization started and is awaiting completion.')
                    : __('Trust authorization completed.'),
                'session' => $this->trustAuthorizationSessionPayload($session),
            ]);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Trust authorization start failed', [
                'token' => $token,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'message' => __('Unable to start trust authorization right now. Please try again.'),
            ], 500);
        }
    }

    public function pollTrustAuthorization(string $token, TrustAuthorizationSession $session): JsonResponse|Response
    {
        $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return response()->json([
                'message' => __('Enter the document password to continue.'),
            ], 423);
        }

        if ($session->document_signer_id !== $signer->id) {
            abort(404);
        }

        try {
            $session = $this->trustAuthorizationWorkflowService->pollSession($session);

            return response()->json([
                'message' => $session->status === 'pending'
                    ? __('Trust authorization is still pending.')
                    : __('Trust authorization completed.'),
                'session' => $this->trustAuthorizationSessionPayload($session),
            ]);
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Trust authorization poll failed', [
                'token' => $token,
                'session_id' => $session->id,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return response()->json([
                'message' => __('Unable to check trust authorization right now. Please try again.'),
            ], 500);
        }
    }

    public function unlock(Request $request, string $token): JsonResponse|RedirectResponse|Response
    {
        $signer = $this->resolveAccessibleSigner($token, ['document']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        $document = $signer->document;

        if (! $document->hasAccessPassword()) {
            return redirect()->route('sign.show', $token);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Hash::check((string) $validated['password'], (string) $document->access_password_hash)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('The document password is incorrect.'),
                ], 422);
            }

            return redirect()->route('sign.show', $token)
                ->with('error', __('The document password is incorrect.'));
        }

        session()->put($this->documentPasswordSessionKey($document), (string) $document->access_password_hash);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Document unlocked.'),
            ]);
        }

        return redirect()->route('sign.show', $token)
            ->with('status', __('Document unlocked.'));
    }

    /**
     * @param  array<int|string, mixed>  $with
     */
    private function resolveAccessibleSigner(string $token, array $with = []): ?DocumentSigner
    {
        return DocumentSigner::query()
            ->when($with !== [], fn ($query) => $query->with($with))
            ->where('access_token', $token)
            ->whereNotNull('access_token')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereHas('document', function ($query): void {
                $query->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Completed]);
            })
            ->first();
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

    private function documentAccessUnlocked(\App\Models\Document $document): bool
    {
        if (! $document->hasAccessPassword()) {
            return true;
        }

        return hash_equals(
            (string) $document->access_password_hash,
            (string) session()->get($this->documentPasswordSessionKey($document), '')
        );
    }

    private function documentPasswordSessionKey(\App\Models\Document $document): string
    {
        return self::DOCUMENT_UNLOCK_SESSION_PREFIX.$document->id;
    }

    private function documentPasswordRequiredResponse(): Response
    {
        return response()->view('sign.invalid', [
            'message' => __('Enter the document password to continue.'),
        ], 423);
    }

    private function canSignerModifyFields(\App\Models\Document $document, DocumentSigner $signer): ?string
    {
        return $this->documentSigningWorkflowService->canSignerModifyFields($document, $signer);
    }

    /**
     * @return array<string, mixed>
     */
    private function trustAuthorizationSessionPayload(TrustAuthorizationSession $session): array
    {
        return [
            'id' => $session->id,
            'provider_name' => $session->provider_name,
            'credential_id' => $session->credential_id,
            'authorization_mode' => $session->authorization_mode,
            'status' => $session->status,
            'authorization_reference' => $session->authorization_reference,
            'expires_at' => $session->expires_at?->toDateTimeString(),
            'completed_at' => $session->completed_at?->toDateTimeString(),
            'payload' => is_array($session->payload) ? $session->payload : null,
        ];
    }

    /**
     * @return array{image_url: ?string, submitted_value: ?string}
     */
    private function signatureFieldResponsePayload(Signature $signature, DocumentSigner $signer): array
    {
        $imageUrl = null;

        if (is_string($signature->signature_path) && $signature->signature_path !== '' && $signature->signature_field_id !== null) {
            $imageUrl = route('sign.signature.image', [
                'token' => $this->signerRouteToken($signer),
                'signatureField' => $signature->signature_field_id,
            ]).'?v='.$signature->updated_at?->timestamp;
        }

        return [
            'image_url' => $imageUrl,
            'submitted_value' => is_string($signature->submitted_value) ? $signature->submitted_value : null,
        ];
    }
}
