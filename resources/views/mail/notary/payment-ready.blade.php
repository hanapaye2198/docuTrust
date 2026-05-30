<x-mail::message>
# Payment Required

Your notarization is ready for payment.

**Notarization:** {{ $notaryRequest->title }}
**Assigned notary:** {{ $notaryName }}
**Amount due:** PHP {{ $amount }}
**Gateway:** {{ strtoupper((string) $payment->gateway) }}
@if ($expiresAt)
**Payment expires:** {{ $expiresAt }}
@endif

Complete the payment from your case page to continue the notarization workflow.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Notarization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
