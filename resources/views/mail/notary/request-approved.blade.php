<x-mail::message>
# Notary Request Approved

Your notary request has been approved by the assigned notary public.

**Request:** {{ $notaryRequest->title }}
**Approved by:** {{ $notaryName }}
**Approved at:** {{ $approvedAt }}

The request is now ready for finalization. Once all document artifacts are complete, the notarization can be finalized.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
