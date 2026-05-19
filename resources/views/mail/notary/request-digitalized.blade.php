<x-mail::message>
# Notary Request Ready For Finalization

Digital notarization has been completed for your request. The signed document, notary seal, QR verification, and notarization artifacts are now prepared for final administrative finalization.

**Request:** {{ $notaryRequest->title }}
**Processed by:** {{ $notaryName }}
**Attorney review completed:** {{ $approvedAt }}

The request will be finalized by the Notary Admin as the last step before the notarization is fully closed.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
