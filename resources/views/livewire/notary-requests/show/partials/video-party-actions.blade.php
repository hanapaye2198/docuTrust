@php
    $canJoinPartySession = ($party['has_signed'] ?? false)
        && ($party['session_id'] ?? null)
        && in_array($party['session_status'] ?? '', ['scheduled', 'in_progress'], true);
    $isWaiting = (bool) ($isWaiting ?? false);
@endphp

@if ($canJoinPartySession)
    <div class="flex flex-wrap gap-2">
        @if ($isNotary && ($party['session_status'] ?? '') === 'scheduled')
            <flux:button
                variant="outline"
                size="sm"
                type="button"
                wire:click="startSession({{ $party['session_id'] }})"
                @class([
                    '!border-emerald-300 !bg-emerald-600 !text-white hover:!bg-emerald-700 dark:!border-emerald-700 dark:!bg-emerald-600 dark:hover:!bg-emerald-500' => $isWaiting,
                ])
            >
                {{ __('Start session') }}
            </flux:button>
        @endif

        @include('livewire.notary-requests.show.partials.video-join-link', [
            'notaryRequest' => $notaryRequest,
            'sessionId' => $party['session_id'],
            'label' => $isWaiting ? __('Join now') : null,
            'waiting' => $isWaiting,
        ])
    </div>
@endif
