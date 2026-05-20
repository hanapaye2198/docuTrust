<x-mail::message>
# Payment Required

Your notarization request is ready for payment.

**Request:** {{ $notaryRequest->title }}
**Assigned notary:** {{ $notaryName }}
**Amount due:** PHP {{ $amount }}
**Gateway:** {{ strtoupper((string) $payment->gateway) }}
@if ($expiresAt)
**Payment expires:** {{ $expiresAt }}
@endif

Complete the payment from your request page to continue the notarization workflow.

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
