<x-mail::message>
# {{ __('Attorney access approved') }}

{{ __('Your application to practice as an attorney / notary public on :app has been approved.', ['app' => config('app.name')]) }}

**{{ __('Commission') }}:** {{ $credential->commission_number }}

<x-mail::button :url="$dashboardUrl">
{{ __('Open e-Notary dashboard') }}
</x-mail::button>

{{ __('Confirm your seal and signature are on file before starting cases.') }}

<x-mail::button :url="$credentialsUrl">
{{ __('Manage credentials') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
