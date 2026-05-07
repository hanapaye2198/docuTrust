@props([
    'model' => 'code',
    'autoSubmit' => true,
])

@php
    $autoSubmitJs = $autoSubmit ? 'true' : 'false';
@endphp

<div
    class="contents"
    x-data="{
        digits: Array.from({ length: 6 }, () => ''),
        autoSubmit: {{ $autoSubmitJs }},
        _autoTimer: null,
        get joinedCode() {
            return this.digits.join('');
        },
        get isComplete() {
            return this.joinedCode.length === 6;
        },
        init() {
            this.syncFromHidden();
            this.$refs.hiddenCode?.addEventListener('input', () => {
                this.syncFromHidden();
                this.refreshInputElements();
            });
            this.$nextTick(() => this.focusIndex(0));
        },
        syncFromHidden() {
            const v = this.$refs.hiddenCode?.value ?? '';
            const clean = String(v).replace(/\D/g, '').slice(0, 6);
            this.digits = Array.from({ length: 6 }, (_, i) => clean[i] ?? '');
        },
        refreshInputElements() {
            this.$nextTick(() => {
                this.$el.querySelectorAll('.otp-input').forEach((el, i) => {
                    el.value = this.digits[i] ?? '';
                });
            });
        },
        syncToLivewire() {
            if (! this.$refs.hiddenCode) {
                return;
            }
            this.$refs.hiddenCode.value = this.joinedCode;
            this.$refs.hiddenCode.dispatchEvent(new Event('input', { bubbles: true }));
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
            this.syncToLivewire();
            if (this.digits[index] !== '' && index < 5) {
                this.focusIndex(index + 1);
            }
            this.autoSubmitIfComplete();
        },
        onKeydown(index, event) {
            if (event.key === 'Backspace' && this.digits[index] === '' && index > 0) {
                this.focusIndex(index - 1);

                return;
            }
            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                this.focusIndex(index - 1);
            }
            if (event.key === 'ArrowRight' && index < 5) {
                event.preventDefault();
                this.focusIndex(index + 1);
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
            this.refreshInputElements();
            this.syncToLivewire();
            const focusTo = Math.min(startIndex + clean.length - 1, 5);
            this.focusIndex(focusTo);
            this.autoSubmitIfComplete();
        },
        onPaste(event) {
            event.preventDefault();
            const text = event.clipboardData?.getData('text') ?? '';
            const clean = text.replace(/\D/g, '');
            const inputs = [...this.$el.querySelectorAll('.otp-input')];
            const startIndex = inputs.indexOf(event.currentTarget);
            if (clean.length >= 6) {
                this.digits = Array.from({ length: 6 }, () => '');
                this.fillFromString(clean, 0);

                return;
            }
            this.fillFromString(clean, startIndex >= 0 ? startIndex : 0);
        },
        autoSubmitIfComplete() {
            if (! this.autoSubmit || ! this.isComplete) {
                return;
            }
            clearTimeout(this._autoTimer);
            this._autoTimer = setTimeout(() => {
                this.$el.closest('form')?.requestSubmit();
            }, 75);
        },
    }"
>
    <input x-ref="hiddenCode" type="hidden" wire:model.live="{{ $model }}" />
    <div class="mt-3 grid grid-cols-6 gap-2 sm:gap-3" data-otp-grid>
        <template x-for="(digit, index) in digits" :key="index">
            <input
                :data-otp-index="index"
                x-model="digits[index]"
                x-on:input="onInput(index, $event)"
                x-on:keydown="onKeydown(index, $event)"
                x-on:paste="onPaste($event)"
                x-on:focus="$event.target.select()"
                type="text"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="one-time-code"
                class="otp-input h-12 rounded-xl border-2 border-gray-300 bg-white text-center text-lg font-semibold text-[#1F2937] outline-none shadow-sm shadow-zinc-200/60 transition duration-200 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/30 motion-safe:focus:scale-[1.03] dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:shadow-none dark:focus:border-teal-400 dark:focus:ring-teal-500/30 sm:h-14 sm:text-xl"
                :class="digits[index] !== '' ? 'border-[#2EC4B6] shadow-sm dark:border-teal-500/80' : ''"
                :aria-label="`OTP digit ${index + 1}`"
                required
            />
        </template>
    </div>
</div>
