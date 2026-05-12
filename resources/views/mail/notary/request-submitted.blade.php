<x-mail::message>
# New Notary Request

A new notary request has been submitted and assigned to you.

**Request:** {{ $notaryRequest->title }}
**Type:** {{ ucfirst($requestType) }}
**Requester:** {{ $requesterName }}
**Submitted:** {{ $notaryRequest->submitted_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)

Please review the request and proceed with identity verification.

<x-mail::button :url="route('notary.requests.show', $notaryRequest)">
View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
