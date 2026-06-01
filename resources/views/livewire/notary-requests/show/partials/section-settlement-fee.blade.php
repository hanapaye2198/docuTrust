<div id="section-settlement-fee" class="ui-panel scroll-mt-24 p-5 sm:p-6">
    @php
        $savedFeeAmount = $attorneyRegistryDraft !== null ? (float) $attorneyRegistryDraft->fees : null;
        $pendingFeeAmount = is_numeric($settlementFee) ? (float) $settlementFee : null;
        $showPendingFeeNotice = $pendingFeeAmount !== null
            && ($savedFeeAmount === null || abs($pendingFeeAmount - $savedFeeAmount) > 0.00001);
    @endphp

    <flux:heading size="lg" class="!mb-2">{{ __('Set notarial fee') }}</flux:heading>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Enter the fee amount before creating a payment link. The full 9-field notarial register is completed after the client pays.') }}
    </p>

    @if ($attorneyRegistryDraft)
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ __('Current fee: PHP :amount', ['amount' => number_format((float) $attorneyRegistryDraft->fees, 2)]) }}
            @if ($paymentRequired && ! $hasSettledPayment)
                <span class="mt-1 block text-xs">{{ __('Create or share the payment link below.') }}</span>
            @endif
            @if ($showPendingFeeNotice)
                <span class="mt-1 block text-xs">{{ __('Unsaved amount: PHP :amount. Click Save fee to update the case.', ['amount' => number_format($pendingFeeAmount, 2)]) }}</span>
            @endif
        </div>
    @elseif ($showPendingFeeNotice)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            {{ __('Unsaved amount: PHP :amount. Click Save fee to apply it.', ['amount' => number_format($pendingFeeAmount, 2)]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('notary.requests.settlement-fee', $notaryRequest) }}" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
        @csrf
        <div class="flex-1">
            <label for="settlement-fee-input" class="mb-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                {{ __('Notarial fee (PHP)') }}
            </label>
            <input
                id="settlement-fee-input"
                name="settlement_fee"
                type="number"
                step="0.01"
                min="0.01"
                inputmode="decimal"
                placeholder="500.00"
                value="{{ old('settlement_fee', $settlementFee) }}"
                class="block w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 text-base text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-zinc-900 focus:ring-2 focus:ring-zinc-900/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500 dark:focus:border-zinc-200 dark:focus:ring-zinc-200/10"
            />
            @error('settlement_fee')
                <div class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</div>
            @enderror
        </div>
        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-xl bg-zinc-900 px-5 py-3 text-base font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200 sm:w-auto"
        >
            {{ __('Save fee') }}
        </button>
    </form>
</div>
