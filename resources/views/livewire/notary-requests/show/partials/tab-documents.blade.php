@php
    use App\Enums\NotaryRequestStatus;
@endphp

            <div class="ui-panel w-full p-5 sm:p-6 lg:p-7">
                <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Document') }}</h2>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('One instrument per case: signers sign, video verification, attorney signature, then the sealed instrument is ready.') }}</p>

                @if ($isNotary && is_array($signingProgress) && ($signingProgress['phase'] ?? '') === 'awaiting_attorney_signature')
                    <div class="mt-4 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 dark:border-violet-900/40 dark:bg-violet-950/30 dark:text-violet-100">
                        <div class="font-semibold">{{ __('Your turn: sign the contract') }}</div>
                        <p class="mt-1 text-violet-800/90 dark:text-violet-200/90">{{ __('Signer legitimacy was confirmed on video. Place your attorney signature, then the system will generate the final PDF, certificate, and hash.') }}</p>
                    </div>
                @elseif ($isNotary && is_array($signingProgress) && ($signingProgress['phase'] ?? '') === 'awaiting_video')
                    @php
                        $joinableVideoParties = collect($partiesForVideo ?? [])->filter(
                            fn (array $party): bool => ($party['has_signed'] ?? false)
                                && ($party['session_id'] ?? null)
                                && in_array($party['session_status'] ?? '', ['scheduled', 'in_progress'], true)
                        );
                    @endphp
                    <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-100">
                        <div class="font-semibold">{{ __('Video verification required') }}</div>
                        <p class="mt-1 text-indigo-800/90 dark:text-indigo-200/90">
                            @if ($joinableVideoParties->isNotEmpty())
                                {{ __('A signer may already be waiting in the video room. Join the call below or open the full video workspace.') }}
                            @else
                                {{ __('All signers have signed. Send video links, then complete a verification call with each party before you sign as attorney.') }}
                            @endif
                        </p>
                        @if ($joinableVideoParties->isNotEmpty())
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @foreach ($joinableVideoParties as $party)
                                    @include('livewire.notary-requests.show.partials.video-join-link', [
                                        'notaryRequest' => $notaryRequest,
                                        'sessionId' => $party['session_id'],
                                        'label' => __('Join call with :name', ['name' => $party['full_name']]),
                                    ])
                                @endforeach
                                <flux:button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    wire:click="setActiveTab('session')"
                                >
                                    {{ __('Open video workspace') }}
                                </flux:button>
                            </div>
                        @else
                            <div class="mt-3">
                                <button
                                    type="button"
                                    wire:click="openVideoSessionWorkspace"
                                    wire:loading.attr="disabled"
                                    wire:target="openVideoSessionWorkspace,sendSignerVideoInvitations,syncVideoPartiesIfReady"
                                    class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-teal-600 dark:hover:bg-teal-500"
                                >
                                    {{ __('Send video links & open workspace') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @elseif ($isNotary && is_array($signingProgress) && ($signingProgress['phase'] ?? '') === 'finalizing')
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        <div class="font-semibold">{{ __('Finalizing instrument') }}</div>
                        <p class="mt-1 text-amber-800/90 dark:text-amber-200/90">{{ __('Your signature is recorded. Generate or wait for the final PDF, completion certificate, and document hash below.') }}</p>
                    </div>
                @elseif ($isNotary && is_array($signingProgress) && ($signingProgress['phase'] ?? '') === 'document_ready')
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-100">
                        <div class="font-semibold">{{ __('Instrument ready') }}</div>
                        <p class="mt-1 text-emerald-800/90 dark:text-emerald-200/90">{{ __('Signing, video verification, and attorney signature are complete. Continue on the Settlement tab for register entry and digital notarization.') }}</p>
                    </div>
                @endif

                @if ($isNotary && is_array($signingProgress) && ($signingProgress['visible'] ?? false))
                    <div class="mt-5">
                        @include('livewire.notary-requests.show.partials.signing-progress')
                    </div>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse ($requestDocuments as $document)
                        @php
                            $artifactState = collect($finalizationReadiness['documents'])->firstWhere('document_id', $document->id);
                            $workflowState = $documentWorkflowStates[$document->id] ?? null;
                        @endphp
                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <a href="{{ route('documents.show', $document) }}" wire:navigate class="min-w-0 flex-1">
                                    <div class="truncate font-medium text-zinc-800 dark:text-zinc-200">{{ $document->title }}</div>
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
                                    @if (is_array($artifactState) && ($attorneyHasSigned ?? false))
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
                                    @elseif ($isNotary && $document->status->value === 'completed' && ! ($attorneyHasSigned ?? false) && ($signingProgress['video_verification_complete'] ?? false))
                                        <div class="mt-2 text-xs text-violet-700 dark:text-violet-300">
                                            {{ __('Final PDF, certificate, and hash are generated after you sign as attorney.') }}
                                        </div>
                                    @endif
                                </a>
                                @if ($canManageLifecycle || $isNotary)
                                    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                                        @if ($document->status->value === 'draft' && $isNotary)
                                            <flux:button class="w-full sm:w-auto" variant="outline" :href="route('notary.documents.prepare', $document)" wire:navigate>{{ __('Prepare fields') }}</flux:button>
                                            @if (is_array($workflowState) && $workflowState['can_send'])
                                                <flux:button class="w-full sm:w-auto" variant="primary" type="button" wire:click="sendLinkedDocument({{ $document->id }})" wire:confirm="{{ __('Send this document to signers for signing?') }}">{{ __('Send to signers') }}</flux:button>
                                            @endif
                                        @elseif ($document->status->value === 'pending')
                                            @php
                                                $isAttorneySigningPhase = $isNotary && $document->documentSigners->contains(fn ($s) => (int) $s->user_id === (int) auth()->id() && $s->status->value === 'pending');
                                            @endphp
                                            @if ($isAttorneySigningPhase)
                                                {{-- Attorney signing phase: show prepare/sign links --}}
                                                <a
                                                    href="{{ route('notary.documents.prepare', $document) }}"
                                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 sm:w-auto"
                                                >
                                                    {{ __('Prepare Attorney Fields') }}
                                                </a>
                                                @php
                                                    $attorneySigner = $document->documentSigners->first(fn ($s) => (int) $s->user_id === (int) auth()->id());
                                                @endphp
                                                @if ($attorneySigner && $document->signatureFields->where('signer_id', $attorneySigner->id)->isNotEmpty())
                                                    <a href="{{ route('notary.sign.account.show', $attorneySigner->id) }}"
                                                       class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                                        {{ __('Sign Document') }}
                                                    </a>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center gap-1.5 rounded-md border border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300">
                                                    <svg class="h-3 w-3 animate-pulse" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                    {{ __('Awaiting signatures') }}
                                                </span>
                                            @endif
                                        @elseif ($document->status->value === 'completed')
                                            @if ($isNotary && $canAttorneySign)
                                                @php
                                                    $attorneySigner = $document->documentSigners->first(fn ($s) => (int) $s->user_id === (int) auth()->id());
                                                    $attorneyHasSigned = $attorneySigner && $attorneySigner->status->value === 'signed';
                                                @endphp
                                                @if (! $attorneyHasSigned)
                                                    <a
                                                        href="{{ route('notary.documents.prepare', $document) }}"
                                                        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto"
                                                    >
                                                        {{ __('Prepare Attorney Fields') }}
                                                    </a>
                                                    @if ($attorneySigner)
                                                        <a href="{{ route('notary.sign.account.show', $attorneySigner->id) }}"
                                                           class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-300 dark:hover:bg-indigo-950/50 sm:w-auto">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                                            {{ __('Open signing page') }}
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                        {{ __('Attorney signed') }}
                                                    </span>
                                                @endif
                                            @endif
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
