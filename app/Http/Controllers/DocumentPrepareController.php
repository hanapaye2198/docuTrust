<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Enums\TemplateRoleType;
use App\Http\Requests\StoreSignatureFieldsRequest;
use App\Models\Document;
use App\Models\SignatureField;
use App\Services\SendDocumentForSignatureService;
use App\Services\SignatureAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class DocumentPrepareController extends Controller
{
    public function show(Document $document): View|RedirectResponse
    {
        $this->authorize('update', $document);

        if ($document->notary_request_id !== null) {
            if (auth()->id() !== $document->notaryRequest->notary_user_id) {
                abort(403);
            }

            // Auto-sync NotarySigner records into DocumentSigner for field placement
            $this->syncNotarySignersToDocument($document);
        }

        // For eNOTARY documents, allow pending status (attorney signing phase)
        if ($document->notary_request_id !== null && auth()->id() === $document->notaryRequest->notary_user_id) {
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
                ];
            })
            ->values();

        $firstSignerId = $signers->first()['id'] ?? null;

        $user = auth()->user();
        $isAttorneySigningPhase = $document->notary_request_id !== null
            && $user?->role->value === 'notary'
            && $document->status === DocumentStatus::Pending
            && $document->documentSigners()->where('user_id', $user->id)->exists();

        // In attorney signing phase, only show the attorney as available signer
        if ($isAttorneySigningPhase) {
            $signers = $document->documentSigners
                ->filter(fn ($signer) => (int) $signer->user_id === (int) $user->id)
                ->map(function ($signer): array {
                    return [
                        'id' => (int) $signer->id,
                        'name' => (string) $signer->name,
                        'email' => (string) $signer->email,
                    ];
                })
                ->values();
            $firstSignerId = $signers->first()['id'] ?? null;
        }

        return view('documents.prepare', [
            'document' => $document,
            'pdfUrl' => $this->resolveStreamUrl($document),
            'firstSignerId' => $firstSignerId,
            'signers' => $signers,
            'canSend' => ! $isAttorneySigningPhase && $document->canSendForSigning(),
            'isAttorneySigningPhase' => $isAttorneySigningPhase,
            'initialFields' => $document->signatureFields()
                ->orderBy('id')
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
        $user = auth()->user();
        $isAttorneySigningPhase = $document->notary_request_id !== null
            && $user?->role->value === 'notary'
            && $document->status === DocumentStatus::Pending
            && $document->documentSigners()->where('user_id', $user->id)->exists();

        DB::transaction(function () use ($document, $fields, $ip, $isAttorneySigningPhase, $user): void {
            if ($isAttorneySigningPhase) {
                // Attorney signing phase: only delete attorney's fields, keep client fields intact
                $attorneySigner = $document->documentSigners()->where('user_id', $user->id)->first();
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

        return redirect()
            ->route($redirectRoute, $document)
            ->with('status', __('Signature fields saved.'));
    }

    public function send(Document $document, SendDocumentForSignatureService $sender): RedirectResponse
    {
        $this->authorize('update', $document);

        $isNotary = auth()->user()?->role->value === 'notary';
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

    /**
     * Sync NotarySigner records from the notary request into DocumentSigner records
     * so the attorney can assign signature fields to each party using the standard
     * field preparation UI.
     */
    private function syncNotarySignersToDocument(Document $document): void
    {
        $notaryRequest = $document->notaryRequest;
        if ($notaryRequest === null) {
            return;
        }

        $notarySigners = $notaryRequest->signers;
        if ($notarySigners->isEmpty()) {
            return;
        }

        // Only sync if the document doesn't already have signers
        if ($document->documentSigners()->exists()) {
            return;
        }

        $order = 1;
        foreach ($notarySigners as $notarySigner) {
            $document->documentSigners()->create([
                'name' => $notarySigner->full_name,
                'email' => $notarySigner->email,
                'role_type' => TemplateRoleType::Signer,
                'signing_method' => SigningMethod::EmailLink,
                'status' => 'pending',
                'signing_order' => $order++,
            ]);
        }
    }

    private function resolveStreamUrl(Document $document): string
    {
        $user = auth()->user();

        // For eNOTARY attorney signing phase (document is pending after clients signed),
        // use the prepared/final PDF so client signatures are visible
        $useSignedPdf = $document->notary_request_id !== null
            && $document->status === DocumentStatus::Pending
            && ($document->prepared_pdf_path !== null || $document->final_pdf_path !== null);

        $source = $useSignedPdf ? 0 : 1;

        // Use the notary-specific stream route for notary users
        if ($user !== null && $user->role->value === 'notary') {
            return route('notary.documents.stream', ['document' => $document, 'source' => $source], false);
        }

        return route('documents.stream', ['document' => $document, 'source' => $source], false);
    }
}
