<x-layouts.auth.simple>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8">
        <div class="mx-auto max-w-md px-6 py-8 text-center">
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
                {{ __("You're in the waiting room") }}
            </h1>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __("The notary has been notified you're here.") }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                {{ __('Please stay on this page. The session will begin shortly.') }}
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-black shadow-sm dark:border-zinc-800">
            <iframe
                src="{{ $meetingUrl }}"
                class="h-[70vh] min-h-[420px] w-full"
                allow="camera; microphone; fullscreen; display-capture; autoplay"
                referrerpolicy="no-referrer-when-downgrade"
                title="{{ __('Video verification room') }}"
            ></iframe>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-500 dark:text-zinc-400">
            <span>
                {{ __('Keep your government ID ready for verification.') }}
            </span>
            <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400">
                {{ __('Open video in a separate window') }}
            </a>
        </div>
    </div>
</x-layouts.auth.simple>
