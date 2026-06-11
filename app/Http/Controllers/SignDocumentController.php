<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesSecureDisk;
use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Http\Requests\StartTrustAuthorizationRequest;
use App\Http\Requests\StoreDocumentSignatureRequest;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Models\TrustAuthorizationSession;
use App\Services\CompletedDocumentArtifactService;
use App\Services\DocumentPdfStampingService;
use App\Services\DocumentSigningWorkflowService;
use App\Services\FieldSignatureCaptureService;
use App\Services\SigningMethodService;
use App\Support\PublicPdfStream;
use App\Trust\Authorization\TrustAuthorizationRequiredException;
use App\Trust\Authorization\TrustAuthorizationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SignDocumentController extends Controller
{
    use ResolvesSecureDisk;

    private const DOCUMENT_UNLOCK_SESSION_PREFIX = 'document_access_unlocked:';

    public function __construct(
        private readonly CompletedDocumentArtifactService $completedDocumentArtifactService,
        private readonly DocumentPdfStampingService $documentPdfStampingService,
        private readonly DocumentSigningWorkflowService $documentSigningWorkflowService,
        private readonly FieldSignatureCaptureService $fieldSignatureCaptureService,
        private readonly TrustAuthorizationWorkflowService $trustAuthorizationWorkflowService,
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function show(string $token): View|Response|RedirectResponse
    {
        $signer = $this->resolveAccessibleSigner($token, $this->signerDetailRelations());
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        $accessResponse = $this->authorizeSigningMethodAccess($signer);
        if ($accessResponse !== null) {
            return $accessResponse;
        }

        return $this->renderSignView($signer, false);
    }

    public function showAuthenticated(int $signerId): View|Response|RedirectResponse
    {
        $signer = $this->resolveAuthenticatedSigner($signerId, $this->signerDetailRelations());

        return $this->renderSignView($signer, true);
    }

    public function streamPdf(string $token): StreamedResponse|Response|RedirectResponse
    {
        $signer = $this->resolveAccessibleSigner($token, ['document']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        $accessResponse = $this->authorizeSigningMethodAccess($signer);
        if ($accessResponse !== null) {
            return $accessResponse;
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return $this->documentPasswordRequiredResponse();
        }

        $document = $signer->document;

        // Use the original source PDF for the live signing view so the interactive
        // overlay and the visible document share the same coordinate basis.
        return PublicPdfStream::inlineResponse($document->sourcePdfPath() ?: $document->activeSigningPdfPath());
    }

    public function streamAuthenticatedPdf(int $signerId): StreamedResponse|Response|RedirectResponse
    {
        $signer = $this->resolveAuthenticatedSigner($signerId, ['document']);

        if (! $this->documentAccessUnlocked($signer->document)) {
            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                ->with('error', __('Enter the document password to continue.'));
        }

        $document = $signer->document;
        $path = $this->authenticatedSigningPdfPath($document);

        return PublicPdfStream::inlineResponse($path);
    }

    public function sign(string $token): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
            if ($signer === null) {
                return $this->invalidLinkResponse();
            }

            $accessResponse = $this->authorizeSigningMethodAccess($signer);
            if ($accessResponse !== null) {
                return $accessResponse;
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
                ->with('status', $signer->isApprover()
                    ? __('Thank you. Your approval has been recorded.')
                    : __('Thank you. Your signature has been recorded.'));
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

    public function signAuthenticated(int $signerId): RedirectResponse|Response
    {
        try {
            $signer = $this->resolveAuthenticatedSigner($signerId, ['document.documentSigners']);

            if (! $this->documentAccessUnlocked($signer->document)) {
                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('error', __('Enter the document password to continue.'));
            }

            $document = $signer->document;
            $signingError = $this->canSignerModifyFields($document, $signer);
            if ($signingError !== null) {
                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('error', $signingError);
            }

            if ($document->signatureFields()->exists()) {
                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('error', __('This document must be signed using the signature fields on the document.'));
            }

            $hasFieldsForSigner = $document->signatureFields()
                ->where('signer_id', $signer->id)
                ->exists();

            if ($hasFieldsForSigner) {
                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('error', __('Please complete each signature field on the document.'));
            }

            $this->documentSigningWorkflowService->completeLegacySigning($signer, (string) request()->ip());

            Log::channel('audit')->info('Document signed (authenticated flow)', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'user_id' => Auth::id(),
                'ip_address' => (string) request()->ip(),
            ]);

            $redirectUrl = $this->completionRedirectUrl($signer->fresh(['document']));

            return $redirectUrl !== null
                ? redirect()->to($redirectUrl)->with('status', $signer->isApprover()
                    ? __('Thank you. Your approval has been recorded.')
                    : __('Thank you. Your signature has been recorded.'))
                : redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('status', $signer->isApprover()
                        ? __('Thank you. Your approval has been recorded.')
                        : __('Thank you. Your signature has been recorded.'));
        } catch (TrustAuthorizationRequiredException $exception) {
            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signerId])
                ->with('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Authenticated document signing failed', [
                'signer_id' => $signerId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
                'ip_address' => (string) request()->ip(),
            ]);

            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signerId])
                ->with('error', __('Unable to complete signing right now. Please try again.'));
        }
    }

    public function storeSignature(
        StoreDocumentSignatureRequest $request,
        string $token,
    ): RedirectResponse|Response|JsonResponse {
        try {
            $signer = $this->resolveAccessibleSigner($token, ['document.documentSigners']);
            if ($signer === null) {
                return $this->invalidLinkResponse();
            }

            $accessResponse = $this->authorizeSigningMethodAccess($signer, $request->expectsJson());
            if ($accessResponse !== null) {
                return $accessResponse;
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

            if ($signer->status->isCompleted() && $request->expectsJson()) {
                return response()->json($this->completedFieldSigningPayload($signer, false));
            }

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
                    'redirect_url' => $signer->status->isCompleted()
                        ? route('sign.show', $this->signerRouteToken($signer))
                        : null,
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

    public function storeAuthenticatedSignature(
        StoreDocumentSignatureRequest $request,
        int $signerId,
    ): RedirectResponse|Response|JsonResponse {
        try {
            $signer = $this->resolveAuthenticatedSigner($signerId, ['document.documentSigners']);

            if (! $this->documentAccessUnlocked($signer->document)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => __('Enter the document password to continue.'),
                    ], 423);
                }

                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('error', __('Enter the document password to continue.'));
            }

            $document = $signer->document;

            if ($signer->status->isCompleted() && $request->expectsJson()) {
                return response()->json($this->completedFieldSigningPayload($signer, true));
            }

            $signingError = $this->canSignerModifyFields($document, $signer);
            if ($signingError !== null) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $signingError,
                    ], 422);
                }

                return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
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
                        ...$this->signatureFieldResponsePayload($signature, $signer, true),
                    ],
                    'redirect_url' => $this->completionRedirectUrl($signer),
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

            $redirectUrl = $this->completionRedirectUrl($signer);

            return $redirectUrl !== null
                ? redirect()->to($redirectUrl)->with('status', $captureResult->message)
                : redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                    ->with('status', $captureResult->message);
        } catch (TrustAuthorizationRequiredException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                ], 422);
            }

            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signerId])
                ->with('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            Log::channel('errors')->error('Authenticated field signature submission failed', [
                'signer_id' => $signerId,
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

            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signerId])
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

    public function downloadSignedDocument(string $token): StreamedResponse|Response|RedirectResponse
    {
        $signer = $this->resolveAccessibleSigner($token, ['document']);
        if ($signer === null) {
            return $this->invalidLinkResponse();
        }

        $accessResponse = $this->authorizeSigningMethodAccess($signer);
        if ($accessResponse !== null) {
            return $accessResponse;
        }

        if (! $this->documentAccessUnlocked($signer->document)) {
            return redirect()->route('sign.show', $token)
                ->with('error', __('Enter the document password to download this document.'));
        }

        $document = $signer->document;
        if ($document->status !== DocumentStatus::Completed) {
            abort(404);
        }

        $document = $this->completedDocumentArtifactService->ensureReady($document);
        $diskName = $document->hasArchivedFinalDocument()
            ? $document->archiveDisk()
            : (string) config('filesystems.docutrust_disk', 'local');
        $path = $document->finalDownloadPath();

        abort_if(! is_string($path) || $path === '', 404);

        return Storage::disk($diskName)->download(
            $path,
            $document->title.'-signed.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function streamAuthenticatedSignatureImage(int $signerId, SignatureField $signatureField): StreamedResponse|Response|RedirectResponse
    {
        $signer = $this->resolveAuthenticatedSigner($signerId);

        if (! $this->documentAccessUnlocked($signer->document)) {
            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                ->with('error', __('Enter the document password to continue.'));
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

        $accessResponse = $this->authorizeSigningMethodAccess($signer, true);
        if ($accessResponse !== null) {
            return $accessResponse;
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

    public function startAuthenticatedTrustAuthorization(StartTrustAuthorizationRequest $request, int $signerId): JsonResponse|Response
    {
        $signer = $this->resolveAuthenticatedSigner($signerId, ['document.documentSigners']);

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
            Log::channel('errors')->error('Authenticated trust authorization start failed', [
                'signer_id' => $signerId,
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

        $accessResponse = $this->authorizeSigningMethodAccess($signer, true);
        if ($accessResponse !== null) {
            return $accessResponse;
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

    public function pollAuthenticatedTrustAuthorization(int $signerId, TrustAuthorizationSession $session): JsonResponse|Response
    {
        $signer = $this->resolveAuthenticatedSigner($signerId, ['document.documentSigners']);

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
            Log::channel('errors')->error('Authenticated trust authorization poll failed', [
                'signer_id' => $signerId,
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

        $accessResponse = $this->authorizeSigningMethodAccess($signer, $request->expectsJson());
        if ($accessResponse !== null) {
            return $accessResponse;
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

    public function unlockAuthenticated(Request $request, int $signerId): JsonResponse|RedirectResponse|Response
    {
        $signer = $this->resolveAuthenticatedSigner($signerId, ['document']);
        $document = $signer->document;

        if (! $document->hasAccessPassword()) {
            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id]);
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

            return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
                ->with('error', __('The document password is incorrect.'));
        }

        session()->put($this->documentPasswordSessionKey($document), (string) $document->access_password_hash);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('Document unlocked.'),
            ]);
        }

        return redirect()->route($this->authenticatedAccountRouteName('show'), ['signerId' => $signer->id])
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

    /**
     * @return array<int|string, mixed>
     */
    private function signerDetailRelations(): array
    {
        return [
            'document' => fn ($q) => $q->withCount('signatureFields'),
            'document.signatureFields',
            'signatures' => fn ($q) => $q->whereNotNull('signature_field_id'),
        ];
    }

    private function resolveAuthenticatedSigner(int $signerId, array $with = []): DocumentSigner
    {
        $userId = Auth::id();

        abort_if($userId === null, 401);

        return DocumentSigner::query()
            ->when($with !== [], fn ($query) => $query->with($with))
            ->whereKey($signerId)
            ->where('user_id', $userId)
            ->whereHas('document', function ($query): void {
                $query->whereIn('status', [DocumentStatus::Pending, DocumentStatus::Completed]);
            })
            ->firstOrFail();
    }

    private function invalidLinkResponse(): Response
    {
        return response()->view('sign.invalid', [
            'message' => __('Link expired or invalid'),
        ], 403);
    }

    private function documentAccessUnlocked(Document $document): bool
    {
        if (! $document->hasAccessPassword()) {
            return true;
        }

        return hash_equals(
            (string) $document->access_password_hash,
            (string) session()->get($this->documentPasswordSessionKey($document), '')
        );
    }

    private function documentPasswordSessionKey(Document $document): string
    {
        return self::DOCUMENT_UNLOCK_SESSION_PREFIX.$document->id;
    }

    private function documentPasswordRequiredResponse(): Response
    {
        return response()->view('sign.invalid', [
            'message' => __('Enter the document password to continue.'),
        ], 423);
    }

    private function canSignerModifyFields(Document $document, DocumentSigner $signer): ?string
    {
        return $this->documentSigningWorkflowService->canSignerModifyFields($document, $signer);
    }

    private function authorizeSigningMethodAccess(DocumentSigner $signer, bool $expectsJson = false): Response|RedirectResponse|JsonResponse|null
    {
        if (! $this->signingMethodService->requiresAuthenticatedAccount($signer)) {
            return null;
        }

        if ($signer->user_id === null) {
            return $expectsJson
                ? response()->json([
                    'message' => __('This signer is not linked to a verified DocuTrust account.'),
                ], 422)
                : response()->view('sign.invalid', [
                    'message' => __('This signer is not linked to a verified DocuTrust account.'),
                ], 422);
        }

        if (auth()->guest()) {
            return $expectsJson
                ? response()->json([
                    'message' => __('Sign in with the assigned DocuTrust account to access this document.'),
                ], 401)
                : redirect()->guest(route('login'));
        }

        if ($this->signingMethodService->canAuthenticatedUserAccessSigner($signer, auth()->user())) {
            return null;
        }

        return $expectsJson
            ? response()->json([
                'message' => __('Sign in with the assigned DocuTrust account to access this document.'),
            ], 403)
            : response()->view('sign.invalid', [
                'message' => __('Sign in with the assigned DocuTrust account to access this document.'),
            ], 403);
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
    private function signatureFieldResponsePayload(Signature $signature, DocumentSigner $signer, bool $authenticated = false): array
    {
        $imageUrl = null;

        if (is_string($signature->signature_path) && $signature->signature_path !== '' && $signature->signature_field_id !== null) {
            $imageUrl = $authenticated
                ? route($this->authenticatedAccountRouteName('signature.image'), [
                    'signerId' => $signer->id,
                    'signatureField' => $signature->signature_field_id,
                ]).'?v='.$signature->updated_at?->timestamp
                : route('sign.signature.image', [
                    'token' => $this->signerRouteToken($signer),
                    'signatureField' => $signature->signature_field_id,
                ]).'?v='.$signature->updated_at?->timestamp;
        }

        return [
            'image_url' => $imageUrl,
            'submitted_value' => is_string($signature->submitted_value) ? $signature->submitted_value : null,
        ];
    }

    private function renderSignView(DocumentSigner $signer, bool $authenticated): View
    {
        $document = $signer->document;
        $documentAccessProtected = $document->hasAccessPassword();
        $documentAccessLocked = $documentAccessProtected && ! $this->documentAccessUnlocked($document);
        $signingAvailabilityMessage = ! $documentAccessLocked
            ? $this->documentSigningWorkflowService->canSignerModifyFields($document->loadMissing('documentSigners'), $signer)
            : null;
        $trustAuthorizationEnabled = $this->signingMethodService->requiresTrustAuthorization($signer);
        $trustAuthorizationSession = $trustAuthorizationEnabled
            ? $signer->trustAuthorizationSessions()->latest('id')->first()
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

            $signedByFieldId[$signature->signature_field_id] = $this->signatureFieldResponsePayload($signature, $signer, $authenticated);
        }

        return view('sign.show', [
            'signer' => $signer,
            'pdfUrl' => $authenticated
                ? route(
                    Auth::user()?->role->value === 'notary' ? 'notary.sign.account.document.pdf' : 'sign.account.document.pdf',
                    ['signerId' => $signer->id]
                )
                : route('sign.document.pdf', $this->signerRouteToken($signer)),
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
            'signerSessionRealtime' => ! $authenticated
                ? [
                    'channel' => 'signer-session.'.$this->signerRouteToken($signer),
                    'event' => 'signer.session.updated',
                ]
                : null,
            'trustAuthorizationEnabled' => $trustAuthorizationEnabled,
            'trustAuthorizationSession' => $trustAuthorizationSession !== null
                ? $this->trustAuthorizationSessionPayload($trustAuthorizationSession)
                : null,
            'trustAuthorizationSessionActive' => $trustAuthorizationSessionActive,
            'authenticatedSigning' => $authenticated,
        ]);
    }

    private function authenticatedAccountRouteName(string $suffix): string
    {
        return Auth::user()?->role->value === 'notary'
            ? 'notary.sign.account.'.$suffix
            : 'sign.account.'.$suffix;
    }

    private function completionRedirectUrl(DocumentSigner $signer): ?string
    {
        $document = $signer->document;

        if (
            Auth::user()?->role->value === 'notary'
            && $document->notary_request_id !== null
            && (int) $signer->user_id === (int) Auth::id()
            && $signer->status === DocumentSignerStatus::Signed
        ) {
            return route('notary.requests.show', [
                'notaryRequest' => $document->notary_request_id,
                'tab' => 'closing',
            ]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function completedFieldSigningPayload(DocumentSigner $signer, bool $authenticated): array
    {
        $signer->loadMissing(['document.signatureFields', 'signatures' => fn ($query) => $query->whereNotNull('signature_field_id')]);
        $document = $signer->document;
        $assignedCount = $document->signatureFields
            ->where('signer_id', $signer->id)
            ->count();
        $signedCount = $signer->signatures->count();

        return [
            'message' => $signer->isApprover()
                ? __('You have already approved this document.')
                : __('You have already signed this document.'),
            'redirect_url' => $authenticated
                ? $this->completionRedirectUrl($signer)
                : route('sign.show', $this->signerRouteToken($signer)),
            'summary' => [
                'assigned' => $assignedCount,
                'completed' => $signedCount,
                'remaining' => max(0, $assignedCount - $signedCount),
                'progress_percent' => $assignedCount > 0
                    ? (int) round(($signedCount / $assignedCount) * 100)
                    : 0,
                'can_edit_fields' => false,
                'signer_status' => $signer->status->value,
                'document_status' => $document->status->value,
            ],
        ];
    }

    private function authenticatedSigningPdfPath(Document $document): ?string
    {
        if ($document->status === DocumentStatus::Completed) {
            return $this->completedDocumentArtifactService
                ->ensureReady($document)
                ->previewPdfPath();
        }

        $hasCompletedFieldSignatures = $document->signatures()
            ->whereNotNull('signature_field_id')
            ->exists();

        if ($hasCompletedFieldSignatures) {
            $signedPreviewPath = $this->documentPdfStampingService->generateSignedPreviewPdf($document);

            if (is_string($signedPreviewPath) && $signedPreviewPath !== '') {
                return $signedPreviewPath;
            }
        }

        return $document->activeSigningPdfPath() ?: $document->sourcePdfPath();
    }
}
