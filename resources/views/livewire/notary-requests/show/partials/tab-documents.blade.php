@php
    use App\Enums\NotaryRequestStatus;
@endphp

            <div class="ui-panel w-full p-5 sm:p-6 lg:p-7">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Document') }}</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('One instrument per case: signers sign, video verification, attorney signature, then the sealed instrument is ready.') }}</p>

                @php
                    $attorneySignatureDocument = $requestDocuments->first();
                    $attorneySignatureSigner = $attorneySignatureDocument?->documentSigners?->first(
                        fn ($signer) => (int) $signer->user_id === (int) auth()->id()
                    );
                    $attorneySignatureFieldsReady = $attorneySignatureDocument !== null
                        && $attorneySignatureSigner !== null
                        && $attorneySignatureDocument->signatureFields
                            ->where('signer_id', $attorneySignatureSigner->id)
                            ->isNotEmpty();
                    $attorneySignatureComplete = $attorneySignatureSigner !== null
                        && $attorneySignatureSigner->status->isCompleted();
                    $showAttorneySignatureCard = $isNotary
                        && $attorneySignatureDocument !== null
                        && ($signingProgress['video_verification_complete'] ?? false);
                @endphp

                @if ($showAttorneySignatureCard)
                    <div @class([
                        'mt-5 overflow-hidden rounded-3xl border shadow-sm',
                        'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/20' => $attorneySignatureComplete,
                        'border-indigo-200 bg-indigo-50/70 dark:border-indigo-900/40 dark:bg-indigo-950/20' => ! $attorneySignatureComplete,
                    ])>
                        <div class="p-5 sm:p-6 lg:p-7">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-300">
                                            {{ __('Attorney Signature') }}
                                        </span>
                                        @if ($attorneySignatureComplete)
                                            <flux:badge color="emerald">{{ __('Signed') }}</flux:badge>
                                        @elseif ($attorneySignatureFieldsReady)
                                            <flux:badge color="indigo">{{ __('Ready to sign') }}</flux:badge>
                                        @else
                                            <flux:badge color="amber">{{ __('Needs attorney fields') }}</flux:badge>
                                        @endif
                                    </div>

                                    <h3 class="mt-3 text-xl font-bold tracking-tight text-zinc-950 dark:text-white">
                                        @if ($attorneySignatureComplete)
                                            {{ __('Attorney signed the document') }}
                                        @elseif ($attorneySignatureFieldsReady)
                                            {{ __('Now sign the document as attorney') }}
                                        @else
                                            {{ __('Now add your attorney signature fields') }}
                                        @endif
                                    </h3>

                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                                        @if ($attorneySignatureComplete)
                                            {{ __('Your attorney signature is recorded. Continue with fees, payment, register, and final notarization.') }}
                                        @elseif ($attorneySignatureFieldsReady)
                                            {{ __('Video verification is complete and your fields are ready. Sign the instrument before moving to fees and register steps.') }}
                                        @else
                                            {{ __('Video verification is complete. Place your attorney signature and seal fields on the signed instrument, then sign it.') }}
                                        @endif
                                    </p>

                                    <div class="mt-4 grid gap-2 text-xs text-zinc-600 dark:text-zinc-400 sm:grid-cols-3">
                                        <div class="rounded-2xl bg-white/70 px-3 py-2 dark:bg-zinc-950/50">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Video') }}</div>
                                            <div>{{ __('Completed') }}</div>
                                        </div>
                                        <div class="rounded-2xl bg-white/70 px-3 py-2 dark:bg-zinc-950/50">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Fields') }}</div>
                                            <div>{{ $attorneySignatureFieldsReady ? __('Ready') : __('Not placed yet') }}</div>
                                        </div>
                                        <div class="rounded-2xl bg-white/70 px-3 py-2 dark:bg-zinc-950/50">
                                            <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Signature') }}</div>
                                            <div>
                                                @if ($attorneySignatureComplete && $attorneySignatureSigner?->signed_at)
                                                    {{ $attorneySignatureSigner->signed_at->timezone(config('docutrust.notary.timezone', 'Asia/Manila'))->format('M j, Y g:i A') }}
                                                @else
                                                    {{ $attorneySignatureComplete ? __('Signed') : __('Pending') }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if (! $attorneySignatureComplete)
                                    <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto">
                                        @if ($attorneySignatureFieldsReady && $attorneySignatureSigner !== null)
                                            <a href="{{ route('notary.sign.account.show', $attorneySignatureSigner->id) }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400 sm:w-auto">
                                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25 18 3m0 0 2.25 2.25M18 3v6m-8.25 9.75h-3a2.25 2.25 0 0 1-2.25-2.25v-9A2.25 2.25 0 0 1 6.75 5.25h4.5m0 13.5 7.5-7.5"/></svg>
                                                {{ __('Sign document now') }}
                                            </a>
                                            <a href="{{ route('notary.documents.prepare', $attorneySignatureDocument) }}"
                                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 dark:border-indigo-900/40 dark:bg-zinc-900 dark:text-indigo-300 dark:hover:bg-indigo-950/40 sm:w-auto">
                                                {{ __('Adjust fields') }}
                                            </a>
                                        @else
                                            <a href="{{ route('notary.documents.prepare', $attorneySignatureDocument) }}"
                                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-400 sm:w-auto">
                                                {{ __('Prepare attorney fields') }}
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse ($requestDocuments as $document)
                        @php
                            $artifactState = collect($finalizationReadiness['documents'])->firstWhere('document_id', $document->id);
                            $workflowState = $documentWorkflowStates[$document->id] ?? null;
                            $documentProgress = collect($signingProgress['documents'] ?? [])->firstWhere('document_id', $document->id);
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
                                        @if (($artifactState['completed'] ?? false) || $document->status->value === 'completed')
                                            <flux:badge size="sm" color="emerald">{{ __('Completed document') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $document->status->value }}</div>
                                    @if (is_array($workflowState))
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count signer|:count signers', $workflowState['participant_counts']['signers'], ['count' => $workflowState['participant_counts']['signers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approver|:count approvers', $workflowState['participant_counts']['approvers'], ['count' => $workflowState['participant_counts']['approvers']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count recipient|:count recipients', $workflowState['participant_counts']['recipients'], ['count' => $workflowState['participant_counts']['recipients']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count field|:count fields', $workflowState['field_count'], ['count' => $workflowState['field_count']]) }}
                                        </div>
                                        <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ trans_choice(':count pending|:count pending', $workflowState['participant_counts']['pending'], ['count' => $workflowState['participant_counts']['pending']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count signed|:count signed', $workflowState['participant_counts']['signed'], ['count' => $workflowState['participant_counts']['signed']]) }}
                                            <span class="mx-1 text-zinc-300 dark:text-zinc-600">•</span>
                                            {{ trans_choice(':count approved|:count approved', $workflowState['participant_counts']['approved'], ['count' => $workflowState['participant_counts']['approved']]) }}
                                        </div>
                                    @endif
                                    @if (is_array($artifactState) && (($attorneyHasSigned ?? false) || ($artifactState['completed'] ?? false) || ($artifactState['has_document_hash'] ?? false)))
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_final_pdf'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Final PDF') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_certificate'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Certificate') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_document_hash'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Hash') }}</span>
                                            <span class="rounded-full px-2.5 py-1 text-[11px] font-medium {{ $artifactState['has_blockchain_transaction'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' }}">{{ __('Blockchain') }}</span>
                                        </div>
                                        @if ($artifactState['issues'] !== [])
                                            <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                                {{ implode(' ', $artifactState['issues']) }}
                                            </div>
                                        @endif
                                        @if (($artifactState['document_hash'] ?? null) !== null)
                                            <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <span class="font-semibold">{{ __('Document hash') }}</span>
                                                    @if (($artifactState['document_hash_created_at'] ?? null) !== null)
                                                        <span class="text-[11px] text-emerald-700 dark:text-emerald-300">
                                                            {{ __('Recorded :date', ['date' => $artifactState['document_hash_created_at']]) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 break-all font-mono text-[11px] leading-5">{{ $artifactState['document_hash'] }}</div>
                                                @if (($artifactState['blockchain_transaction_id'] ?? null) !== null)
                                                    <div class="mt-1 break-all text-emerald-700 dark:text-emerald-300">
                                                        {{ __('Blockchain transaction: :transaction', ['transaction' => $artifactState['blockchain_transaction_id']]) }}
                                                    </div>
                                                @else
                                                    <div class="mt-1 text-amber-700 dark:text-amber-300">
                                                        {{ __('Hash recorded. Blockchain proof is pending.') }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    @elseif ($isNotary && $document->status->value === 'completed' && ! ($attorneyHasSigned ?? false) && ($signingProgress['video_verification_complete'] ?? false))
                                        <div class="mt-2 text-xs text-violet-700 dark:text-violet-300">
                                            {{ __('Final PDF, certificate, and hash are generated after you sign as attorney.') }}
                                        </div>
                                    @endif
                                </a>
                                @if ($canManageLifecycle || $isNotary)
                                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                                        @if ($document->status->value === 'draft' && $isNotary)
                                            @php
                                                $missingFieldSigners = $document->signersMissingFields();
                                            @endphp
                                            @if ($missingFieldSigners->isNotEmpty())
                                                <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                                    {{ trans_choice(':count signer still needs signature fields.|:count signers still need signature fields.', $missingFieldSigners->count(), ['count' => $missingFieldSigners->count()]) }}
                                                </div>
                                            @elseif ($document->canSendForSigning())
                                                <div class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                                                    {{ __('All signature fields are placed. Ready to send to signers.') }}
                                                </div>
                                            @endif
                                            <flux:button class="w-full sm:w-auto" variant="outline" :href="route('notary.documents.prepare', $document)" wire:navigate>{{ __('Prepare fields') }}</flux:button>
                                            @if (is_array($workflowState) && $workflowState['can_send'])
                                                <flux:button class="w-full sm:w-auto" variant="primary" type="button" wire:click="sendLinkedDocument({{ $document->id }})" wire:confirm="{{ __('Send this document to signers for signing?') }}">{{ __('Send to signers') }}</flux:button>
                                            @endif
                                        @elseif ($document->status->value === 'pending')
                                            @php
                                                $isAttorneySigningPhase = $isNotary && $document->documentSigners->contains(fn ($s) => (int) $s->user_id === (int) auth()->id() && $s->status->value === 'pending');
                                            @endphp
                                            @if ($isAttorneySigningPhase)
                                                <span class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300">
                                                    <svg class="h-3 w-3 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                    {{ __('Client signature in progress') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300">
                                                    <svg class="h-3 w-3 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                    {{ __('Awaiting signatures') }}
                                                </span>
                                            @endif
                                        @elseif ($document->status->value === 'completed')
                                            @if ($attorneyHasSigned ?? false)
                                                @if (! ($artifactState['has_certificate'] ?? false))
                                                    <flux:button class="w-full sm:w-auto" variant="outline" type="button" wire:click="generateDocumentCertificate({{ $document->id }})">{{ __('Generate certificate') }}</flux:button>
                                                @endif
                                                @if (! ($artifactState['has_blockchain_transaction'] ?? false))
                                                    <flux:button class="w-full sm:w-auto" variant="outline" type="button" wire:click="refreshBlockchainProof({{ $document->id }})">{{ __('Refresh blockchain') }}</flux:button>
                                                @endif
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if (is_array($documentProgress) && ($documentProgress['total'] ?? 0) > 0 && in_array($document->status->value, ['pending', 'completed'], true))
                                <div
                                    class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/50"
                                    wire:poll.5s="refreshSigningStatus"
                                    wire:key="document-signer-status-{{ $document->id }}"
                                    data-document-signer-status
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signer status') }}</span>
                                                @if (($documentProgress['completed'] ?? 0) >= ($documentProgress['total'] ?? 0))
                                                    <flux:badge size="sm" color="emerald">{{ __('All signers completed') }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="amber">{{ __('Waiting for signatures') }}</flux:badge>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ trans_choice(':count of :total signer complete|:count of :total signers complete', (int) ($documentProgress['total'] ?? 0), [
                                                    'count' => (int) ($documentProgress['completed'] ?? 0),
                                                    'total' => (int) ($documentProgress['total'] ?? 0),
                                                ]) }}
                                                @if ($documentProgress['is_sequential'] ?? false)
                                                    <span class="mx-1 text-zinc-300 dark:text-zinc-700">•</span>
                                                    {{ __('Sequential') }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="w-full sm:max-w-48">
                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                <span class="font-medium text-zinc-500 dark:text-zinc-400">{{ __('Progress') }}</span>
                                                <span class="font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ (int) ($documentProgress['percent'] ?? 0) }}%</span>
                                            </div>
                                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                                <div class="h-full rounded-full bg-sky-500 transition-all duration-500" style="width: {{ max(0, min(100, (int) ($documentProgress['percent'] ?? 0))) }}%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid gap-2">
                                        @foreach ($documentProgress['signers'] as $signerProgress)
                                            <div
                                                class="flex flex-col gap-2 rounded-xl border border-white bg-white px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900 sm:flex-row sm:items-center sm:justify-between"
                                                wire:key="document-signer-status-{{ $document->id }}-{{ $signerProgress['signer_id'] }}"
                                            >
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span @class([
                                                            'inline-flex size-5 items-center justify-center rounded-full text-[10px] font-bold',
                                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' => $signerProgress['is_completed'],
                                                            'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' => ! $signerProgress['is_completed'],
                                                        ])>
                                                            @if ($signerProgress['is_completed'])
                                                                <flux:icon.check variant="mini" class="size-3" />
                                                            @else
                                                                {{ $signerProgress['signing_order'] ?? '·' }}
                                                            @endif
                                                        </span>
                                                        <span class="truncate font-semibold text-zinc-900 dark:text-zinc-100">{{ $signerProgress['name'] }}</span>
                                                        <flux:badge size="sm" :color="$signerProgress['is_completed'] ? 'emerald' : 'amber'">
                                                            {{ $signerProgress['status_label'] }}
                                                        </flux:badge>
                                                    </div>
                                                    <div class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signerProgress['email'] }}</div>
                                                </div>
                                                <div class="text-xs font-medium">
                                                    @if ($signerProgress['completed_at'])
                                                        <span class="text-emerald-700 dark:text-emerald-300">{{ __('Completed :date', ['date' => $signerProgress['completed_at']]) }}</span>
                                                    @elseif (is_string($signerProgress['waiting_label'] ?? null) && $signerProgress['waiting_label'] !== '')
                                                        <span class="text-amber-700 dark:text-amber-300">{{ $signerProgress['waiting_label'] }}</span>
                                                    @else
                                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Waiting') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (($signingProgress['phase'] ?? '') === 'awaiting_video')
                                        <div class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-200">
                                            <div class="font-semibold">{{ __('Signer signatures are complete.') }}</div>
                                            <div class="mt-1">{{ __('Next step: go to Signers & video for video verification.') }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if (app()->environment('local') && in_array($document->status->value, ['pending', 'completed'], true))
                                @php
                                    $localTestingSigners = $document->documentSigners->filter(
                                        fn ($signer) => $signer->requiresAction() && is_string($signer->access_token) && $signer->access_token !== ''
                                    );
                                    $videoService = app(\App\Services\NotarySignerVideoInvitationService::class);
                                @endphp
                                @if ($localTestingSigners->isNotEmpty())
                                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-3 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                                        <div class="font-semibold">{{ __('Local testing links') }}</div>
                                        <div class="mt-1 text-[11px] opacity-80">{{ __('Shortcuts for signing and video verification during local development.') }}</div>
                                        <div class="mt-2 flex flex-col gap-2">
                                            @foreach ($localTestingSigners as $signer)
                                                @php
                                                    $requestSigner = $notaryRequest->signers->first(
                                                        fn ($party) => strtolower(trim((string) $party->email)) === strtolower(trim((string) $signer->email))
                                                    );
                                                    $videoSession = $requestSigner
                                                        ? $signerVideoSessions->first(fn ($session) => (int) $session->notary_signer_id === (int) $requestSigner->id)
                                                        : null;
                                                @endphp
                                                <div class="rounded-md border border-amber-300 bg-white px-3 py-2 dark:border-amber-800 dark:bg-zinc-900">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-[11px] font-semibold text-amber-900 dark:text-amber-100">{{ $signer->name }}</span>
                                                        <flux:badge size="sm" :color="$signer->status->isCompleted() ? 'emerald' : 'amber'">
                                                            {{ $signer->status->isCompleted() ? __('Signed') : __('Pending') }}
                                                        </flux:badge>
                                                    </div>
                                                    <div class="mt-2 flex flex-col gap-1.5">
                                                        <a href="{{ app(\App\Services\SigningMethodService::class)->signerEntryUrl($signer) }}"
                                                           target="_blank"
                                                           class="text-[11px] font-medium text-amber-800 underline decoration-amber-400/60 underline-offset-2 dark:text-amber-200">
                                                            {{ __('Signing link') }}
                                                        </a>
                                                        @if ($videoSession !== null)
                                                            <a href="{{ $videoService->signerVideoJoinUrl($videoSession) }}"
                                                               target="_blank"
                                                               class="text-[11px] font-medium text-indigo-700 underline decoration-indigo-400/60 underline-offset-2 dark:text-indigo-300">
                                                                {{ __('Video link') }}
                                                                @if ($videoSession->invitation_sent_at)
                                                                    · {{ __('sent') }}
                                                                @endif
                                                            </a>
                                                        @elseif ($signer->status->isCompleted() && ($signingProgress['all_client_signatures_complete'] ?? false))
                                                            <span class="text-[10px] text-amber-700 dark:text-amber-300">{{ __('Video link pending') }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                            @if ($canManageLifecycle)
                                @error('sendDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if ($canManageLifecycle)
                                @error('artifactDocument'.$document->id)
                                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                                        {{ $message }}
                                    </div>
                                @enderror
                            @endif
                            @if (is_array($workflowState) && $document->status->value === 'draft' && $workflowState['missing_signers'] !== [])
                                <div class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Missing fields for: :signers', ['signers' => implode(', ', $workflowState['missing_signers'])]) }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && is_string($workflowState['blocking_reason']) && $workflowState['blocking_reason'] !== '' && $document->status->value === 'draft')
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ $workflowState['blocking_reason'] }}
                                </div>
                            @endif
                            @if (is_array($workflowState) && $workflowState['account_link_blockers'] !== [])
                                <div class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                    {{ __('Account-linked signer setup is incomplete for: :signers', ['signers' => implode(', ', $workflowState['account_link_blockers'])]) }}
                                </div>
                            @endif
                            @if ($canManageLifecycle && $notaryRequest->status === NotaryRequestStatus::Draft && $document->status->value === 'draft')
                                <div class="mt-3 flex flex-col gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-800/40">
                                    <input
                                        type="file"
                                        wire:model="replaceDocumentFile"
                                        accept="application/pdf,.pdf"
                                        class="w-full flex-1 text-xs text-zinc-600 dark:text-zinc-400"
                                    />
                                    <flux:button class="w-full sm:w-auto" variant="outline" size="sm" type="button" wire:click="replaceDocument({{ $document->id }})" wire:loading.attr="disabled" wire:target="replaceDocumentFile">{{ __('Replace') }}</flux:button>
                                </div>
                                <div wire:loading wire:target="replaceDocumentFile" class="mt-1 text-xs text-teal-600 dark:text-teal-400">{{ __('Uploading...') }}</div>
                                <flux:error name="replaceDocumentFile" />
                            @endif
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">{{ __('No documents linked yet.') }}</div>
                    @endforelse
                </div>

                {{-- Upload Document Form (Attorney / Admin) --}}
                @if (($isNotary || $canManageLifecycle) && ($canUploadAnotherDocument ?? true) && ! in_array($notaryRequest->status->value, ['digitalized', 'notarized', 'rejected', 'failed', 'cancelled'], true))
                    <div
                        class="mt-5 rounded-xl border border-teal-200 bg-teal-50/40 p-4 dark:border-teal-900/40 dark:bg-teal-950/20"
                        x-data="{ progress: 0 }"
                        x-on:livewire-upload-start="progress = 0"
                        x-on:livewire-upload-finish="progress = 0"
                        x-on:livewire-upload-error="progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                    >
                        <div class="text-sm font-semibold text-teal-900 dark:text-teal-100">{{ __('Upload document for this case') }}</div>
                        <p class="mt-1 text-xs text-teal-800/90 dark:text-teal-200/90">{{ __('Choose a PDF, then click Upload document.') }}</p>
                        <div class="mt-3 space-y-3">
                            <flux:field>
                                <flux:label>{{ __('Document title') }} <span class="text-rose-500">*</span></flux:label>
                                <flux:input wire:model="newDocumentTitle" type="text" placeholder="{{ __('e.g. Contract of sale') }}" />
                                <flux:error name="newDocumentTitle" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('PDF file') }} <span class="text-rose-500">*</span></flux:label>
                                <div>
                                    <flux:button type="button" variant="primary" icon="arrow-up-tray" x-on:click="$refs.newDocumentPdf.click()">{{ __('Choose PDF') }}</flux:button>
                                    <input x-ref="newDocumentPdf" type="file" wire:model="newDocumentFile" accept="application/pdf,.pdf" class="sr-only" />
                                </div>
                                <flux:error name="newDocumentFile" />
                            </flux:field>
                            <div wire:loading wire:target="newDocumentFile" class="space-y-2">
                                <p class="text-sm font-semibold text-teal-800 dark:text-teal-200">
                                    <span x-text="progress > 0 ? '{{ __('Uploading') }} ' + progress + '%' : '{{ __('Uploading...') }}'"></span>
                                </p>
                                <div class="h-2.5 overflow-hidden rounded-full bg-teal-100 dark:bg-teal-950/50">
                                    <div class="h-full rounded-full bg-teal-600 transition-all duration-300" :style="'width: ' + Math.max(progress, 8) + '%'"></div>
                                </div>
                            </div>
                            <flux:button variant="primary" type="button" icon="cloud-arrow-up" wire:click="createDocument" wire:loading.attr="disabled" wire:target="newDocumentFile,createDocument">{{ __('Upload document') }}</flux:button>
                        </div>
                    </div>
                @elseif (($isNotary || $canManageLifecycle) && ! ($canUploadAnotherDocument ?? true) && $requestDocuments->isNotEmpty())
                    <p class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('This case already has its document. Continue with prepare, send, and video verification on the instrument above.') }}
                    </p>
                @endif

                @if (
                    $isNotary
                    && ($usesPerSignerVideo ?? false)
                    && ($signingProgress['all_client_signatures_complete'] ?? false)
                    && ! ($signingProgress['video_verification_complete'] ?? false)
                )
                    @php
                        $hasJoinableVideoSessions = collect($partiesForVideo ?? [])->contains(
                            fn (array $party): bool => ($party['has_signed'] ?? false)
                                && ($party['session_id'] ?? null)
                                && in_array($party['session_status'] ?? '', ['scheduled', 'in_progress'], true)
                        );
                    @endphp
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if ($hasJoinableVideoSessions)
                            <flux:button
                                variant="outline"
                                type="button"
                                wire:click="sendSignerVideoInvitations(true)"
                                wire:loading.attr="disabled"
                                wire:target="sendSignerVideoInvitations,syncVideoPartiesIfReady"
                            >
                                {{ __('Resend video links by email') }}
                            </flux:button>
                        @else
                            <flux:button
                                variant="primary"
                                type="button"
                                wire:click="sendSignerVideoInvitations"
                                wire:loading.attr="disabled"
                                wire:target="sendSignerVideoInvitations,syncVideoPartiesIfReady"
                            >
                                {{ __('Send video links to signers') }}
                            </flux:button>
                        @endif
                        @if ($hasJoinableVideoSessions)
                            <flux:button
                                variant="ghost"
                                size="sm"
                                type="button"
                                wire:click="setActiveTab('session')"
                            >
                                {{ __('Open video workspace') }}
                            </flux:button>
                        @endif
                        <flux:error name="sendSignerVideoInvitations" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Each signer receives a personal video link by email. Links are sent automatically when signing finishes if not already sent.') }}
                        </p>
                    </div>
                @endif
            </div>
