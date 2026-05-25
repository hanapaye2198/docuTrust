                <section id="section-completed" class="order-2 rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-emerald-900 dark:text-emerald-100">{{ __('Notarized Document & Certificate') }}</h2>
                            <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ __('Notarization completed on :date', ['date' => $notaryRequest->completed_at?->timezone('Asia/Manila')->format('F j, Y g:i A') ?? '-']) }}</p>
                        </div>
                    </div>

                    {{-- Generated Artifacts --}}
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {{-- Notarized PDF --}}
                        @foreach ($requestDocuments as $document)
                            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarized PDF') }}</span>
                                </div>
                                <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                                <div class="mt-3 flex gap-2">
                                    <a href="{{ route('documents.download', $document) }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                        {{ __('Download') }}
                                    </a>
                                </div>
                            </div>
                        @endforeach

                        {{-- Notarial Certificate --}}
                        @foreach ($notaryRequest->registerEntries as $entry)
                            <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Notarial Certificate') }}</span>
                                </div>
                                <div class="mt-2 truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->document_title }}</div>
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Entry') }} #{{ str_pad($entry->entry_number, 3, '0', STR_PAD_LEFT) }} · {{ ucfirst(str_replace('_', ' ', $entry->notarial_act_type)) }}</div>
                                @if ($entry->certificate_path)
                                    <div class="mt-3">
                                        <a href="{{ route('documents.certificate.download', $entry->document_id ?? $requestDocuments->first()?->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            {{ __('Download') }}
                                        </a>
                                    </div>
                                @endif
                            </div>

                            {{-- QR Verification --}}
                            @if ($entry->qr_code_path || $entry->qr_verification_token)
                                <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z" /></svg>
                                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('QR Verification') }}</span>
                                    </div>
                                    <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Scan to verify authenticity') }}</div>
                                    @if ($entry->qr_verification_token)
                                        <div class="mt-2">
                                            <a href="{{ route('notary.verify', ['token' => $entry->qr_verification_token]) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-800 dark:bg-zinc-800 dark:text-emerald-300 dark:hover:bg-emerald-900/20">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                {{ __('Verify') }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Audit & Blockchain --}}
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        {{-- Audit Logs --}}
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" /></svg>
                                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Audit Logs') }}</span>
                            </div>
                            <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ trans_choice(':count journal entry|:count journal entries', $journalEntries->count(), ['count' => $journalEntries->count()]) }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">{{ __('Complete notarization trail recorded') }}</div>
                        </div>

                        {{-- Blockchain Reference --}}
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 dark:border-emerald-900/30 dark:bg-zinc-900">
                            <div class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
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
                                        <div class="truncate text-xs font-mono text-zinc-500 dark:text-zinc-400" title="{{ $doc->documentHash->transaction_id }}">
                                            {{ __('TX:') }} {{ \Illuminate\Support\Str::limit($doc->documentHash->transaction_id, 24) }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ __('Blockchain service was unavailable — can be retried.') }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Ready for actions --}}
                    <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-emerald-200 pt-4 dark:border-emerald-800">
                        <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">{{ __('Ready for:') }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            {{ __('Download') }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
                            {{ __('Verification') }}
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                            {{ __('Archive') }}
                        </span>
                    </div>
                </section>
