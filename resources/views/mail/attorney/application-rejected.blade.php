<x-mail::message>
# {{ __('Attorney application not approved') }}

{{ __('Your attorney application was reviewed and could not be approved at this time.') }}

**{{ __('Reason') }}:** {{ $reason }}

{{ __('You may update your documents and submit a new application.') }}

<x-mail::button :url="$reapplyUrl">
{{ __('Update application') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
