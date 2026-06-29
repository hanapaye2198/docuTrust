<x-layouts.auth.simple>
    <div class="flex w-full max-w-sm flex-col gap-6">
        <div class="flex flex-col gap-2 text-center sm:text-left">
            <flux:heading size="xl" level="1" class="text-zinc-900 dark:text-zinc-50">
                {{ __('Two-factor authentication') }}
            </flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                {{ __('Enter the 6-digit code from your authenticator app to finish signing in.') }}
            </flux:subheading>
        </div>

        <form
            method="POST"
            action="{{ route('two-factor.verify') }}"
            class="flex flex-col gap-5"
            x-ref="verificationForm"
            x-on:submit="submitted = true"
            x-data="{
                submitted: false,
                normalize(event) {
                    const input = event.target;
                    input.value = String(input.value ?? '').replace(/\D/g, '').slice(0, 6);
                    this.autoSubmitIfComplete(input.value);
                },
                autoSubmitIfComplete(value) {
                    if (value.length !== 6 || this.submitted) {
                        return;
                    }

                    this.$refs.verificationForm.requestSubmit();
                },
            }"
        >
            @csrf
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Authentication code') }}</p>
                <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Type or paste the full 6-digit code.') }}</p>
                <input
                    id="code"
                    name="code"
                    type="text"
                    value="{{ old('code') }}"
                    x-on:input="normalize($event)"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="one-time-code"
                    maxlength="6"
                    placeholder="000000"
                    class="w-full rounded-xl border-2 border-zinc-300 bg-white px-4 py-3 text-center font-mono text-xl font-semibold tracking-[0.35em] text-zinc-900 outline-none transition placeholder:text-zinc-300 focus:border-teal-500 focus:ring-2 focus:ring-teal-300/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:placeholder:text-zinc-600 dark:focus:border-teal-400 dark:focus:ring-teal-500/30 sm:px-5 sm:py-4 sm:text-2xl"
                    required
                />
            </div>
            <flux:input
                name="recovery_code"
                label="{{ __('Recovery code (optional)') }}"
                type="text"
                autocomplete="one-time-code"
                placeholder="{{ __('xxxx-xxxx') }}"
            />
            @php
                $rememberDeviceChecked = old('remember_device') !== null
                    ? (bool) old('remember_device')
                    : (bool) session(\App\Support\AuthSession::PENDING_TWO_FACTOR_REMEMBER, false);
            @endphp
            <flux:checkbox
                name="remember_device"
                value="1"
                label="{{ __('Trust this device for 30 days') }}"
                @if ($rememberDeviceChecked) checked @endif
            />
            @error('code')
                <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    {{ $message }}
                </p>
            @enderror
            @error('recovery_code')
                <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    {{ $message }}
                </p>
            @enderror

            <flux:button
                variant="primary"
                type="submit"
                class="w-full disabled:cursor-not-allowed disabled:opacity-50"
                x-bind:disabled="submitted"
            >
                {{ __('Verify and continue') }}
            </flux:button>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            <a
                href="{{ route('login') }}"
                class="font-medium text-teal-600 underline decoration-teal-500/30 underline-offset-4 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300"
            >
                {{ __('Back to sign in') }}
            </a>
        </div>
    </div>
</x-layouts.auth.simple>
