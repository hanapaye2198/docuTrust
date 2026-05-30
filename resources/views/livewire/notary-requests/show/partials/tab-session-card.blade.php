<div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/40">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-2.5">
            @if ($session->status === 'in_progress')
                <span class="flex h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
            @elseif ($session->status === 'completed')
                <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
            @else
                <span class="flex h-2 w-2 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
            @endif
            <div class="min-w-0">
                <span class="block truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">
                    @if ($session->notarySigner)
                        {{ $session->notarySigner->full_name }}
                    @else
                        {{ ucfirst($session->provider_name) }}
                    @endif
                </span>
                <span class="mt-0.5 block text-xs text-zinc-400 dark:text-zinc-500">
                    {{ $session->scheduled_for?->format('M j, g:i A') ?? '-' }}
                    @if ($session->invitation_sent_at)
                        · {{ __('invite sent') }}
                    @endif
                </span>
            </div>
        </div>
        <span class="inline-flex w-fit rounded-md border border-zinc-200 bg-zinc-50 px-2 py-0.5 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">{{ $session->status }}</span>
    </div>

    @if (in_array($session->status, ['scheduled', 'in_progress'], true))
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($session->status === 'scheduled' && $isNotary)
                <flux:button variant="outline" size="sm" type="button" wire:click="startSession({{ $session->id }})">{{ __('Start session') }}</flux:button>
                <flux:error name="startSession" />
            @endif

            @include('livewire.notary-requests.show.partials.video-join-link', [
                'notaryRequest' => $notaryRequest,
                'sessionId' => $session->id,
            ])
        </div>
    @endif

    @if ($session->status === 'in_progress')
        @if (is_string($session->meeting_url) && $session->meeting_url !== '')
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ $session->meeting_url }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                    {{ __('Open in new tab') }}
                </a>
            </div>
        @endif

        @if ($isNotary)
            @include('livewire.notary-requests.show.partials.video-session-checklist', [
                'sessionId' => $session->id,
                'class' => 'mt-4',
            ])
        @endif
    @endif

    @if ($session->status === 'completed')
        <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">
            {{ __('Completed') }} {{ $session->ended_at?->diffForHumans() }}
        </div>
    @endif
</div>
