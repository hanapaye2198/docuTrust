@php
    use App\Enums\EInvoiceStatus;
    use App\Enums\PaymentStatus;
    use App\Models\Payment;
@endphp

<div class="space-y-4">
                <div id="section-register" class="ui-panel p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-2">{{ __('Notarial register') }}</flux:heading>
                    @if ($canCreateRegisterEntry)
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Create the official notarial register entry with all 9 required fields.') }}</p>
                        <div class="mt-4">
                            <flux:button variant="primary" :href="route('notary.register-entry', $notaryRequest)" wire:navigate>{{ __('Create register entry') }}</flux:button>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Register entry creation becomes available after the attorney has signed the linked documents.') }}
                        </div>
                    @endif
                    @if ($notaryRequest->registerEntries->isNotEmpty())
                        <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            @foreach ($notaryRequest->registerEntries as $entry)
                                <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ __('Entry') }} {{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }} — {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}</div>
                                    <div class="text-zinc-500 dark:text-zinc-400">{{ $entry->document_title }}</div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->notarized_at?->timezone('Asia/Manila')->format('M j, Y g:i:s A') }} (PHT)</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            @if ($isNotary || $canManageLifecycle || $isEnotaryPortalSigner || $isRequester)
                <div id="section-payment" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-2">{{ __('Payment') }}</flux:heading>
                    @php
                        $latestRegisterEntry = $notaryRequest->registerEntries->sortByDesc('created_at')->first();
                        $paymentDue = $latestRegisterEntry ? (float) $latestRegisterEntry->fees : 0.0;
                        $currentPaymentExpired = $latestPayment instanceof Payment
                            && $latestPayment->status === PaymentStatus::Pending
                            && $latestPayment->expires_at?->isPast();
                        $displayPaymentStatus = $currentPaymentExpired ? PaymentStatus::Expired : ($latestPayment?->status ?? null);
                        $paymentBadgeColor = match ($displayPaymentStatus) {
                            PaymentStatus::Paid => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                            PaymentStatus::Pending => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
                            PaymentStatus::Failed, PaymentStatus::Expired, PaymentStatus::Cancelled => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300',
                            default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300',
                        };
                    @endphp

                    @if ($latestRegisterEntry)
                        <div class="mt-3 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/40">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('Amount due') }}</div>
                            <div class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">PHP {{ number_format($paymentDue, 2) }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Based on register entry :entry', ['entry' => str_pad((string) $latestRegisterEntry->entry_number, 3, '0', STR_PAD_LEFT)]) }}</div>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Create a notarial register entry with fees before generating a GatewayHub payment.') }}
                        </div>
                    @endif

                    @if ($paymentRequired && ! $hasSettledPayment)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-100">
                            {{ __('This request is blocked until a successful payment is recorded.') }}
                        </div>
                    @endif

                    @if ($latestPayment instanceof Payment)
                        <div class="mt-4 rounded-xl border px-4 py-4 {{ $paymentBadgeColor }}">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider">{{ __('Latest payment') }}</div>
                                    <div class="mt-1 text-sm font-medium">{{ strtoupper($latestPayment->gateway) }} · {{ $latestPayment->reference }}</div>
                                </div>
                                <span class="rounded-full border border-current/15 px-2.5 py-1 text-xs font-semibold uppercase">{{ $displayPaymentStatus?->value ?? '-' }}</span>
                            </div>
                            <div class="mt-3 space-y-1 text-xs">
                                <div>{{ __('GatewayHub Payment ID') }}: <span class="font-mono">{{ $latestPayment->provider_payment_id ?? '-' }}</span></div>
                                <div>{{ __('Created') }}: {{ $latestPayment->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                <div>{{ __('Expires') }}: {{ $latestPayment->expires_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }}{{ $currentPaymentExpired ? ' '.__('(expired)') : '' }}</div>
                                @if ($latestPayment->paid_at)
                                    <div>{{ __('Paid') }}: {{ $latestPayment->paid_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} (PHT)</div>
                                @endif
                            </div>

                            @if ($currentPaymentExpired)
                                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
                                    {{ __('This payment link has expired. Generate a new payment to continue.') }}
                                </div>
                                <div class="mt-4">
                                    <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Re-check status') }}</flux:button>
                                </div>
                            @elseif ($latestPayment->status === PaymentStatus::Pending)
                                <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_280px]">
                                    <div class="space-y-3">
                                        @if ($latestPayment->checkout_url || $latestPayment->redirect_url)
                                            <a href="{{ $latestPayment->checkout_url ?? $latestPayment->redirect_url }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-500">
                                                {{ __('Open checkout') }}
                                            </a>
                                        @endif
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wider">{{ __('QR payload') }}</div>
                                            <textarea readonly rows="5" class="mt-2 w-full rounded-xl border border-current/15 bg-white/70 px-3 py-2 text-xs font-mono text-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-100">{{ $latestPayment->qr_data }}</textarea>
                                        </div>
                                        <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Verify status from GatewayHub') }}</flux:button>
                                        <flux:error name="refreshPaymentStatus" />
                                    </div>
                                    <div class="flex items-start justify-center">
                                        @if ($latestPayment->qr_data)
                                            <img
                                                src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data={{ rawurlencode($latestPayment->qr_data) }}"
                                                alt="{{ __('GatewayHub payment QR') }}"
                                                class="w-full max-w-[280px] rounded-2xl border border-current/15 bg-white p-3"
                                            >
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mt-4">
                                    <flux:button variant="outline" type="button" wire:click="refreshPaymentStatus({{ $latestPayment->id }})">{{ __('Re-check status') }}</flux:button>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($latestRegisterEntry && $paymentDue > 0 && (! ($latestPayment instanceof Payment) || $latestPayment->status !== PaymentStatus::Paid))
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $currentPaymentExpired ? __('Generate a new GatewayHub payment') : __('Create GatewayHub payment') }}</div>
                            @if ($enabledPaymentGateways !== [])
                                <div class="mt-3 space-y-3">
                                    <flux:field>
                                        <flux:label>{{ __('Gateway') }}</flux:label>
                                        <select wire:model="paymentGateway" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                                            @foreach ($enabledPaymentGateways as $gatewayOption)
                                                <option value="{{ $gatewayOption['code'] }}">{{ $gatewayOption['name'] }}</option>
                                            @endforeach
                                        </select>
                                        <flux:error name="paymentGateway" />
                                    </flux:field>
                                    <flux:button variant="primary" type="button" wire:click="createGatewayPayment">{{ $currentPaymentExpired ? __('Generate new payment') : __('Create payment') }}</flux:button>
                                    <flux:error name="createGatewayPayment" />
                                </div>
                            @else
                                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                    {{ __('GatewayHub is not fully configured or enabled gateways could not be loaded.') }}
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($paymentHistory->count() > 1)
                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Payment history') }}</div>
                            <div class="mt-3 space-y-2">
                                @foreach ($paymentHistory->slice(1) as $historicPayment)
                                    <div class="rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ strtoupper($historicPayment->gateway) }} · {{ $historicPayment->reference }}</div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ strtoupper($historicPayment->status->value) }} · {{ $historicPayment->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($latestEInvoice)
                        @php
                            $invoiceBadgeColor = match ($latestEInvoice->status) {
                                EInvoiceStatus::Accepted => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                                EInvoiceStatus::Rejected, EInvoiceStatus::NeedsCorrection => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300',
                                EInvoiceStatus::Queued, EInvoiceStatus::Submitted, EInvoiceStatus::Processing => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300',
                                default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300',
                            };
                        @endphp

                        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('E-invoice') }}</div>
                            <div class="mt-3 rounded-xl border px-4 py-4 {{ $invoiceBadgeColor }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wider">{{ __('Latest invoice') }}</div>
                                        <div class="mt-1 text-sm font-medium">{{ $latestEInvoice->invoice_number }}</div>
                                    </div>
                                    <span class="rounded-full border border-current/15 px-2.5 py-1 text-xs font-semibold uppercase">{{ $latestEInvoice->status->value }}</span>
                                </div>
                                <div class="mt-3 space-y-1 text-xs">
                                    <div>{{ __('Amount') }}: PHP {{ number_format((float) $latestEInvoice->total_amount, 2) }}</div>
                                    <div>{{ __('Issue date') }}: {{ $latestEInvoice->issue_date?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '-' }} (PHT)</div>
                                    <div>{{ __('Document') }}: {{ $latestEInvoice->document_title ?? '-' }}</div>
                                    <div>{{ __('O.R. number') }}: {{ $latestEInvoice->official_receipt_number ?? '-' }}</div>
                                </div>

                                @if ($latestEInvoice->status === EInvoiceStatus::Draft)
                                    <div class="mt-4 rounded-xl border border-current/15 bg-white/50 px-4 py-3 text-sm dark:bg-zinc-950/20">
                                        {{ __('The internal invoice record is ready and awaiting EIS submission setup.') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

                @if ($isNotary)
                <div id="section-review" class="ui-panel scroll-mt-6 p-5 sm:p-6">
                    <flux:heading size="lg" class="!mb-4">{{ __('Attorney review') }}</flux:heading>
                    @if ($canReviewNotary)
                        <div class="mt-4 space-y-4">
                            <flux:field>
                                <flux:label>{{ __('Review summary') }}</flux:label>
                                <flux:textarea wire:model="approvalSummary" rows="4" placeholder="{{ __('Observed signer awareness, reviewed identity, and validated voluntary signing.') }}" />
                            </flux:field>
                            <flux:button variant="primary" type="button" wire:click="approveRequest">{{ __('Complete attorney review') }}</flux:button>
                            <flux:error name="approveRequest" />

                            <div class="border-t border-zinc-200 pt-4 dark:border-zinc-700">
                                <flux:field>
                                    <flux:label>{{ __('Rejection reason') }}</flux:label>
                                    <flux:textarea wire:model="rejectionReason" rows="4" placeholder="{{ __('Explain why this request cannot proceed.') }}" />
                                    <flux:error name="rejectionReason" />
                                </flux:field>
                                <div class="mt-3">
                                    <flux:button variant="outline" type="button" wire:click="rejectRequest">{{ __('Reject request') }}</flux:button>
                                    <flux:error name="rejectRequest" />
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                            {{ __('Attorney review becomes available after the video session is complete, the attorney has signed, the register entry exists, and the client payment has been completed.') }}
                        </div>
                    @endif
                </div>
                @endif
</div>
