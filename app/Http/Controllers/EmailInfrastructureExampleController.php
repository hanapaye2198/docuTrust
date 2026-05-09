<?php

namespace App\Http\Controllers;

use App\Mail\DocumentCompletedMail;
use App\Mail\ReminderMail;
use App\Mail\SignerInvitationMail;
use App\Models\Document;
use App\Models\DocumentSigner;
use App\Services\OtpService;
use App\Services\SigningMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailInfrastructureExampleController extends Controller
{
    public function __construct(
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function sendOtp(Request $request, OtpService $otpService): JsonResponse
    {
        $result = $otpService->generateForEmail(
            user: $request->user(),
            purpose: 'onboarding',
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sendSignerInvitation(DocumentSigner $signer): JsonResponse
    {
        $document = $signer->document()->with('user')->first();
        if (! $document instanceof Document) {
            return response()->json(['message' => __('Document not found.')], 404);
        }

        Mail::to($signer->email)->queue(new SignerInvitationMail(
            documentTitle: $document->title,
            senderName: $document->user?->name ?? config('app.name'),
            signUrl: $this->signingMethodService->signerEntryUrl($signer),
            expiresAt: $signer->expires_at?->toDateTimeString(),
            requiresDocumentPassword: $document->hasAccessPassword(),
            documentPasswordHint: $document->access_password_hint,
        ));

        return response()->json(['message' => __('Signer invitation queued.')]);
    }

    public function sendReminder(DocumentSigner $signer): JsonResponse
    {
        $document = $signer->document()->first();
        if (! $document instanceof Document) {
            return response()->json(['message' => __('Document not found.')], 404);
        }

        Mail::to($signer->email)->queue(new ReminderMail(
            recipientName: $signer->name,
            documentTitle: $document->title,
            signUrl: $this->signingMethodService->signerEntryUrl($signer),
            requiresDocumentPassword: $document->hasAccessPassword(),
            documentPasswordHint: $document->access_password_hint,
        ));

        return response()->json(['message' => __('Reminder email queued.')]);
    }

    public function sendCompleted(Document $document): JsonResponse
    {
        $document->loadMissing('user');

        Mail::to($document->user?->email)->queue(new DocumentCompletedMail(
            document: $document,
        ));

        return response()->json(['message' => __('Document completion email queued.')]);
    }
}
