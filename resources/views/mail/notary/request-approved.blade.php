<x-mail::message>
# Attorney Review Completed

The assigned notary public has completed the attorney review for your request.

**Request:** {{ $notaryRequest->title }}
**Reviewed by:** {{ $notaryName }}
**Reviewed at:** {{ $approvedAt }}

The request can now proceed through the remaining notarization steps, including digital notarization and final administrative finalization.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
