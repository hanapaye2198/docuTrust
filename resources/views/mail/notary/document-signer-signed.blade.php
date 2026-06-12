<x-mail::message>
# {{ __('Signature recorded') }}

{{ __('Hello :name,', ['name' => $signer->name]) }}

{{ __('Your signature on ":title" was recorded successfully. No further edits are needed on your part for this instrument.', [
    'title' => $document->title,
]) }}

{{ __('Next step: :attorney will verify your identity on a one-on-one video call. You will receive a separate email with your personal video link shortly.', [
    'attorney' => $attorneyName,
]) }}

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
{{ __('View case details') }}
</x-mail::button>

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
