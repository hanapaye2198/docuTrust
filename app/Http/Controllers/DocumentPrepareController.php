<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Http\Requests\StoreSignatureFieldsRequest;
use App\Models\Document;
use App\Models\SignatureField;
use App\Services\NotaryParticipantSyncService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\SendDocumentForSignatureService;
use App\Services\SignatureAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DocumentPrepareController extends Controller
{
    public function show(Document $document): View|RedirectResponse
    {
        $this->authorize('update', $document);

        if ($document->notary_request_id !== null) {
            if (Auth::id() !== $document->notaryRequest->notary_user_id) {
                abort(403);
            }

            $user = Auth::user();

            // If the document is fully completed and this is the assigned attorney,
            // auto-transition to attorney-signing preparation so the CTA link is always actionable.
            if ($document->status === DocumentStatus::Completed && $user?->role->value === 'notary') {
                try {
                    app(NotaryRequestWorkflowService::class)->beginAttorneySigning($document->notaryRequest);
                } catch (RuntimeException $exception) {
                    return redirect()
                        ->route('notary.requests.show', $document->notaryRequest)
                        ->with('error', $exception->getMessage());
                }

                $attorneySigner = $document->documentSigners()
                    ->where('user_id', $user->id)
                    ->first();

                if ($attorneySigner === null) {
                    $attorneySigner = $document->documentSigners()->create([
                        'name' => $user->name,
                        'email' => $user->email,
                        'user_id' => $user->id,
                        'role_type' => TemplateRoleType::Signer,
                        'signing_method' => SigningMethod::AccountVerified,
                        'status' => 'pending',
                        'signing_order' => 999,
                    ]);
                }

                $document->update(['status' => DocumentStatus::Pending]);
                $document->refresh();
            }

            // Auto-sync NotarySigner records into DocumentSigner for field placement
            app(NotaryParticipantSyncService::class)->syncRequestSignersToDocument($document);
        }

        // For eNOTARY documents, allow pending status (attorney signing phase)
        if ($document->notary_request_id !== null && Auth::id() === $document->notaryRequest->notary_user_id) {
            if (! in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Pending], true)) {
                abort(403);
            }
        } elseif ($document->status !== DocumentStatus::Draft) {
            abort(403);
        }

        $document->loadMissing(['documentSigners' => fn ($q) => $q->orderBy('signing_order')]);

        if (! $document->documentSigners->contains(fn ($signer) => $signer->isSigner())) {
            return redirect()
                ->route('documents.show', $document)
                ->with('error', __('Add at least one signer before preparing fields.'));
        }

        $signers = $document->documentSigners
            ->filter(fn ($signer) => $signer->isSigner())
            ->map(function ($signer): array {
                return [
                    'id' => (int) $signer->id,
                    'name' => (string) $signer->name,
                    'email' => (string) $signer->email,
                    'allowed_pages' => $signer->allowed_pages,
                ];
            })
            ->values();

        $firstSignerId = $signers->first()['id'] ?? null;

        $user = Auth::user();
        $isAttorneySigningPhase = $document->notary_request_id !== null
            && $user?->role->value === 'notary'
            && $document->status === DocumentStatus::Pending
            && $document->documentSigners()->where('user_id', $user->id)->exists();
        $attorneySigner = $isAttorneySigningPhase
            ? $document->documentSigners->first(fn ($signer) => (int) $signer->user_id === (int) $user?->id)
            : null;

        // In attorney signing phase, only show the attorney as available signer
        if ($isAttorneySigningPhase) {
            $signers = $document->documentSigners
                ->filter(fn ($signer) => (int) $signer->user_id === (int) $user->id)
                ->map(function ($signer): array {
                    return [
                        'id' => (int) $signer->id,
                        'name' => (string) $signer->name,
                        'email' => (string) $signer->email,
                        'allowed_pages' => $signer->allowed_pages,
                    ];
                })
                ->values();
            $firstSignerId = $signers->first()['id'] ?? null;
        }

        $initialFieldsQuery = $document->signatureFields()->orderBy('id');

        if ($isAttorneySigningPhase && $attorneySigner !== null) {
            $initialFieldsQuery->where('signer_id', $attorneySigner->id);
        }

        return view('documents.prepare', [
            'document' => $document,
            'pdfUrl' => $this->resolveStreamUrl($document),
            'firstSignerId' => $firstSignerId,
            'signers' => $signers,
            'canSend' => ! $isAttorneySigningPhase && $document->canSendForSigning(),
            'isAttorneySigningPhase' => $isAttorneySigningPhase,
            'initialFields' => $initialFieldsQuery
                ->get()
                ->map(fn (SignatureField $f) => [
                    'id' => $f->id,
                    'signer_id' => $f->signer_id,
                    'type' => $f->type->value,
                    'page_number' => $f->page_number ?? 1,
                    'position_data' => $f->position_data,
                ])
                ->values(),
        ]);
    }

    public function store(StoreSignatureFieldsRequest $request, Document $document): RedirectResponse
    {
        /** @var array<int, array{signer_id: int, type: string, page_number: int, position_data: array{x: float, y: float, width: float, height: float}}> $fields */
        $fields = $request->validated()['fields'];

        foreach ($fields as $field) {
            $signer = $document->documentSigners()->whereKey($field['signer_id'])->first();
            if ($signer === null) {
                abort(422, __('Invalid signer for this document.'));
            }
        }

        $ip = (string) $request->ip();
        $user = Auth::user();
        $isAttorneySigningPhase = $document->notary_request_id !== null
            && $user?->role->value === 'notary'
            && $document->status === DocumentStatus::Pending
            && $document->documentSigners()->where('user_id', $user->id)->exists();
        $attorneySigner = $isAttorneySigningPhase
            ? $document->documentSigners()->where('user_id', $user?->id)->first()
            : null;

        if ($isAttorneySigningPhase) {
            if ($attorneySigner === null) {
                abort(422, __('Unable to resolve the attorney signer for this document.'));
            }

            foreach ($fields as $field) {
                if ((int) $field['signer_id'] !== (int) $attorneySigner->id) {
                    abort(422, __('Attorney signing phase only allows fields assigned to the attorney.'));
                }
            }
        }

        DB::transaction(function () use ($document, $fields, $ip, $isAttorneySigningPhase, $attorneySigner): void {
            if ($isAttorneySigningPhase) {
                // Attorney signing phase: only delete attorney's fields, keep client fields intact
                if ($attorneySigner) {
                    $document->signatureFields()->where('signer_id', $attorneySigner->id)->delete();
                }
            } else {
                // Normal flow: reset everything
                $document->update([
                    'prepared_pdf_path' => null,
                    'final_pdf_path' => null,
                ]);
                $document->signatureFields()->delete();
            }

            foreach ($fields as $field) {
                SignatureField::query()->create([
                    'document_id' => $document->id,
                    'signer_id' => $field['signer_id'],
                    'type' => $field['type'],
                    'page_number' => $field['page_number'],
                    'position_data' => $field['position_data'],
                ]);
                SignatureAuditLogger::fieldPlaced($document, $field['signer_id'], $ip);
            }
        });

        // For eNOTARY attorney signing phase: redirect to signing page
        if ($isAttorneySigningPhase) {
            $attorneySigner = $document->documentSigners()
                ->where('user_id', $user->id)
                ->first();

            if ($attorneySigner !== null) {
                return redirect()
                    ->route('notary.sign.account.show', $attorneySigner->id)
                    ->with('status', __('Signature fields saved. Please sign the document.'));
            }
        }

        $redirectRoute = $user?->role->value === 'notary'
            ? 'notary.documents.prepare'
            : 'documents.prepare';

        $returnToPage = (int) $request->input('return_to_page', 1);

        return redirect()
            ->route($redirectRoute, ['document' => $document, 'page' => $returnToPage > 0 ? $returnToPage : 1])
            ->with('status', __('Signature fields saved.'));
    }

    public function send(Document $document, SendDocumentForSignatureService $sender): RedirectResponse
    {
        $this->authorize('update', $document);

        $isNotary = Auth::user()?->role->value === 'notary';
        $prepareRoute = $isNotary ? 'notary.documents.prepare' : 'documents.prepare';

        try {
            $sender->send($document);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route($prepareRoute, $document)
                ->with('error', $exception->getMessage());
        }

        // For eNOTARY documents, redirect back to the notary request show page
        if ($document->notary_request_id !== null && $isNotary) {
            return redirect()
                ->route('notary.requests.show', $document->notary_request_id)
                ->with('status', __('Document sent to signers for signing.'));
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', __('Document sent for signature.'));
    }

    private function resolveStreamUrl(Document $document): string
    {
        $user = Auth::user();

        // For eNOTARY attorney signing phase (document is pending after clients signed),
        // use a signed-only preview so completed signer marks appear in the PDF background
        // without re-drawing their interactive fields on the prepare canvas.
        $useSignedPreview = $document->notary_request_id !== null
            && $document->status === DocumentStatus::Pending
            && $document->signatures()->exists();

        // Use the notary-specific stream route for notary users
        if ($user !== null && $user->role->value === 'notary') {
            return route('notary.documents.stream', [
                'document' => $document,
                'source' => $useSignedPreview ? 0 : 1,
                'signed_preview' => $useSignedPreview ? 1 : 0,
            ], false);
        }

        return route('documents.stream', [
            'document' => $document,
            'source' => $useSignedPreview ? 0 : 1,
            'signed_preview' => $useSignedPreview ? 1 : 0,
        ], false);
    }
}
