<x-mail::message>
# {{ __('Identity verified on video') }}

{{ __('Hello :name,', ['name' => $signer->full_name]) }}

{{ __('Your identity was successfully verified with :attorney during the live video session for ":title".', [
    'attorney' => $attorneyName,
    'title' => $notaryRequest->title,
]) }}

@if ($verifiedAt)
**{{ __('Verified at') }}:** {{ $verifiedAt }}
@endif

{{ __('No further action is required from you for this verification step. Your attorney will continue the notarization process.') }}

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
{{ __('View case details') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
