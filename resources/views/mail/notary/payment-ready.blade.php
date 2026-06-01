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

Use the payment link below to open your case payment page, choose a payment method, and continue the notarization workflow.

<x-mail::button :url="$paymentUrl">
Pay Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
