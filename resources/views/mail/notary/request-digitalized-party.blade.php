<x-mail::message>
# {{ __('Your digitally notarized copy is ready') }}

{{ __('Hello :name,', ['name' => $signer->name]) }}

{{ __('The notary seal, QR verification code, and certificate have been applied to ":title". Your digitally notarized copy is now available.', [
    'title' => $notaryRequest->title,
]) }}

**{{ __('Processed by') }}:** {{ $notaryName }}
@if ($digitalizedAt)
**{{ __('Completed at') }}:** {{ $digitalizedAt }}
@endif

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
{{ __('View and download your copy') }}
</x-mail::button>

{{ __('Keep this copy for your records. The QR code on the certificate can be used to verify authenticity.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
