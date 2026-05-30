<x-mail::message>
# Document Notarized

Your notarization has been completed and the document is now officially notarized.

**Notarization:** {{ $notaryRequest->title }}
**Completed at:** {{ $completedAt }}

Your notarized document, notarial certificate, and blockchain proof are now available for download.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Notarized Document
</x-mail::button>

The notarization record is immutable and can be independently verified using the QR code on the notarial certificate.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
