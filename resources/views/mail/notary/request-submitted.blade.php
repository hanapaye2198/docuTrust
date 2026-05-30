<x-mail::message>
# New Notarization

A new notarization has been submitted and assigned to you.

**Notarization:** {{ $notaryRequest->title }}
**Type:** {{ ucfirst($requestType) }}
**Requester:** {{ $requesterName }}
**Submitted:** {{ $notaryRequest->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)

Please review the notarization and proceed with identity verification.

<x-mail::button :url="route('notary.requests.show', $notaryRequest)">
View Notarization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
