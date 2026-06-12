<form method="POST" action="{{ route('notary.requests.settlement-fee', $notaryRequest) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
    @csrf
    <div class="min-w-0 flex-1">
        <label for="do-this-now-settlement-fee" class="mb-2 block text-sm font-medium text-sky-950 dark:text-sky-50">
            {{ __('Notarial fee (PHP)') }}
        </label>
        <input
            id="do-this-now-settlement-fee"
            name="settlement_fee"
            type="number"
            step="0.01"
            min="0.01"
            inputmode="decimal"
            placeholder="500.00"
            value="{{ old('settlement_fee', $settlementFee) }}"
            class="block w-full rounded-xl border border-sky-200 bg-white px-4 py-3 text-base text-zinc-900 shadow-sm outline-none transition placeholder:text-zinc-400 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 dark:border-sky-800 dark:bg-sky-950/40 dark:text-zinc-100"
        />
        @error('settlement_fee')
            <div class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</div>
        @enderror
    </div>
    <button
        type="submit"
        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-sky-700 px-5 py-3 text-base font-semibold text-white transition hover:bg-sky-800 dark:bg-sky-600 dark:hover:bg-sky-500 sm:w-auto"
    >
        {{ __('Save fee') }}
    </button>
</form>
