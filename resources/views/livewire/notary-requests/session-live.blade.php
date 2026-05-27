<?php

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use App\Services\NotaryJitsiRoomService;
use App\Services\NotaryRequestWorkflowService;
use App\Services\NotarySchedulingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public NotaryRequest $notaryRequest;

    public NotarySession $session;

    public array $jitsiConfig = [];

    public string $externalApiScriptUrl = '';

    public string $popOutMeetingUrl = '';

    public bool $isAssignedNotary = false;

    public ?string $partyName = null;

    public function mount(NotaryRequest $notaryRequest, NotarySession $session): void
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);
        abort_unless((int) $session->notary_request_id === (int) $notaryRequest->id, 404);

        $this->authorize('view', $notaryRequest);

        $session->loadMissing('notarySigner');

        $this->notaryRequest = $notaryRequest;
        $this->session = $session;
        $this->partyName = $session->notarySigner?->full_name;
        $this->isAssignedNotary = $user->role->value === 'notary'
            && (int) $notaryRequest->notary_user_id === (int) $user->id;

        $jitsiService = app(NotaryJitsiRoomService::class);
        $isModerator = $this->isAssignedNotary;
        $this->jitsiConfig = $jitsiService->getIframeConfig($session, $user, $isModerator);
        $this->externalApiScriptUrl = $jitsiService->externalApiScriptUrl();

        if (is_string($session->room_name) && $session->room_name !== '') {
            $this->popOutMeetingUrl = $jitsiService->meetingUrlForUser($session->room_name, $user, $isModerator);
        } elseif (is_string($session->meeting_url) && $session->meeting_url !== '') {
            $this->popOutMeetingUrl = $session->meeting_url;
        }

        if ($this->isAssignedNotary && $session->status === 'scheduled') {
            $this->session = app(NotarySchedulingService::class)->start($session);
        }
    }

    public function verifySigner(): void
    {
        abort_unless($this->isAssignedNotary, 403);

        if (in_array($this->session->status, ['completed', 'cancelled'], true)) {
            $this->addError('verifySigner', __('This video session has already ended.'));

            return;
        }

        try {
            if ($this->session->status === 'scheduled') {
                $this->session = app(NotarySchedulingService::class)->start($this->session);
            }

            $checklist = array_fill_keys(config('docutrust.notary.verification_checklist', []), true);

            app(NotarySchedulingService::class)->complete($this->session, $checklist, [
                'verified_via' => 'live_session',
                'verified_by_user_id' => Auth::id(),
                'verified_at' => now()->toDateTimeString(),
            ]);

            $request = $this->notaryRequest->fresh();
            $workflow = app(NotaryRequestWorkflowService::class);

            if (
                $workflow->hasCompletedSession($request)
                && $workflow->canBeginAttorneySigning($request)
                && ! $workflow->hasAttorneySignedAllDocuments($request)
            ) {
                session()->flash('status', __('Signer identity verified. Sign the contract as attorney on the Documents tab.'));
            } else {
                session()->flash('status', __('Signer identity verified on video.'));
            }

            $this->redirect(route('notary.requests.show', $request), navigate: true);
        } catch (\RuntimeException $exception) {
            $this->addError('verifySigner', $exception->getMessage());
        }
    }

    public function cancelSession(): void
    {
        abort_unless($this->isAssignedNotary, 403);

        try {
            app(NotarySchedulingService::class)->cancel(
                $this->session,
                __('Attorney ended the session before completing identity verification.'),
            );

            session()->flash('status', __('Video session ended. You can start a new verification call when ready.'));

            $this->redirect(route('notary.requests.show', $this->notaryRequest), navigate: true);
        } catch (\RuntimeException $exception) {
            $this->addError('cancelSession', $exception->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-4 p-1">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Live notary session') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $notaryRequest->title }}
                @if ($partyName)
                    <span class="text-zinc-400 dark:text-zinc-500">· {{ $partyName }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-950/30 dark:text-red-400">
                <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
                {{ __('Live') }}
            </span>
            <flux:button variant="ghost" :href="route('notary.requests.show', $notaryRequest)" wire:navigate>{{ __('Back to request') }}</flux:button>
        </div>
    </div>

    @if ($isAssignedNotary && ! in_array($session->status, ['completed', 'cancelled'], true))
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/80 px-4 py-3 text-sm text-indigo-950 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-100">
            <p class="font-medium">{{ __('Verify the signer on camera') }}</p>
            <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90">
                {{ __('Confirm they match their government ID and are signing willingly. Mark verified when satisfied, or cancel if you cannot complete verification.') }}
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button
                    variant="primary"
                    type="button"
                    wire:click="verifySigner"
                    wire:loading.attr="disabled"
                    wire:target="verifySigner"
                    icon="check-circle"
                >
                    {{ __('Signer verified') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    type="button"
                    wire:click="cancelSession"
                    wire:confirm="{{ __('End this video call without marking the signer as verified?') }}"
                    wire:loading.attr="disabled"
                    wire:target="cancelSession"
                >
                    {{ __('Cancel session') }}
                </flux:button>
            </div>
            <flux:error name="verifySigner" />
            <flux:error name="cancelSession" />
        </div>
    @elseif ($session->status === 'completed')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ __('This verification session is complete.') }}
        </div>
    @elseif ($session->status === 'cancelled')
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
            {{ __('This verification session was cancelled.') }}
        </div>
    @endif

    @if (!empty($jitsiConfig['roomName']))
        <div
            id="jitsi-container"
            class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-950 shadow-sm dark:border-zinc-800"
            style="height: 72vh; min-height: 420px;"
            wire:ignore
        >
            <div id="jitsi-loading" class="absolute inset-0 flex flex-col items-center justify-center gap-3 text-zinc-400">
                <svg class="h-8 w-8 animate-spin text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span class="text-sm">{{ __('Connecting to video room…') }}</span>
            </div>
            <div id="jitsi-error" class="absolute inset-0 hidden flex-col items-center justify-center gap-3 px-6 text-center text-sm text-amber-200"></div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('Keep your government ID ready. The notary will verify your identity during this session.') }}
            </p>
            @if ($popOutMeetingUrl !== '')
                <a href="{{ $popOutMeetingUrl }}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                    {{ __('Open in separate window') }} →
                </a>
            @endif
        </div>

        @script
        <script>
            (function () {
                const config = @json($jitsiConfig);
                const scriptUrl = @json($externalApiScriptUrl);
                const backUrl = @json(route('notary.requests.show', $notaryRequest));

                let jitsiApi = null;

                function hideLoading() {
                    document.getElementById('jitsi-loading')?.classList.add('hidden');
                }

                function showError(message) {
                    hideLoading();
                    const errorEl = document.getElementById('jitsi-error');
                    if (! errorEl) {
                        return;
                    }
                    errorEl.textContent = message;
                    errorEl.classList.remove('hidden');
                    errorEl.classList.add('flex');
                }

                function disposeJitsi() {
                    if (jitsiApi) {
                        try {
                            jitsiApi.dispose();
                        } catch (error) {
                            console.warn('[DocuTrust] Jitsi dispose failed', error);
                        }
                        jitsiApi = null;
                    }
                }

                function loadJitsiScript() {
                    return new Promise((resolve, reject) => {
                        if (typeof JitsiMeetExternalAPI !== 'undefined') {
                            resolve();
                            return;
                        }

                        const existing = document.querySelector('script[data-docutrust-jitsi="1"]');
                        if (existing) {
                            existing.addEventListener('load', () => resolve());
                            existing.addEventListener('error', () => reject(new Error('Failed to load Jitsi SDK')));
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = scriptUrl;
                        script.async = true;
                        script.dataset.docutrustJitsi = '1';
                        script.onload = () => resolve();
                        script.onerror = () => reject(new Error('Failed to load Jitsi SDK'));
                        document.head.appendChild(script);
                    });
                }

                async function initJitsi() {
                    const container = document.getElementById('jitsi-container');
                    if (! container || ! config?.roomName) {
                        return;
                    }

                    disposeJitsi();

                    try {
                        await loadJitsiScript();

                        const options = {
                            roomName: config.roomName,
                            parentNode: container,
                            width: '100%',
                            height: '100%',
                            configOverwrite: config.configOverwrite || {},
                            interfaceConfigOverwrite: config.interfaceConfigOverwrite || {},
                            userInfo: config.userInfo || undefined,
                            iframeProps: {
                                allow: 'camera; microphone; display-capture; autoplay; clipboard-write; fullscreen',
                                allowFullScreen: true,
                            },
                        };

                        if (config.jwt) {
                            options.jwt = config.jwt;
                        }

                        jitsiApi = new JitsiMeetExternalAPI(config.domain, options);

                        const iframe = jitsiApi.getIFrame();
                        if (iframe) {
                            iframe.allow = 'camera *; microphone *; display-capture *; autoplay *; clipboard-write *; fullscreen *';
                            iframe.addEventListener('load', () => hideLoading());
                        }

                        jitsiApi.addEventListener('videoConferenceJoined', () => hideLoading());
                        jitsiApi.addEventListener('readyToClose', () => {
                            window.location.href = backUrl;
                        });
                    } catch (error) {
                        console.error('[DocuTrust] Jitsi init failed', error);
                        showError(@json(__('Unable to load the embedded video room. Use “Open in separate window” or refresh this page.')));
                    }
                }

                function boot() {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initJitsi, { once: true });
                    } else {
                        initJitsi();
                    }
                }

                boot();
                document.addEventListener('livewire:navigated', boot);
                window.addEventListener('beforeunload', disposeJitsi);
            })();
        </script>
        @endscript
    @elseif (is_string($session->meeting_url) && $session->meeting_url !== '')
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-black shadow-sm dark:border-zinc-800">
            <iframe
                src="{{ $popOutMeetingUrl !== '' ? $popOutMeetingUrl : $session->meeting_url }}"
                class="h-[72vh] min-h-[420px] w-full"
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
