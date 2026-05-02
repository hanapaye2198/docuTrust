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
            x-data="{
                digits: ['', '', '', '', '', ''],
                submitted: false,
                init() {
                    this.syncFromCode(this.$refs.codeInput.value);
                    this.$watch(() => this.joinedCode, (value) => {
                        this.$refs.codeInput.value = value;
                    });
                },
                get joinedCode() {
                    return this.digits.join('');
                },
                get isComplete() {
                    return this.joinedCode.length === 6;
                },
                syncFromCode(value) {
                    const clean = String(value ?? '').replace(/\D/g, '').slice(0, 6);
                    this.digits = Array.from({ length: 6 }, (_, index) => clean[index] ?? '');
                },
                focusIndex(index) {
                    this.$nextTick(() => {
                        this.$el.querySelector(`[data-otp-index='${index}']`)?.focus();
                    });
                },
                onInput(index, event) {
                    const rawValue = String(event.target.value ?? '');
                    const clean = rawValue.replace(/\D/g, '');

                    if (clean.length > 1) {
                        this.fillFromString(clean, index);
                        return;
                    }

                    this.digits[index] = clean === '' ? '' : clean;
                    event.target.value = this.digits[index];

                    if (this.digits[index] !== '' && index < 5) {
                        this.focusIndex(index + 1);
                    }

                    this.autoSubmitIfComplete();
                },
                onKeydown(index, event) {
                    if (event.key === 'Backspace' && this.digits[index] === '' && index > 0) {
                        this.focusIndex(index - 1);
                    }
                },
                fillFromString(value, startIndex = 0) {
                    const clean = String(value ?? '').replace(/\D/g, '').slice(0, 6 - startIndex);
                    if (clean === '') {
                        return;
                    }

                    for (let offset = 0; offset < clean.length; offset++) {
                        this.digits[startIndex + offset] = clean[offset] ?? '';
                    }

                    const focusTo = Math.min(startIndex + clean.length - 1, 5);
                    this.focusIndex(focusTo);
                    this.autoSubmitIfComplete();
                },
                onPaste(event) {
                    event.preventDefault();
                    this.digits = ['', '', '', '', '', ''];
                    this.fillFromString(event.clipboardData?.getData('text') ?? '', 0);
                },
                autoSubmitIfComplete() {
                    if (! this.isComplete || this.submitted) {
                        return;
                    }

                    this.submitted = true;
                    this.$nextTick(() => this.$refs.submitButton.click());
                },
            }"
            x-init="init(); focusIndex(0);"
        >
            @csrf
            <div>
                <p class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ __('Authentication code') }}</p>
                <div class="grid grid-cols-6 gap-2 sm:gap-3" @paste="onPaste($event)">
                    <template x-for="index in 6" :key="index">
                        <input
                            :data-otp-index="index - 1"
                            x-model="digits[index - 1]"
                            x-on:input="onInput(index - 1, $event)"
                            x-on:keydown="onKeydown(index - 1, $event)"
                            x-on:focus="$event.target.select()"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="1"
                            required
                            class="h-12 rounded-lg border border-zinc-300 bg-white text-center text-lg font-semibold text-zinc-900 outline-none transition focus:border-teal-500 focus:ring-2 focus:ring-teal-300/60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:border-teal-400 dark:focus:ring-teal-500/30 sm:h-14 sm:text-xl"
                            :class="digits[index - 1] !== '' ? 'border-teal-500 dark:border-teal-400' : ''"
                        />
                    </template>
                </div>
                <input x-ref="codeInput" id="code" name="code" type="hidden" value="{{ old('code') }}" required />
            </div>
            @error('code')
                <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                    {{ $message }}
                </p>
            @enderror

            <flux:button
                x-ref="submitButton"
                variant="primary"
                type="submit"
                class="w-full disabled:cursor-not-allowed disabled:opacity-50"
                x-bind:disabled="! isComplete || submitted"
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
