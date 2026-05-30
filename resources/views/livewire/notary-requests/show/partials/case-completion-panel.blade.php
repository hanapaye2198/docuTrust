@php
    $variant = $variant ?? 'full';
    $primaryEntry = $notaryRequest->registerEntries->sortByDesc('entry_number')->first();
    $primaryDocument = $requestDocuments->first();
@endphp

<section id="section-completed" @class([
    'rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/20',
    'order-2' => $variant === 'compact',
])>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                <flux:icon.shield-check class="size-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
                <flux:heading size="lg" class="!mb-1 text-emerald-900 dark:text-emerald-100">
                    {{ __('Notarization complete') }}
                </flux:heading>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">
                    {{ __('Notarization completed on :date', ['date' => $notaryRequest->completed_at?->timezone('Asia/Manila')->format('F j, Y g:i A') ?? '-']) }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2">
        @foreach ($requestDocuments as $document)
            <flux:button
                variant="primary"
                icon="arrow-down-tray"
                :href="route('documents.download', $document)"
            >
                {{ $requestDocuments->count() > 1 ? __('Download :title', ['title' => \Illuminate\Support\Str::limit($document->title, 24)]) : __('Download PDF') }}
            </flux:button>
        @endforeach

        @if ($primaryEntry?->qr_verification_token)
            <flux:button
                variant="outline"
                icon="qr-code"
                :href="route('notary.verify', ['token' => $primaryEntry->qr_verification_token])"
                target="_blank"
            >
                {{ __('Verify QR') }}
            </flux:button>
        @endif

        @if ($primaryEntry?->certificate_path && $primaryDocument)
            <flux:button
                variant="outline"
                icon="document-text"
                :href="route('documents.certificate.download', $primaryEntry->document_id ?? $primaryDocument->id)"
            >
                {{ __('Certificate') }}
            </flux:button>
        @elseif ($primaryDocument)
            <flux:button
                variant="outline"
                icon="document-text"
                :href="route('documents.certificate.show', $primaryDocument)"
                wire:navigate
            >
                {{ __('Certificate') }}
            </flux:button>
        @endif
    </div>

    @if ($variant === 'full')
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($requestDocuments as $document)
                <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <flux:icon.document-text class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarized PDF') }}</span>
                    </div>
                    <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                    <div class="mt-3">
                        <flux:button size="sm" variant="outline" icon="arrow-down-tray" :href="route('documents.download', $document)">
                            {{ __('Download') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach

            @foreach ($notaryRequest->registerEntries as $entry)
                <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <flux:icon.document-text class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarial Certificate') }}</span>
                    </div>
                    <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->document_title }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Entry') }} #{{ str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT) }} · {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}
                    </div>
                    @if ($entry->certificate_path)
                        <div class="mt-3">
                            <flux:button
                                size="sm"
                                variant="outline"
                                icon="arrow-down-tray"
                                :href="route('documents.certificate.download', $entry->document_id ?? $requestDocuments->first()?->id)"
                            >
                                {{ __('Download') }}
                            </flux:button>
                        </div>
                    @endif
                </div>

                @if ($entry->qr_code_path || $entry->qr_verification_token)
                    <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                        <div class="flex items-center gap-2">
                            <flux:icon.qr-code class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('QR Verification') }}</span>
                        </div>
                        <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Scan to verify authenticity') }}</div>
                        @if ($entry->qr_verification_token)
                            <div class="mt-2">
                                <flux:button
                                    size="sm"
                                    variant="outline"
                                    icon="shield-check"
                                    :href="route('notary.verify', ['token' => $entry->qr_verification_token])"
                                    target="_blank"
                                >
                                    {{ __('Verify') }}
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <div class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.clipboard-document-list class="size-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Audit Logs') }}</span>
                </div>
                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ trans_choice(':count journal entry|:count journal entries', $journalEntries->count(), ['count' => $journalEntries->count()]) }}
                </div>
                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Complete notarization trail recorded') }}</div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                <div class="flex items-center gap-2">
                    <flux:icon.link class="size-4 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Blockchain Reference') }}</span>
                </div>
                @php
                    $anchoredDocs = $requestDocuments->filter(fn ($doc) => $doc->documentHash?->transaction_id);
                    $totalDocs = $requestDocuments->count();
                @endphp
                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ trans_choice(':count of :total document anchored|:count of :total documents anchored', $anchoredDocs->count(), ['count' => $anchoredDocs->count(), 'total' => $totalDocs]) }}
                </div>
                @if ($anchoredDocs->isNotEmpty())
                    <div class="mt-2 space-y-1">
                        @foreach ($anchoredDocs as $doc)
                            <div class="truncate font-mono text-xs text-zinc-500 dark:text-zinc-400" title="{{ $doc->documentHash->transaction_id }}">
                                {{ __('TX:') }} {{ \Illuminate\Support\Str::limit($doc->documentHash->transaction_id, 24) }}
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('Blockchain service was unavailable — can be retried.') }}</div>
                @endif
            </div>
        </div>
    @endif
</section>
