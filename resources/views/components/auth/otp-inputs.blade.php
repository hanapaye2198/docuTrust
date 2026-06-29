@props([
    'model' => 'code',
    'autoSubmit' => true,
])

@php
    $autoSubmitJs = $autoSubmit ? 'true' : 'false';
@endphp

<div
    {{ $attributes->merge(['class' => 'mt-3']) }}
    x-data="{
        autoSubmit: {{ $autoSubmitJs }},
        _autoTimer: null,
        init() {
            this.$refs.codeInput.value = this.$refs.hiddenCode.value;
        },
        normalize(event) {
            const input = event.target;
            input.value = String(input.value ?? '').replace(/\D/g, '').slice(0, 6);
            this.$refs.hiddenCode.value = input.value;
            this.$refs.hiddenCode.dispatchEvent(new Event('input', { bubbles: true }));
            this.autoSubmitIfComplete(input.value);
        },
        autoSubmitIfComplete(value) {
            if (! this.autoSubmit || value.length !== 6) {
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
    <input
        x-ref="codeInput"
        type="text"
        x-on:input="normalize($event)"
        inputmode="numeric"
        pattern="[0-9]*"
        autocomplete="one-time-code"
        maxlength="6"
        placeholder="000000"
        aria-label="{{ __('6-digit authentication code') }}"
        class="w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3 text-center font-mono text-xl font-semibold tracking-[0.35em] text-[#1F2937] outline-none shadow-sm shadow-zinc-200/60 transition duration-200 placeholder:text-zinc-300 focus:border-[#2EC4B6] focus:ring-2 focus:ring-[#2EC4B6]/30 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:shadow-none dark:placeholder:text-zinc-600 dark:focus:border-teal-400 dark:focus:ring-teal-500/30 sm:px-5 sm:py-4 sm:text-2xl"
        required
    />
</div>
