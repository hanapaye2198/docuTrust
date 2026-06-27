@php
    use App\Enums\DocumentSignerStatus;
    use App\Enums\DocumentStatus;
    use App\Enums\SigningMethod;

    $isNotaryUser = auth()->user()?->role->value === 'notary';
    $accountRoutePrefix = $isNotaryUser ? 'notary.sign.account' : 'sign.account';

    $showFieldViewer = count($fieldsJson) > 0;

    $showFieldEditing =
        count($fieldsJson) > 0
        && $signer->document->status === DocumentStatus::Pending;

    $showFieldSigning =
        $showFieldEditing
        && $signer->status === DocumentSignerStatus::Pending;

    $showLegacySign =
        ! $documentHasSignatureFields
        && count($fieldsJson) === 0
        && $signer->status === DocumentSignerStatus::Pending
        && $signer->document->status === DocumentStatus::Pending
        && ! $signer->isRecipient();

    $showCscCredentialAuthorization =
        (bool) config('signature.pades_enabled')
        && (
            is_string($signer->remote_credential_id)
            || $signer->signingMethod() === SigningMethod::PkiCertificate
        );

    $isApprover = $signer->isApprover();
    $isRecipient = $signer->isRecipient();
    $actionVerb = $isApprover ? __('approve') : __('sign');
    $sessionHeading = $isRecipient ? __('Document participant') : ($isApprover ? __('Approve document') : __('Sign document'));
    $sessionDescription = $isApprover
        ? __('Review the details below, then approve the document when you are ready.')
        : ($isRecipient
            ? __('Recipients receive the completed document after the workflow finishes and do not take action during signing.')
            : __('Review the details below, then complete any highlighted signature fields on the document.'));
    $legacyActionPrompt = $isApprover
        ? __('Confirm to approve this document.')
        : __('Confirm to complete your signature on this document.');
    $legacyActionButton = $isApprover ? __('Approve document') : __('Sign document');

    $showAwaitingAssignedFields =
        $documentHasSignatureFields
        && count($fieldsJson) === 0
        && $signer->status === DocumentSignerStatus::Pending
        && $signer->document->status === DocumentStatus::Pending;

    $assignedFieldCount = count($fieldsJson);
    $signedFieldCount = count($signedByFieldId);
    $remainingFieldCount = max(0, $assignedFieldCount - $signedFieldCount);
    $signingProgressPercent = $assignedFieldCount > 0
        ? (int) round(($signedFieldCount / $assignedFieldCount) * 100)
        : 0;

    $showCompletedFieldsNotice =
        count($fieldsJson) > 0
        && $signedFieldCount > 0
        && ! $showFieldEditing;

    $continueProcessUrl = null;
    if (
        $isNotaryUser
        && $signer->document->notary_request_id !== null
        && (int) $signer->user_id === (int) auth()->id()
        && $signer->status === DocumentSignerStatus::Signed
    ) {
        $continueProcessUrl = route('notary.requests.show', [
            'notaryRequest' => $signer->document->notary_request_id,
            'tab' => 'closing',
        ]);
    }

    if ($documentAccessLocked) {
        $showFieldEditing = false;
        $showFieldSigning = false;
        $showLegacySign = false;
        $showAwaitingAssignedFields = false;
        $showCompletedFieldsNotice = false;
    }
@endphp

