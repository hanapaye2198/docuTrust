<div id="section-settlement-start" class="space-y-4">
    @if ($isNotary)
        @include('livewire.notary-requests.show.partials.settlement-checklist')

        @include('livewire.notary-requests.show.partials.settlement-sub-nav')

        @include('livewire.notary-requests.show.partials.section-settlement-fee')

        @if ($canManageLifecycle || $isEnotaryPortalSigner || $isRequester)
            @include('livewire.notary-requests.show.partials.section-payment')
        @endif

        <div id="section-attorney-registry" class="ui-panel scroll-mt-24 p-5 sm:p-6">
            <flux:heading size="lg" class="!mb-2">{{ __('Notarial register entry') }}</flux:heading>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                @if ($canAccessAttorneyRegistry)
                    {{ __('Complete the 9-field register row after payment. Enter the O.R. number and confirm signer signatures.') }}
                @elseif ($paymentRequired && ! $hasSettledPayment)
                    {{ __('Available after the client completes payment. Set the notarial fee above and collect payment first.') }}
                @else
                    {{ __('Available after you sign the document.') }}
                @endif
            </p>
            @if ($canAccessAttorneyRegistry)
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <flux:button variant="primary" :href="route('notary.attorney-registry', $notaryRequest)" wire:navigate>{{ __('Open notarial register entry') }}</flux:button>
                    @if ($attorneyRegistryDraft?->registry_fields_completed_at)
                        <flux:badge color="emerald">{{ __('Completed') }}</flux:badge>
                    @elseif ($attorneyRegistryDraft)
                        <flux:badge color="amber">{{ __('In progress') }}</flux:badge>
                    @endif
                </div>
                @if ($attorneyRegistryDraft)
                    <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                        <div class="font-medium">{{ $attorneyRegistryDraft->title }}</div>
                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Act: :act | Fee: PHP :fee | O.R.: :or', [
                                'act' => ucfirst(str_replace('_', ' ', $attorneyRegistryDraft->notarial_act_type)),
                                'fee' => number_format((float) $attorneyRegistryDraft->fees, 2),
                                'or' => $attorneyRegistryDraft->official_receipt_no ?: __('(pending)'),
                            ]) }}
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div id="section-attorney-seal" class="ui-panel scroll-mt-24 p-5 sm:p-6">
            <flux:heading size="lg" class="!mb-2">{{ __('Attorney personal seal') }}</flux:heading>
            @if ($hasAttorneySealOnFile)
                <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {{ __('Your personal seal is on file and ready for the official register entry.') }}
                </div>
            @else
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                    {{ __('Upload your personal seal in trust profile before creating the official register entry.') }}
                </div>
                <div class="mt-4">
                    <flux:button variant="outline" :href="route('settings.trust-profile').'#notary-seal'" wire:navigate>{{ __('Open trust profile') }}</flux:button>
                </div>
            @endif
        </div>

        <div id="section-register" class="ui-panel scroll-mt-24 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Official notarial register') }}</h2>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Create the official register entry from your saved draft after payment and seal are complete.') }}</p>

            @if ($canCreateRegisterEntry)
                <div class="mt-4">
                    <a
                        href="{{ route('notary.register-entry', $notaryRequest) }}"
                        class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-teal-500"
                    >
                        {{ __('Create register entry') }}
                    </a>
                </div>
            @else
                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                    {{ __('Available after you sign the document, complete the register entry (with O.R. if a fee applies), collect payment, and upload your personal seal.') }}
                </div>
            @endif

            @if ($notaryRequest->registerEntries->isNotEmpty())
                <div class="mt-5 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-[1200px] w-full border-collapse text-left text-xs">
                        <thead class="bg-zinc-50 text-zinc-600 dark:bg-zinc-900/70 dark:text-zinc-300">
                            <tr class="align-top">
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('1 Entry no.') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('2 Title and description') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('3 Names & address of parties') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('4 Names & address of witnesses') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('5 Competent evidence of identity') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('6 Date & time notarization') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('7 Type of notarial act') }}</th>
                                <th class="border-b border-r border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('8 Fees & O.R. no.') }}</th>
                                <th class="border-b border-zinc-200 px-3 py-2.5 font-semibold uppercase tracking-wide dark:border-zinc-700">{{ __('9 Notary signature') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                            @foreach ($notaryRequest->registerEntries->sortByDesc('entry_number') as $entry)
                                @php
                                    $parties = is_array($entry->parties) ? $entry->parties : [];
                                    $witnesses = is_array($entry->witnesses) ? $entry->witnesses : [];
                                    $evidenceList = is_array($entry->competent_evidence) ? $entry->competent_evidence : [];
                                @endphp
                                <tr class="align-top">
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 text-sm font-semibold dark:border-zinc-700">
                                        {{ str_pad((string) $entry->entry_number, 3, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $entry->document_title }}</div>
                                        @if ($entry->document_description)
                                            <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">{{ $entry->document_description }}</div>
                                        @endif
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        @forelse ($parties as $party)
                                            <div class="mb-1 last:mb-0">
                                                <div class="font-medium">{{ $party['name'] ?? '-' }}</div>
                                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $party['address'] ?? '-' }}</div>
                                            </div>
                                        @empty
                                            <span class="text-zinc-400">-</span>
                                        @endforelse
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        @forelse ($witnesses as $witness)
                                            <div class="mb-1 last:mb-0">
                                                <div class="font-medium">{{ $witness['name'] ?? '-' }}</div>
                                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $witness['address'] ?? '-' }}</div>
                                            </div>
                                        @empty
                                            <span class="text-zinc-400">-</span>
                                        @endforelse
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        @forelse ($evidenceList as $evidence)
                                            <div class="mb-1 rounded-md border border-zinc-200 px-2 py-1 last:mb-0 dark:border-zinc-700">
                                                <div class="font-medium">{{ $evidence['id_type'] ?? __('ID') }}</div>
                                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $evidence['id_number'] ?? '-' }}</div>
                                            </div>
                                        @empty
                                            <span class="text-zinc-400">-</span>
                                        @endforelse
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        {{ $entry->notarized_at?->timezone('Asia/Manila')->format('M d, Y g:i:s A') ?? '-' }}
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">(PHT)</div>
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 font-medium uppercase dark:border-zinc-700">
                                        {{ str_replace('_', ' ', $entry->notarial_act_type) }}
                                    </td>
                                    <td class="border-t border-r border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        <div class="font-semibold">PHP {{ number_format((float) $entry->fees, 2) }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400">OR: {{ $entry->official_receipt_number ?: '-' }}</div>
                                    </td>
                                    <td class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-700">
                                        @if ($entry->notary_signature_path)
                                            <img
                                                src="{{ \Illuminate\Support\Facades\Storage::disk((string) config('filesystems.docutrust_disk', 'local'))->url($entry->notary_signature_path) }}"
                                                alt="{{ __('Notary signature') }}"
                                                class="h-10 w-auto max-w-28 object-contain"
                                            >
                                        @else
                                            <span class="text-zinc-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div id="section-review" class="ui-panel scroll-mt-24 p-5 sm:p-6">
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
                            <flux:textarea wire:model="rejectionReason" rows="4" placeholder="{{ __('Explain why this notarization cannot proceed.') }}" />
                            <flux:error name="rejectionReason" />
                        </flux:field>
                        <div class="mt-3">
                            <flux:button variant="outline" type="button" wire:click="rejectRequest">{{ __('Reject notarization') }}</flux:button>
                            <flux:error name="rejectRequest" />
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                    {{ __('Available after the video session, attorney signing, register entry, and client payment (if required) are complete.') }}
                </div>
            @endif
        </div>

        <div id="section-digital-notarization" class="ui-panel scroll-mt-24 p-5 sm:p-6">
            <flux:heading size="lg" class="!mb-2">{{ __('Digital notarization') }}</flux:heading>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Apply the notary seal, attach QR verification, generate the certificate, and timestamp the document.') }}
            </p>
            @if ($canDigitalizeRequest)
                <div class="mt-4">
                    <flux:button variant="primary" type="button" wire:click="digitalizeRequest" wire:loading.attr="disabled" wire:target="digitalizeRequest">
                        <span wire:loading.remove wire:target="digitalizeRequest">{{ __('Apply digital notarization') }}</span>
                        <span wire:loading wire:target="digitalizeRequest">{{ __('Processing...') }}</span>
                    </flux:button>
                    <flux:error name="digitalizeRequest" />
                </div>
            @elseif ($notaryRequest->status === \App\Enums\NotaryRequestStatus::Digitalized)
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                    {{ __('Digital notarization is complete. A Notary Admin will finalize this case.') }}
                </div>
            @else
                <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                    {{ __('Complete payment, register entry, and attorney review before applying digital notarization.') }}
                </div>
            @endif
        </div>

    @else
        @include('livewire.notary-requests.show.partials.settlement-client-portal')
    @endif
</div>
