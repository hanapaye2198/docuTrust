<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestDigitalized;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Events\NotarySessionScheduled;
use App\Mail\NotaryRequestApprovedMail;
use App\Mail\NotaryRequestDigitalizedMail;
use App\Mail\NotaryRequestNotarizedMail;
use App\Mail\NotaryRequestSubmittedMail;
use App\Mail\NotarySessionScheduledMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotaryNotificationService
{
    public function handleRequestSubmitted(NotaryRequestSubmitted $event): void
    {
        $request = $event->notaryRequest->loadMissing(['notary', 'requester']);

        // Notify the assigned notary
        if ($request->notary !== null && $request->notary->email !== '') {
            Mail::to($request->notary->email)
                ->queue(new NotaryRequestSubmittedMail($request));
        }
    }

    public function handleSessionScheduled(NotarySessionScheduled $event): void
    {
        $request = $event->notaryRequest->loadMissing(['requester']);

        // Notify the requester about the scheduled session
        if ($request->requester !== null && $request->requester->email !== '') {
            Mail::to($request->requester->email)
                ->queue(new NotarySessionScheduledMail($request, $event->notarySession));
        }
    }

    public function handleRequestApproved(NotaryRequestApproved $event): void
    {
        $request = $event->notaryRequest->loadMissing(['requester', 'notary']);

        // Notify the requester that attorney review is complete
        if ($request->requester !== null && $request->requester->email !== '') {
            Mail::to($request->requester->email)
                ->queue(new NotaryRequestApprovedMail($request));
        }
    }

    public function handleRequestDigitalized(NotaryRequestDigitalized $event): void
    {
        $request = $event->notaryRequest->loadMissing(['requester', 'notary']);

        if ($request->requester !== null && $request->requester->email !== '') {
            Mail::to($request->requester->email)
                ->queue(new NotaryRequestDigitalizedMail($request));
        }

        User::query()
            ->where('role', UserRole::NotaryAdmin)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->each(function (string $email) use ($request): void {
                Mail::to($email)->queue(new NotaryRequestDigitalizedMail($request));
            });
    }

    public function handleRequestNotarized(NotaryRequestNotarized $event): void
    {
        $request = $event->notaryRequest->loadMissing(['requester', 'notary']);

        // Notify the requester about completion
        if ($request->requester !== null && $request->requester->email !== '') {
            Mail::to($request->requester->email)
                ->queue(new NotaryRequestNotarizedMail($request));
        }
    }
}
