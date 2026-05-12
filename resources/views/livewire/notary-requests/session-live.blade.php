<?php

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public NotaryRequest $notaryRequest;

    public NotarySession $session;

    public function mount(NotaryRequest $notaryRequest, NotarySession $session): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless((int) $session->notary_request_id === (int) $notaryRequest->id, 404);

        $this->authorize('view', $notaryRequest);

        $this->notaryRequest = $notaryRequest;
        $this->session = $session;
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-4 p-1">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Live notary session') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $notaryRequest->title }}</p>
        </div>
        <flux:button variant="ghost" :href="auth()->user()?->role->value === 'notary' ? route('notary.requests.show', $notaryRequest) : route('notary-requests.show', $notaryRequest)" wire:navigate>{{ __('Back to request') }}</flux:button>
    </div>

    @if (is_string($session->meeting_url) && $session->meeting_url !== '')
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-black shadow-sm dark:border-zinc-800">
            <iframe
                src="{{ $session->meeting_url }}"
                class="h-[72vh] w-full"
                allow="camera; microphone; fullscreen; display-capture; autoplay"
                referrerpolicy="no-referrer-when-downgrade"
                title="{{ __('Jitsi Meet') }}"
            ></iframe>
        </div>
        <p class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ __('Use a headset in a quiet space. Keep your government ID ready for the notary checklist.') }}
        </p>
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            {{ __('No meeting URL is configured for this session yet.') }}
        </div>
    @endif
</div>
