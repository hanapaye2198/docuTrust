<x-mail::message>
# Video Session Scheduled

A live video verification session has been scheduled for your notary request.

**Request:** {{ $notaryRequest->title }}
**Scheduled for:** {{ $scheduledFor }}

@if ($meetingUrl)
<x-mail::button :url="$meetingUrl">
Join Meeting
</x-mail::button>
@endif

Please ensure you have:
- A valid government-issued ID ready to show
- A stable internet connection
- A quiet, well-lit environment
- You are physically located in the Philippines

<x-mail::button :url="route('notary-requests.show', $notaryRequest)">
View Request Details
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
