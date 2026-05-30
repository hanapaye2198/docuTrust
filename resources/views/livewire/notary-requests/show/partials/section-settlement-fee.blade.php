<div id="section-settlement-fee" class="ui-panel scroll-mt-24 p-5 sm:p-6">
    <flux:heading size="lg" class="!mb-2">{{ __('Set notarial fee') }}</flux:heading>
    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Enter the fee amount before creating a payment link. The full 9-field notarial register is completed after the client pays.') }}
    </p>

    @if ($attorneyRegistryDraft)
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ __('Saved fee: PHP :amount', ['amount' => number_format((float) $attorneyRegistryDraft->fees, 2)]) }}
            @if ($paymentRequired && ! $hasSettledPayment)
                <span class="block mt-1 text-xs">{{ __('Create or share the payment link below.') }}</span>
            @endif
        </div>
    @endif

    <form wire:submit="saveSettlementFee" class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end">
        <flux:field class="flex-1">
            <flux:label>{{ __('Notarial fee (PHP)') }}</flux:label>
            <flux:input wire:model="settlementFee" type="number" step="0.01" min="0" placeholder="500.00" />
            <flux:error name="settlementFee" />
        </flux:field>
        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveSettlementFee">
            <span wire:loading.remove wire:target="saveSettlementFee">{{ __('Save fee') }}</span>
            <span wire:loading wire:target="saveSettlementFee">{{ __('Saving…') }}</span>
        </flux:button>
    </form>
</div>
