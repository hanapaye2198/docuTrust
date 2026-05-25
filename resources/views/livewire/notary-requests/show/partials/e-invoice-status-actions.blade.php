@php
    use App\Enums\EInvoiceStatus;
@endphp

@if ($latestEInvoice->status === EInvoiceStatus::Draft)
    <div class="mt-4 rounded-xl border border-current/15 bg-white/50 px-4 py-3 text-sm dark:bg-zinc-950/20">
        {{ __('The internal invoice record is ready and awaiting EIS submission setup.') }}
    </div>

    <div class="mt-4 space-y-3">
        <flux:button variant="primary" type="button" wire:click="queueLatestEInvoice">{{ __('Queue for EIS submission') }}</flux:button>
        <flux:error name="queueLatestEInvoice" />
    </div>
@elseif ($latestEInvoice->status === EInvoiceStatus::Queued)
    <div class="mt-4 rounded-xl border border-current/15 bg-white/50 px-4 py-3 text-sm dark:bg-zinc-950/20">
        {{ __('The invoice payload has been prepared and queued for background EIS submission.') }}
    </div>

    <div class="mt-4 space-y-3">
        <flux:button variant="primary" type="button" wire:click="submitLatestEInvoice">{{ __('Submit to EIS now') }}</flux:button>
        <flux:error name="submitLatestEInvoice" />
    </div>
@elseif ($latestEInvoice->status === EInvoiceStatus::Submitted || $latestEInvoice->status === EInvoiceStatus::Processing)
    <div class="mt-4 rounded-xl border border-current/15 bg-white/50 px-4 py-3 text-sm dark:bg-zinc-950/20">
        {{ __('The invoice has been sent to EIS and is awaiting a final result.') }}
    </div>

    <div class="mt-4 space-y-3">
        <flux:button variant="outline" type="button" wire:click="refreshLatestEInvoiceStatus">{{ __('Refresh EIS status') }}</flux:button>
        <flux:error name="refreshLatestEInvoiceStatus" />
    </div>
@elseif ($latestEInvoice->status === EInvoiceStatus::Accepted)
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-200">
        {{ __('The invoice has been accepted by EIS.') }}
        @if ($latestEInvoice->eis_unique_id)
            <div class="mt-2 text-xs">{{ __('EIS Unique ID') }}: <span class="font-mono">{{ $latestEInvoice->eis_unique_id }}</span></div>
        @endif
    </div>
@elseif ($latestEInvoice->status === EInvoiceStatus::Rejected)
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
        {{ $latestEInvoice->error_message ?? __('The invoice was rejected by EIS.') }}
    </div>
@elseif ($latestEInvoice->status === EInvoiceStatus::NeedsCorrection)
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
        {{ $latestEInvoice->error_message ?? __('The invoice needs billing or EIS configuration corrections before submission.') }}
    </div>

    <div class="mt-4 space-y-3">
        <flux:button variant="primary" type="button" wire:click="queueLatestEInvoice">{{ __('Retry queueing') }}</flux:button>
        <flux:error name="queueLatestEInvoice" />
    </div>
@endif
