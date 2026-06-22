<section class="rounded-2xl border border-teal-200/90 bg-teal-50/80 p-5 dark:border-teal-900/50 dark:bg-teal-950/30">
    <div class="flex flex-col gap-4">
        <div class="space-y-1.5">
            <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-teal-900 dark:text-teal-100">
                {{ __('CSC cloud credentials') }}
            </h3>
            <p class="text-sm leading-relaxed text-teal-900/85 dark:text-teal-100/85">
                {{ __('Connect and authorize your cloud signing credential before completing this document.') }}
            </p>
        </div>

        @if ($status === 'authorized')
            <div class="rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                {{ __('Credentials authorized - you may now sign') }}
            </div>
        @endif

        @if ($status === 'error')
            <div class="rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100">
                {{ $errorMessage }}
            </div>
        @endif

        <div wire:loading.flex class="items-center gap-2 text-sm font-medium text-teal-900 dark:text-teal-100">
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-teal-200 border-t-teal-600 dark:border-teal-900 dark:border-t-teal-300"></span>
            <span>{{ __('Loading CSC credentials...') }}</span>
        </div>

        @if ($accessToken === '')
            <div class="flex flex-col gap-3 rounded-xl border border-teal-200/80 bg-white/70 p-4 dark:border-teal-900/50 dark:bg-zinc-950/30">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('Connect your CSC account so DocuTrust can list credentials available for remote PDF signing.') }}
                </p>
                <button
                    type="button"
                    wire:click="connectCsc"
                    class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-900/20 transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:bg-teal-300 dark:shadow-none"
                >
                    {{ __('Connect CSC Credentials') }}
                </button>
            </div>
        @else
            @if ($credentials === [])
                <button
                    type="button"
                    wire:click="loadCredentials"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-900/20 transition hover:bg-teal-500 disabled:cursor-not-allowed disabled:bg-teal-300 dark:shadow-none"
                >
                    {{ __('Load Credentials') }}
                </button>
            @else
                <div class="space-y-3">
                    @foreach ($credentials as $credentialId)
                        <div wire:key="csc-credential-{{ $credentialId }}" class="flex flex-col gap-3 rounded-xl border border-zinc-200/90 bg-white/80 p-4 dark:border-zinc-700 dark:bg-zinc-950/40 sm:flex-row sm:items-center sm:justify-between">
                            <span class="break-all text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $credentialId }}</span>
                            <button
                                type="button"
                                wire:click="selectCredential(@js($credentialId))"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center justify-center rounded-lg border border-teal-200 bg-white px-3 py-2 text-sm font-semibold text-teal-700 transition hover:bg-teal-50 disabled:cursor-not-allowed disabled:text-teal-300 dark:border-teal-900/60 dark:bg-zinc-900 dark:text-teal-200 dark:hover:bg-teal-950/40"
                            >
                                {{ $selectedCredentialId === $credentialId ? __('Selected') : __('Select') }}
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($selectedCredentialId !== '' && $status !== 'authorized')
                <button
                    type="button"
                    wire:click="authorizeCredential"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-emerald-300 dark:shadow-none"
                >
                    {{ __('Authorize Signing') }}
                </button>
            @endif
        @endif
    </div>
</section>
