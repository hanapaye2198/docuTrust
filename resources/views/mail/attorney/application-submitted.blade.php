<x-mail::message>
# {{ __('New attorney application') }}

**{{ __('Applicant') }}:** {{ $applicantName }}

**{{ __('Commission') }}:** {{ $credential->commission_number }}

**{{ __('Jurisdiction') }}:** {{ $credential->commission_jurisdiction }}

**{{ __('Expires') }}:** {{ $credential->commission_expires_at?->format('M j, Y') ?? '—' }}

@if ($credential->is_renewal)
**{{ __('Type') }}:** {{ __('Renewal') }}
@endif

<x-mail::button :url="$reviewUrl">
{{ __('Review application') }}
</x-mail::button>

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
