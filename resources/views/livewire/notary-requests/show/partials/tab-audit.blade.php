<div class="space-y-4">
            <section id="section-readiness" class="ui-panel p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Finalization readiness') }}</flux:heading>
                @if ($finalizationReadiness['ready'])
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                        {{ __('All linked documents have the required notarization artifacts.') }}
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        <div class="font-medium">{{ __('This notarization is not ready to finalize yet.') }}</div>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($finalizationReadiness['issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>

            <section id="section-journal" class="ui-panel p-6 sm:p-8">
                <flux:heading size="lg" class="!mb-4">{{ __('Journal') }}</flux:heading>
                <div class="mt-4 space-y-4">
                    @forelse ($journalEntries as $entry)
                        <div class="rounded-xl border border-zinc-200 bg-white p-4 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex items-center justify-between gap-4">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ str_replace('_', ' ', $entry->entry_type) }}</div>
                                <div class="text-xs text-zinc-400 dark:text-zinc-500">{{ $entry->recorded_at?->toDateTimeString() ?? '-' }}</div>
                            </div>
                            <div class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $entry->summary }}</div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No journal entries yet.') }}</div>
                    @endforelse
                </div>
            </section>
</div>
