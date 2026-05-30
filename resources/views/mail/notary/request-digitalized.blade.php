<x-mail::message>
# Notarization Ready For Finalization

Digital notarization has been completed. The signed document, notary seal, QR verification, and notarization artifacts are now prepared for final administrative finalization.

**Notarization:** {{ $notaryRequest->title }}
**Processed by:** {{ $notaryName }}
**Attorney review completed:** {{ $approvedAt }}

The notarization will be finalized by the Notary Admin as the last step before it is fully closed.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Notarization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
