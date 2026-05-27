@php
    $canJoinPartySession = ($party['has_signed'] ?? false)
        && ($party['session_id'] ?? null)
        && in_array($party['session_status'] ?? '', ['scheduled', 'in_progress'], true);
@endphp

@if ($canJoinPartySession)
    <div class="flex flex-wrap gap-2">
        @if ($isNotary && ($party['session_status'] ?? '') === 'scheduled')
            <flux:button
                variant="outline"
                size="sm"
                type="button"
                wire:click="startSession({{ $party['session_id'] }})"
            >
                {{ __('Start session') }}
            </flux:button>
        @endif

        @include('livewire.notary-requests.show.partials.video-join-link', [
            'notaryRequest' => $notaryRequest,
            'sessionId' => $party['session_id'],
        ])
    </div>
@endif
