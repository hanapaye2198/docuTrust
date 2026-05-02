{{-- Fixed overlay + modal for idle warning; toggled by resources/js/idle-session.js --}}
<div
    id="idle-timeout-overlay"
    class="fixed inset-0 z-[200] hidden items-center justify-center bg-zinc-950/70 p-4 backdrop-blur-[2px]"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    aria-labelledby="idle-timeout-title"
    aria-describedby="idle-timeout-desc"
>
    <div
        class="relative z-[201] w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
        onclick="event.stopPropagation()"
    >
        <div class="flex flex-col gap-4">
            <div>
                <h2 id="idle-timeout-title" class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ __('Session timeout') }}
                </h2>
                <p id="idle-timeout-desc" class="mt-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    <span id="idle-timeout-message">{{ __('You will be logged out in 60 seconds due to inactivity.') }}</span>
                </p>
                <p class="mt-3 text-center text-3xl font-semibold tabular-nums text-teal-700 dark:text-teal-400">
                    <span id="idle-timeout-countdown">60</span>
                    <span class="text-base font-medium text-zinc-500">{{ __('s') }}</span>
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button
                    id="idle-timeout-stay"
                    type="button"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-[#2EC4B6] px-4 py-2.5 text-sm font-semibold text-black transition hover:bg-[#1B5E20] hover:text-white sm:w-auto"
                >
                    {{ __('Stay logged in') }}
                </button>
                <button
                    id="idle-timeout-logout"
                    type="button"
                    class="inline-flex w-full items-center justify-center rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700 sm:w-auto"
                >
                    {{ __('Log out now') }}
                </button>
            </div>
        </div>
    </div>
</div>