<x-layouts.guest-simple>
    <div class="flex min-h-screen flex-col gap-6 px-4 py-6 sm:px-6 lg:px-10">

        {{-- ── Top bar ── --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 shadow-sm shadow-teal-900/20">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                </span>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-teal-600 dark:text-teal-400">{{ __('DocuTrust · Secure e-signature') }}</p>
                    <h1 class="text-lg font-bold tracking-tight text-zinc-900 dark:text-white">{{ $sessionHeading }}</h1>
                </div>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white px-4 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Document') }}</p>
                <p class="mt-0.5 text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $signer->document->title }}</p>
            </div>
        </header>

        <div class="ui-signsurface overflow-hidden">
            <div class="border-b border-zinc-200/80 bg-gradient-to-r from-teal-50/60 to-white px-6 py-4 dark:border-zinc-800 dark:from-teal-950/20 dark:to-zinc-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-teal-700 dark:text-teal-400">{{ __('Signing session') }}</h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide
                        {{ $signer->status === DocumentSignerStatus::Pending
                            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300' }}">
                        <span class="h-1.5 w-1.5 rounded-full
                            {{ $signer->status === DocumentSignerStatus::Pending ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                        {{ ucfirst($signer->status->value) }}
                    </span>
                </div>
            </div>
            <div class="space-y-5 p-6 sm:p-8">
                <div
                    id="sign-feedback"
                    class="@if (! session('status') && ! session('error')) hidden @endif rounded-2xl px-4 py-3 text-sm shadow-sm @if (session('status')) border border-emerald-200/90 bg-emerald-50 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100 @elseif (session('error')) border border-red-200/90 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100 @endif"
                >
                    @if (session('status'))
                        {{ session('status') }}
                    @elseif (session('error'))
                        {{ session('error') }}
                    @endif
                </div>

                @if ($signer->document->status === DocumentStatus::Draft)
                    <p class="rounded-xl border border-amber-200/80 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                        {{ $isApprover ? __('This document has not been sent for approval yet.') : __('This document has not been sent for signature yet.') }}
                    </p>
                @endif

                @if ($showAwaitingAssignedFields)
                    <p class="rounded-xl border border-zinc-200/90 bg-zinc-50 px-4 py-3 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200">
                        {{ __('This document uses field-based signing, but no signature fields are assigned to you.') }}
                    </p>
                @endif

                @if (is_string($signingAvailabilityMessage) && $signingAvailabilityMessage !== '' && $signer->status === DocumentSignerStatus::Pending && $signer->document->status === DocumentStatus::Pending)
                    <p class="rounded-xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                        {{ $signingAvailabilityMessage }}
                    </p>
                @endif

                @if (! $documentAccessLocked && $trustAuthorizationEnabled && ($showFieldViewer || $showLegacySign))
                    <section
                        id="trust-authorization-panel"
                        class="rounded-2xl border border-sky-200/90 bg-sky-50/80 p-5 dark:border-sky-900/50 dark:bg-sky-950/30"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="space-y-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-sm font-semibold uppercase tracking-[0.15em] text-sky-900 dark:text-sky-100">
                                        {{ __('Trust authorization') }}
                                    </h3>
                                    <span
                                        id="trust-authorization-status"
                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200"
                                    >
                                        {{ $trustAuthorizationSession['status'] ?? __('Not started') }}
                                    </span>
                                </div>
                                <p id="trust-authorization-description" class="max-w-2xl text-sm leading-relaxed text-sky-900/90 dark:text-sky-100/90">
                                    {{ __('Before DocuTrust can request a cloud signature, your trust service authorization must be active.') }}
                                </p>
                                <dl class="grid gap-2 text-sm text-sky-900/80 dark:text-sky-100/80 sm:grid-cols-2">
                                    <div>
                                        <dt class="font-medium">{{ __('Credential') }}</dt>
                                        <dd id="trust-authorization-credential">{{ $signer->remote_credential_id ?: __('Not configured') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium">{{ __('Provider') }}</dt>
                                        <dd id="trust-authorization-provider">{{ $trustAuthorizationSession['provider_name'] ?? config('services.remote_signing.provider_name', 'remote_managed') }}</dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="font-medium">{{ __('Session') }}</dt>
                                        <dd id="trust-authorization-detail">
                                            {{ $trustAuthorizationSession['authorization_reference'] ?? __('No authorization session has been started yet.') }}
                                        </dd>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="font-medium">{{ __('Timing') }}</dt>
                                        <dd id="trust-authorization-timing">
                                            @if (($trustAuthorizationSession['completed_at'] ?? null) !== null)
                                                {{ __('Completed at :time', ['time' => $trustAuthorizationSession['completed_at']]) }}
                                            @elseif (($trustAuthorizationSession['expires_at'] ?? null) !== null)
                                                {{ __('Expires at :time', ['time' => $trustAuthorizationSession['expires_at']]) }}
                                            @else
                                                {{ __('No authorization timing is available yet.') }}
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="flex shrink-0 items-start">
                                <button
                                    type="button"
                                    id="trust-authorization-start"
                                    class="inline-flex items-center justify-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-sky-900/20 transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:bg-sky-300 disabled:text-sky-50 dark:shadow-none dark:disabled:bg-sky-900/50 dark:disabled:text-sky-200/70"
                                >
                                    {{ __('Start authorization') }}
                                </button>
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Signer identity card --}}
                <div class="flex items-center gap-4 rounded-xl border border-zinc-100 bg-zinc-50/60 px-4 py-3.5 dark:border-zinc-800 dark:bg-zinc-800/40">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-100 text-base font-bold uppercase text-teal-700 dark:bg-teal-900/50 dark:text-teal-300">
                        {{ mb_substr($signer->name, 0, 1) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ $signer->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $signer->email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                        {{ $isApprover ? __('Approver') : ($isRecipient ? __('Recipient') : __('Signer')) }}
                    </span>
                </div>

                @if ($signer->signed_at)
                    <div class="flex items-center gap-2 rounded-xl border border-emerald-200/70 bg-emerald-50/70 px-4 py-3 text-sm dark:border-emerald-800/40 dark:bg-emerald-950/20">
                        <svg class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <span class="text-emerald-800 dark:text-emerald-200">
                            {{ $isApprover ? __('Approved on') : __('Signed on') }}
                            <span class="font-semibold">{{ $signer->signed_at->format('M j, Y \a\t g:i A') }}</span>
                        </span>
                    </div>
                @endif

                @if (is_string($continueProcessUrl))
                    <div class="rounded-2xl border border-indigo-200/90 bg-indigo-50/80 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/25">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('Attorney signing complete') }}</p>
                                <p class="mt-1 text-xs text-indigo-800/90 dark:text-indigo-200/90">
                                    {{ __('Continue to Settlement to complete registry, payment, seal, and digital notarization.') }}
                                </p>
                            </div>
                            <a
                                href="{{ $continueProcessUrl }}"
                                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 dark:bg-indigo-600 dark:hover:bg-indigo-500"
                            >
                                {{ __('Continue process') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if ($documentAccessLocked)
            <div class="ui-signsurface p-8">
                <div class="mx-auto flex max-w-md flex-col gap-5">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Document password required') }}</h2>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('This sender protected the document with a shared password. Enter it to continue.') }}
                        </p>
                        @if (is_string($documentAccessHint) && $documentAccessHint !== '')
                            <p class="mt-3 rounded-xl border border-zinc-200/90 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200">
                                <span class="font-medium">{{ __('Hint:') }}</span> {{ $documentAccessHint }}
                            </p>
                        @endif
                    </div>

                    <form method="POST" action="{{ $authenticatedSigning
                        ? route($accountRoutePrefix . '.unlock', ['signerId' => $signer->id])
                        : route('sign.unlock', $signer->access_token ?? $signer->id) }}" class="flex flex-col gap-4">
                        @csrf
                        <label class="flex flex-col gap-2">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Password') }}</span>
                            <input
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 shadow-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                            />
                        </label>

                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-teal-600 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-teal-900/20 transition hover:bg-teal-500 dark:shadow-none">
                            {{ __('Unlock document') }}
                        </button>
                    </form>
                </div>
            </div>
        @elseif ($showFieldViewer)
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="ui-signsurface flex items-center gap-4 p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                        <svg class="h-5 w-5 text-zinc-500 dark:text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9h16.5m-16.5 6.75h16.5"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Assigned') }}</p>
                        <p id="assigned-field-count" class="mt-0.5 text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $assignedFieldCount }}</p>
                    </div>
                </div>
                <div class="ui-signsurface flex items-center gap-4 p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                        <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Completed') }}</p>
                        <p id="completed-field-count" class="mt-0.5 text-2xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ $signedFieldCount }}</p>
                    </div>
                </div>
                <div class="ui-signsurface flex items-center gap-4 p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 18.549 2.8a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Remaining') }}</p>
                        <p id="remaining-field-count" class="mt-0.5 text-2xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ $remainingFieldCount }}</p>
                    </div>
                </div>
            </div>

            @if ($showCompletedFieldsNotice)
                <p class="rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ __('Your signed fields are shown below in read-only mode.') }}
                </p>
            @endif

            <div class="ui-panel overflow-auto rounded-2xl p-4 sm:p-5">
                {{-- Toolbar row --}}
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {{-- Page navigation --}}
                    <div class="flex items-center gap-2">
                        <button type="button" id="btn-prev-page"
                            class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                            </svg>
                            {{ __('Prev') }}
                        </button>
                        <span id="page-indicator" class="min-w-[80px] text-center text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
                        <button type="button" id="btn-next-page"
                            class="inline-flex items-center gap-1 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            {{ __('Next') }}
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Progress --}}
                    <div class="flex items-center gap-3 sm:min-w-[240px]">
                        <div class="flex-1">
                            <div class="h-2.5 overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-800">
                                <div id="signing-progress-bar" class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-[width] duration-300" style="width: {{ $signingProgressPercent }}%"></div>
                            </div>
                        </div>
                        <span id="signing-progress-label" class="shrink-0 text-sm font-bold tabular-nums text-zinc-700 dark:text-zinc-300">{{ $signingProgressPercent }}%</span>
                    </div>
                </div>

                <p id="signing-progress-note" class="mb-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $showFieldEditing
                        ? ($remainingFieldCount > 0 ? __('Tap a highlighted field on the document to sign.') : __('All fields completed — you are done.'))
                        : __('Signed fields are shown in read-only mode.') }}
                </p>
                <div
                    id="pdf-shell"
                    class="relative block w-full min-h-[200px] overflow-hidden rounded-xl shadow-xl shadow-zinc-950/15 ring-1 ring-zinc-300/70 dark:shadow-black/50 dark:ring-zinc-600/80"
                >
                    <div
                        id="pdf-loading-indicator"
                        class="absolute inset-0 z-30 flex items-center justify-center bg-white/88 px-6 text-center backdrop-blur-sm dark:bg-zinc-950/88"
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        <div class="w-full max-w-xs space-y-4">
                            <div class="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-zinc-200 border-t-teal-500 dark:border-zinc-700 dark:border-t-teal-400"></div>
                            <div class="space-y-1.5">
                                <p id="pdf-loading-label" class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Loading document...') }}</p>
                                <p id="pdf-loading-progress" class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Preparing secure preview') }}</p>
                            </div>
                        </div>
                    </div>
                    <canvas id="pdf-canvas" class="block max-w-none rounded-[0.65rem] bg-white"></canvas>
                    <canvas id="fabric-canvas" class="absolute left-0 top-0 z-10 block rounded-lg"></canvas>
                </div>
            </div>

            <dialog
                id="sign-modal"
                class="w-[calc(100vw-2rem)] max-w-2xl rounded-3xl border border-zinc-200/90 bg-white p-0 shadow-2xl shadow-zinc-950/25 backdrop:bg-zinc-950/60 dark:border-zinc-700 dark:bg-zinc-900 dark:shadow-black/60"
            >
                <div class="border-b border-zinc-200/90 bg-gradient-to-r from-zinc-50 to-white px-6 py-5 dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                    <h3 id="sign-modal-title" class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Add your signature') }}</h3>
                    <p id="sign-modal-description" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose how you want to sign this field.') }}</p>
                </div>
                <div id="sign-modal-tabs" class="flex gap-1 border-b border-zinc-200/90 bg-zinc-50/80 px-3 pt-3 dark:border-zinc-700 dark:bg-zinc-950/50">
                    <button type="button" id="tab-draw" class="sign-tab rounded-t-xl px-4 py-2.5 text-sm font-semibold text-teal-700 shadow-sm ring-1 ring-zinc-200/90 dark:text-teal-300 dark:ring-zinc-600">
                        {{ __('Draw') }}
                    </button>
                    <button type="button" id="tab-upload" class="sign-tab rounded-t-xl px-4 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Upload') }}
                    </button>
                </div>
                <div class="p-6">
                    <div id="panel-draw" class="sign-panel">
                        <div
                            style="position:relative; width:100%; height:220px; overflow:hidden;"
                            class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-600"
                        >
                            <canvas id="draw-canvas" style="position:absolute;inset:0;width:100%;height:100%;" class="touch-none select-none"></canvas>
                        </div>
                        <button type="button" id="draw-clear" class="mt-3 inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div id="panel-type" class="sign-panel hidden">
                        <label id="type-input-label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="type-input">{{ __('Your name') }}</label>
                        <input
                            id="type-input"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100"
                            autocomplete="name"
                            placeholder="{{ __('Type here') }}"
                        />
                    </div>
                    <div id="panel-upload" class="sign-panel hidden">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="upload-input">{{ __('Image file') }}</label>
                        <input
                            id="upload-input"
                            type="file"
                            accept="image/png,image/jpeg"
                            class="mt-2 w-full text-sm text-zinc-600"
                        />
                    </div>
                </div>
                <form id="sign-form" method="POST" action="{{ $authenticatedSigning
                    ? route($accountRoutePrefix . '.signature.store', ['signerId' => $signer->id])
                    : route('sign.signature.store', $signer->access_token ?? $signer->id) }}" class="flex flex-col gap-3 border-t border-zinc-200/90 bg-zinc-50/40 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                    @csrf
                    <input type="hidden" name="signature_field_id" id="modal-field-id" value="" />
                    <input type="hidden" name="signature_image" id="modal-signature-image" value="" />
                    <input type="hidden" name="submitted_value" id="modal-submitted-value" value="" />
                    @if ($showCscCredentialAuthorization)
                        <div class="mb-4 mt-1">
                            <livewire:signature.csc-credential-selector
                                :document-id="$signer->document->id"
                                :signer-id="$signer->id"
                            />
                        </div>
                        <div class="mb-2">
                            <livewire:signature.trust-authorization-status
                                :document-id="$signer->document->id"
                                :signer-id="$signer->id"
                            />
                        </div>
                    @endif
                    <div class="flex justify-end gap-2">
                        <button type="button" id="modal-cancel" class="rounded-xl px-4 py-2.5 text-sm font-medium text-zinc-600 transition hover:bg-zinc-200/80 dark:text-zinc-400 dark:hover:bg-zinc-800">
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" id="modal-submit" class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-teal-900/20 transition hover:bg-teal-500 dark:shadow-none">
                            {{ __('Apply signature') }}
                        </button>
                    </div>
                </form>
            </dialog>
        @elseif ($showLegacySign)
            <div class="ui-signsurface p-8">
                <form method="POST" action="{{ $authenticatedSigning
                    ? route($accountRoutePrefix . '.store', ['signerId' => $signer->id])
                    : route('sign.store', $signer->access_token ?? $signer->id) }}" class="flex flex-col gap-4">
                    @csrf
                    <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $legacyActionPrompt }}
                    </p>
                    @if ($trustAuthorizationEnabled)
                        <p id="legacy-sign-trust-note" class="rounded-xl border border-sky-200/80 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                            {{ $trustAuthorizationSessionActive
                                ? __('Trust authorization is active. You can complete cloud signing now.')
                                : __('Start trust authorization to enable cloud signing for this document.') }}
                        </p>
                    @endif
                    @if ($showCscCredentialAuthorization)
                        <div class="mb-4 mt-1">
                            <livewire:signature.csc-credential-selector
                                :document-id="$signer->document->id"
                                :signer-id="$signer->id"
                            />
                        </div>
                        <div class="mb-2">
                            <livewire:signature.trust-authorization-status
                                :document-id="$signer->document->id"
                                :signer-id="$signer->id"
                            />
                        </div>
                    @endif
                    <flux:button
                        variant="primary"
                        type="submit"
                        id="legacy-sign-submit"
                        :disabled="$trustAuthorizationEnabled && ! $trustAuthorizationSessionActive"
                        class="w-full py-3 text-base font-semibold shadow-lg shadow-teal-900/15"
                    >
                        {{ $legacyActionButton }}
                    </flux:button>
                </form>
            </div>
        @endif

        @if ($showFieldViewer || $showLegacySign)
            <script id="sign-view-config" type="application/json">
                {!! json_encode([
                    'pdfUrl' => $showFieldViewer ? $pdfUrl : null,
                    'fieldsJson' => $fieldsJson,
                    'signedByFieldId' => $signedByFieldId,
                    'canEditFields' => $showFieldEditing,
                    'canTakeAction' => $showFieldSigning || $showLegacySign,
                    'showLegacySign' => $showLegacySign,
                    'realtime' => $signerSessionRealtime,
                    'signerName' => $signer->name,
                    'signerEmail' => $signer->email,
                    'signerStatus' => $signer->status->value,
                    'documentStatus' => $signer->document->status->value,
                    'dateLocale' => str_replace('_', '-', app()->getLocale()),
                    'trustAuthorization' => [
                        'enabled' => $trustAuthorizationEnabled,
                        'currentSession' => $trustAuthorizationSession,
                        'isActive' => $trustAuthorizationSessionActive,
                        'credentialId' => $signer->remote_credential_id,
                        'providerName' => config('services.remote_signing.provider_name', 'remote_managed'),
                        'canStart' => $showFieldEditing || $showLegacySign,
                        'startUrl' => $authenticatedSigning
                            ? route($accountRoutePrefix . '.trust.authorize', ['signerId' => $signer->id])
                            : route('sign.trust.authorize', $signer->access_token ?? $signer->id),
                        'pollUrlTemplate' => $authenticatedSigning
                            ? route($accountRoutePrefix . '.trust.authorize.poll', [
                                'signerId' => $signer->id,
                                'session' => '__SESSION__',
                            ])
                            : route('sign.trust.authorize.poll', [
                                'token' => $signer->access_token ?? $signer->id,
                                'session' => '__SESSION__',
                            ]),
                    ],
                    'messages' => [
                        'loadingDocument' => __('Loading document...'),
                        'loadingProgress' => __('Preparing secure preview'),
                        'renderingPage' => __('Rendering page :page of :total...', ['page' => '__PAGE__', 'total' => '__TOTAL__']),
                        'loadFailed' => __('Unable to load the document. Please refresh the page and try again.'),
                        'signed' => __('Signed'),
                        'fieldSaved' => __('Field saved.'),
                        'genericSaveError' => __('Unable to save your signature right now. Please try again.'),
                        'drawRequired' => __('Please draw your signature.'),
                        'textRequired' => __('Please enter the required text.'),
                        'uploadRequired' => __('Please choose an image.'),
                        'progressPending' => __('The next required field is highlighted on the page.'),
                        'progressDone' => __('All assigned fields have been completed.'),
                        'signatureModalTitle' => __('Add your signature'),
                        'signatureModalDescription' => __('Choose how you want to sign this field.'),
                        'signatureTypeLabel' => __('Your name'),
                        'signatureTypePlaceholder' => __('Type your name'),
                        'signatureSubmitLabel' => __('Apply signature'),
                        'textModalTitle' => __('Enter text'),
                        'textModalDescription' => __('Type the value that should appear in this field.'),
                        'textTypeLabel' => __('Field value'),
                        'textTypePlaceholder' => __('Enter text'),
                        'textSubmitLabel' => __('Apply text'),
                        'trustAuthorizationNotStarted' => __('Not started'),
                        'trustAuthorizationPending' => __('Pending approval'),
                        'trustAuthorizationAuthorized' => __('Authorized'),
                        'trustAuthorizationExpired' => __('Expired'),
                        'trustAuthorizationProviderMissing' => __('Remote credential is not configured for this signer.'),
                        'trustAuthorizationDescription' => __('Before DocuTrust can request a cloud signature, your trust service authorization must be active.'),
                        'trustAuthorizationStart' => __('Start authorization'),
                        'trustAuthorizationStarting' => __('Starting...'),
                        'trustAuthorizationRestart' => __('Restart authorization'),
                        'trustAuthorizationInProgress' => __('Authorization is still pending with the trust service provider.'),
                        'trustAuthorizationReady' => __('Trust authorization is active and ready for cloud signing.'),
                        'trustAuthorizationSessionMissing' => __('No authorization session has been started yet.'),
                        'trustAuthorizationTimingNone' => __('No authorization timing is available yet.'),
                        'trustAuthorizationCompletedAt' => __('Completed at'),
                        'trustAuthorizationExpiresAt' => __('Expires at'),
                        'trustAuthorizationRequired' => __('Start trust authorization before completing your assigned fields.'),
                        'legacyTrustAuthorizationRequired' => __('Start trust authorization to enable cloud signing for this document.'),
                        'legacyTrustAuthorizationReady' => __('Trust authorization is active. You can complete cloud signing now.'),
                        'returningToWorkflow' => __('Returning to the notarization workflow...'),
                    ],
                ]) !!}
            </script>
        @endif
    </div>
</x-layouts.guest-simple>
