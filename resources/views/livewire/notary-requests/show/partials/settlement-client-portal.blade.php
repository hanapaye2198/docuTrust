@php
    use App\Enums\NotaryRequestStatus;
@endphp

<div class="space-y-4">
    <div class="ui-panel p-5 sm:p-6">
        <flux:heading size="lg" class="!mb-2">{{ __('Payment & closing status') }}</flux:heading>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Track payment and closing progress. Your attorney handles register entry and digital notarization.') }}
        </p>

        @include('livewire.notary-requests.show.partials.client-portal-timeline')
    </div>

    @if ($paymentRequired && ! $hasSettledPayment && $hasSettlementFeeConfigured)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Payment required') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your attorney has set a notarial fee. Complete payment below to continue.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($hasSettledPayment && $notaryRequest->status !== NotaryRequestStatus::Notarized)
        <flux:callout variant="info" icon="information-circle">
            <flux:callout.heading>{{ __('Payment received') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your attorney is completing the register entry and digital notarization.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($canPayNotaryFee || $isRequester)
        @include('livewire.notary-requests.show.partials.section-payment')
    @endif

    @include('livewire.notary-requests.show.partials.settlement-scroll')
</div>
