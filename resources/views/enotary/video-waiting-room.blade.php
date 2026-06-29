<x-layouts.auth.simple container-class="max-w-7xl">
    <div class="mx-auto flex w-full flex-col gap-4 py-4 sm:gap-6 sm:py-6 lg:py-8">
        <div class="mx-auto max-w-xl px-4 py-4 text-center sm:px-6 sm:py-6">
            <div class="mb-6 flex justify-center">
                <div class="relative size-16">
                    <div class="absolute inset-0 rounded-full border-4 border-blue-200 dark:border-blue-800"></div>
                    <div class="absolute inset-0 animate-spin rounded-full border-4 border-blue-500 border-t-transparent"></div>
                    <div class="absolute inset-2 flex items-center justify-center rounded-full bg-blue-50 dark:bg-blue-950">
                        <svg class="size-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0 1 21 8.82v6.36a1 1 0 0 1-1.447.89L15 14M5 18h8a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">
                {{ __('Joining your video call') }}
            </h1>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('The attorney has been notified you are here.') }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('If the video does not appear, open it in a separate window below.') }}
            </p>
        </div>

        <div
            id="video-complete-panel"
            class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-8 text-center text-emerald-900 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-4 text-lg font-semibold">
                {{ __('Video verification complete') }}
            </h2>
            <p id="video-complete-message" class="mt-2 text-sm text-emerald-800/90 dark:text-emerald-100/90">
                {{ __('Your attorney has verified your identity. This video call has ended automatically.') }}
            </p>
        </div>

        <div id="video-frame-panel" class="overflow-hidden rounded-2xl border border-zinc-200 bg-black shadow-sm dark:border-zinc-800">
            <iframe
                id="video-room-frame"
                src="{{ $meetingUrl }}"
                class="h-[68svh] min-h-[360px] w-full sm:h-[72svh] sm:min-h-[460px] lg:h-[76svh]"
                allow="camera; microphone; fullscreen; display-capture; autoplay"
                referrerpolicy="no-referrer-when-downgrade"
                title="{{ __('Video verification room') }}"
            ></iframe>
        </div>

        <div class="flex flex-col gap-2 text-xs text-zinc-500 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
            <span>
                {{ __('Keep your government ID ready for verification.') }}
            </span>
            <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                {{ __('Open video in a separate window') }}
            </a>
        </div>
    </div>

    <script>
        (() => {
            const statusUrl = @json(route('enotary.video.status', ['token' => $session->access_token]));
            const frame = document.getElementById('video-room-frame');
            const framePanel = document.getElementById('video-frame-panel');
            const completePanel = document.getElementById('video-complete-panel');
            const completeMessage = document.getElementById('video-complete-message');
            let timerId = null;
            let stopped = false;

            const stopEmbeddedCall = (message) => {
                if (stopped) {
                    return;
                }

                stopped = true;

                if (timerId !== null) {
                    window.clearTimeout(timerId);
                }

                if (frame) {
                    frame.src = 'about:blank';
                }

                framePanel?.classList.add('hidden');

                if (message && completeMessage) {
                    completeMessage.textContent = message;
                }

                completePanel?.classList.remove('hidden');
            };

            const scheduleStatusCheck = (delay = 5000) => {
                if (stopped) {
                    return;
                }

                timerId = window.setTimeout(checkStatus, delay);
            };

            const checkStatus = async () => {
                if (stopped) {
                    return;
                }

                try {
                    const response = await fetch(statusUrl, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (response.status === 404) {
                        stopEmbeddedCall(@json(__('This video verification link is no longer available.')));
                        return;
                    }

                    if (! response.ok) {
                        scheduleStatusCheck(8000);
                        return;
                    }

                    const payload = await response.json();

                    if (payload.completed) {
                        stopEmbeddedCall(@json(__('Your attorney has verified your identity. This video call has ended automatically.')));
                        return;
                    }

                    if (payload.cancelled) {
                        stopEmbeddedCall(@json(__('Your attorney ended this video call. Contact them if you need a new verification link.')));
                        return;
                    }
                } catch (error) {
                    scheduleStatusCheck(8000);
                    return;
                }

                scheduleStatusCheck();
            };

            checkStatus();
        })();
    </script>
</x-layouts.auth.simple>
