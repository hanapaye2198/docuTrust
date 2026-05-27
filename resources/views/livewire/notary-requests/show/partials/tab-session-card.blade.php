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
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Attorney verification checklist') }}</span>
                <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">{{ __('Complete all items before ending the session.') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach (config('docutrust.notary.verification_checklist', []) as $key)
                        <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-amber-100/50 dark:hover:bg-amber-950/30">
                            <input type="checkbox" class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-700" wire:model.live="sessionChecklist.{{ $key }}" />
                            <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="sessionChecklist" />
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" type="button" wire:click="completeSession({{ $session->id }})">{{ __('Complete session') }}</flux:button>
                    <flux:error name="completeSession" />
                </div>
            </div>
        @endif
    @endif

    @if ($session->status === 'completed')
        <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">
            {{ __('Completed') }} {{ $session->ended_at?->diffForHumans() }}
        </div>
    @endif
</div>
