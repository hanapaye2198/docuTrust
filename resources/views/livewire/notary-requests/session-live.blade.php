<?php

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Services\NotaryJitsiRoomService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public NotaryRequest $notaryRequest;

    public NotarySession $session;

    public array $jitsiConfig = [];

    public function mount(NotaryRequest $notaryRequest, NotarySession $session): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless((int) $session->notary_request_id === (int) $notaryRequest->id, 404);

        $this->authorize('view', $notaryRequest);

        $this->notaryRequest = $notaryRequest;
        $this->session = $session;

        // Build Jitsi config for iframe API
        $jitsiService = app(NotaryJitsiRoomService::class);
        $isModerator = $user->role->value === 'notary';
        $this->jitsiConfig = $jitsiService->getIframeConfig($session, $user, $isModerator);
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-4 p-1">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Live notary session') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $notaryRequest->title }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-950/30 dark:text-red-400">
                <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
                {{ __('Live') }}
            </span>
            <flux:button variant="ghost" :href="auth()->user()?->role->value === 'notary' ? route('notary.requests.show', $notaryRequest) : route('notary-requests.show', $notaryRequest)" wire:navigate>{{ __('Back to request') }}</flux:button>
        </div>
    </div>

    @if (!empty($jitsiConfig['roomName']))
        {{-- Jitsi iframe API container --}}
        <div id="jitsi-container" class="overflow-hidden rounded-2xl border border-zinc-200 shadow-sm dark:border-zinc-800" style="height: 72vh;"></div>

        <div class="flex items-center justify-between">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Keep your government ID ready. The notary will verify your identity during this session.') }}
            </p>
            @if (is_string($session->meeting_url) && $session->meeting_url !== '')
                <a href="{{ $session->meeting_url }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    {{ __('Open in separate window') }} →
                </a>
            @endif
        </div>

        @push('scripts')
        <script src="https://8x8.vc/vpaas-magic-cookie-6f5394927a4a4904812f628ebbf691a3/external_api.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const config = @json($jitsiConfig);

                console.log('[DocuTrust] Jitsi config:', JSON.stringify({
                    domain: config.domain,
                    roomName: config.roomName,
                    hasJwt: !!config.jwt,
                    jwtLength: config.jwt ? config.jwt.length : 0,
                    jwtKid: config.jwt ? JSON.parse(atob(config.jwt.split('.')[0])).kid : 'none'
                }));

                const options = {
                    roomName: config.roomName,
                    parentNode: document.getElementById('jitsi-container'),
                    width: '100%',
                    height: '100%',
                    configOverwrite: config.configOverwrite || {},
                    interfaceConfigOverwrite: config.interfaceConfigOverwrite || {},
                    iframeProps: {
                        allow: 'camera; microphone; display-capture; autoplay; clipboard-write; fullscreen',
                        allowFullScreen: true,
                    },
                };

                // Add JWT if available
                if (config.jwt) {
                    options.jwt = config.jwt;
                }

                const api = new JitsiMeetExternalAPI(config.domain, options);

                // Ensure camera/microphone permissions on the iframe
                const iframe = api.getIFrame();
                if (iframe) {
                    iframe.allow = 'camera *; microphone *; display-capture *; autoplay *; clipboard-write *; fullscreen *';
                }

                // Event listeners
                api.addEventListener('readyToClose', function() {
                    window.location.href = '{{ auth()->user()?->role->value === "notary" ? route("notary.requests.show", $notaryRequest) : route("notary-requests.show", $notaryRequest) }}';
                });

                api.addEventListener('participantJoined', function(participant) {
                    console.log('[DocuTrust] Participant joined:', participant.displayName);
                });

                api.addEventListener('videoConferenceLeft', function() {
                    console.log('[DocuTrust] Conference left');
                });
            });
        </script>
        @endpush
    @elseif (is_string($session->meeting_url) && $session->meeting_url !== '')
        {{-- Fallback: raw iframe for non-Jitsi URLs (Zoom, Google Meet, etc.) --}}
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-black shadow-sm dark:border-zinc-800">
            <iframe
                src="{{ $session->meeting_url }}"
                class="h-[72vh] w-full"
                allow="camera; microphone; fullscreen; display-capture; autoplay"
                referrerpolicy="no-referrer-when-downgrade"
                title="{{ __('Video Session') }}"
            ></iframe>
        </div>
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            {{ __('No meeting URL is configured for this session yet.') }}
        </div>
    @endif
</div>
