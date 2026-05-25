<x-mail::message>
# {{ __('Identity verification video call') }}

{{ __('Hello :name,', ['name' => $signer->full_name]) }}

{{ __('You have finished signing for ":title". The next step is a one-on-one video call with :attorney to verify your identity.', [
    'title' => $notaryRequest->title,
    'attorney' => $attorneyName,
]) }}

**{{ __('Scheduled for') }}:** {{ $scheduledFor }}

<x-mail::button :url="$joinUrl">
{{ __('Join your video session') }}
</x-mail::button>

{{ __('Please have ready:') }}

- {{ __('A valid government-issued ID') }}
- {{ __('Stable internet and a quiet, well-lit space') }}
- {{ __('You must be physically located in the Philippines') }}

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
{{ __('View case details') }}
</x-mail::button>

{{ __('This link is for you only. Do not share it with others.') }}

{{ __('Thanks,') }}<br>
{{ config('app.name') }}
</x-mail::message>
