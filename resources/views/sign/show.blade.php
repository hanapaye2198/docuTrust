@php
    use App\Enums\DocumentSignerStatus;
    use App\Enums\DocumentStatus;

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
        && $signer->document->status === DocumentStatus::Pending;

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
@endphp

<x-layouts.guest-simple>
    <div class="mx-auto flex min-h-screen max-w-5xl flex-col gap-8 px-4 py-10 sm:px-6 lg:py-14">
        <header class="text-center sm:text-left">
            <div
                class="inline-flex items-center gap-2 rounded-full border border-teal-500/20 bg-teal-500/[0.07] px-3 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.2em] text-teal-800 dark:border-teal-400/25 dark:bg-teal-400/10 dark:text-teal-200"
            >
                <svg class="size-3.5 text-teal-600 dark:text-teal-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                {{ __('Secure e-signature') }}
            </div>
            <h1 class="mt-5 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">
                {{ __('Sign document') }}
            </h1>
            <p class="mt-3 text-lg font-medium text-zinc-700 dark:text-zinc-200">{{ $signer->document->title }}</p>
            <p class="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                {{ __('Review the details below, then complete any highlighted signature fields on the document.') }}
            </p>
        </header>

        <div class="ui-signsurface overflow-hidden">
            <div
                class="border-b border-zinc-200/80 bg-gradient-to-r from-zinc-50/90 to-white px-6 py-5 dark:border-zinc-800 dark:from-zinc-900/90 dark:to-zinc-900"
            >
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Signing session') }}</h2>
            </div>
            <div class="space-y-6 p-6 sm:p-8">
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
                        {{ __('This document has not been sent for signature yet.') }}
                    </p>
                @endif

                @if ($showAwaitingAssignedFields)
                    <p class="rounded-xl border border-zinc-200/90 bg-zinc-50 px-4 py-3 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-200">
                        {{ __('This document uses field-based signing, but no signature fields are assigned to you.') }}
                    </p>
                @endif

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-800/80">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Signer') }}</dt>
                        <dd class="text-right font-semibold text-zinc-900 dark:text-zinc-100">{{ $signer->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-800/80">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Email') }}</dt>
                        <dd class="text-right text-zinc-800 dark:text-zinc-200">{{ $signer->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-zinc-100 pb-3 dark:border-zinc-800/80">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                        <dd class="text-right capitalize text-zinc-800 dark:text-zinc-200">{{ $signer->status->value }}</dd>
                    </div>
                    @if ($signer->signed_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Signed at') }}</dt>
                            <dd class="text-right text-zinc-800 dark:text-zinc-200">{{ $signer->signed_at->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if ($showFieldViewer)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned fields') }}</p>
                    <p id="assigned-field-count" class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $assignedFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Fields assigned to you on this document.') }}</p>
                </div>
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
                    <p id="completed-field-count" class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700 dark:text-emerald-300">{{ $signedFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed fields stay visible and can be updated until the document is completed.') }}</p>
                </div>
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Remaining') }}</p>
                    <p id="remaining-field-count" class="mt-3 text-3xl font-semibold tracking-tight text-amber-700 dark:text-amber-300">{{ $remainingFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Complete every remaining field to finish your signing session.') }}</p>
                </div>
            </div>

            @if ($showCompletedFieldsNotice)
                <p class="rounded-xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100">
                    {{ __('Your signed fields are shown below in read-only mode.') }}
                </p>
            @endif

            <div class="ui-panel overflow-auto rounded-3xl p-5 sm:p-7">
                <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">
                            {{ __('Document') }}
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $showFieldEditing
                                ? __('Tap a highlighted field to sign. Signed fields can be updated until the document is completed.')
                                : __('Signed fields remain visible here for review. This document is read-only.') }}
                        </p>
                    </div>
                    <div class="mt-4 w-full max-w-xs sm:mt-0">
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Progress') }}</span>
                            <span id="signing-progress-label">{{ $signingProgressPercent }}%</span>
                        </div>
                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-800">
                            <div id="signing-progress-bar" class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-[width] duration-300" style="width: {{ $signingProgressPercent }}%"></div>
                        </div>
                        <p id="signing-progress-note" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $remainingFieldCount > 0 ? __('The next required field is highlighted on the page.') : __('All assigned fields have been completed.') }}
                        </p>
                    </div>
                </div>
                <div class="mb-3 flex items-center gap-2">
                    <button type="button" id="btn-prev-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Prev') }}</button>
                    <button type="button" id="btn-next-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}</button>
                    <span id="page-indicator" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
                </div>
                <div
                    id="pdf-shell"
                    class="relative inline-block min-h-[200px] min-w-[200px] overflow-hidden rounded-xl shadow-xl shadow-zinc-950/15 ring-1 ring-zinc-300/70 dark:shadow-black/50 dark:ring-zinc-600/80"
                >
                    <canvas id="pdf-canvas" class="block max-w-none rounded-[0.65rem] bg-white"></canvas>
                    <canvas id="fabric-canvas" class="absolute left-0 top-0 z-10 block rounded-lg"></canvas>
                </div>
            </div>

            <dialog
                id="sign-modal"
                class="w-[calc(100vw-2rem)] max-w-lg rounded-3xl border border-zinc-200/90 bg-white p-0 shadow-2xl shadow-zinc-950/25 backdrop:bg-zinc-950/60 dark:border-zinc-700 dark:bg-zinc-900 dark:shadow-black/60"
            >
                <div class="border-b border-zinc-200/90 bg-gradient-to-r from-zinc-50 to-white px-6 py-5 dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                    <h3 id="sign-modal-title" class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Add your signature') }}</h3>
                    <p id="sign-modal-description" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose how you want to sign this field.') }}</p>
                </div>
                <div id="sign-modal-tabs" class="flex gap-1 border-b border-zinc-200/90 bg-zinc-50/80 px-3 pt-3 dark:border-zinc-700 dark:bg-zinc-950/50">
                    <button type="button" id="tab-draw" class="sign-tab rounded-t-xl px-4 py-2.5 text-sm font-semibold text-teal-700 shadow-sm ring-1 ring-zinc-200/90 dark:text-teal-300 dark:ring-zinc-600">
                        {{ __('Draw') }}
                    </button>
                    <button type="button" id="tab-type" class="sign-tab rounded-t-xl px-4 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Type') }}
                    </button>
                    <button type="button" id="tab-upload" class="sign-tab rounded-t-xl px-4 py-2.5 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Upload') }}
                    </button>
                </div>
                <div class="p-6">
                    <div id="panel-draw" class="sign-panel">
                        <canvas id="draw-canvas" width="400" height="200" class="w-full max-w-full touch-none select-none rounded-lg border border-zinc-200 bg-white dark:border-zinc-600"></canvas>
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
                            accept="image/png,image/jpeg,image/webp"
                            class="mt-2 w-full text-sm text-zinc-600"
                        />
                    </div>
                </div>
                <form id="sign-form" method="POST" action="{{ route('sign.signature.store', $signer->access_token ?? $signer->id) }}" class="flex flex-col gap-3 border-t border-zinc-200/90 bg-zinc-50/40 px-6 py-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                    @csrf
                    <input type="hidden" name="signature_field_id" id="modal-field-id" value="" />
                    <input type="hidden" name="signature_image" id="modal-signature-image" value="" />
                    <input type="hidden" name="submitted_value" id="modal-submitted-value" value="" />
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
                <form method="POST" action="{{ route('sign.store', $signer->access_token ?? $signer->id) }}" class="flex flex-col gap-4">
                    @csrf
                    <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Confirm to complete your signature on this document.') }}
                    </p>
                    <flux:button variant="primary" type="submit" class="w-full py-3 text-base font-semibold shadow-lg shadow-teal-900/15">
                        {{ __('Sign document') }}
                    </flux:button>
                </form>
            </div>
        @endif

        @if ($showFieldViewer)
            <script id="sign-view-config" type="application/json">
                {!! json_encode([
                    'pdfUrl' => $pdfUrl,
                    'fieldsJson' => $fieldsJson,
                    'signedByFieldId' => $signedByFieldId,
                    'canEditFields' => $showFieldEditing,
                    'signerName' => $signer->name,
                    'signerEmail' => $signer->email,
                    'dateLocale' => str_replace('_', '-', app()->getLocale()),
                    'messages' => [
                        'signed' => __('Signed'),
                        'fieldSaved' => __('Field saved.'),
                        'genericSaveError' => __('Unable to save your signature right now. Please try again.'),
                        'drawRequired' => __('Please draw your signature.'),
                        'textRequired' => __('Please enter the required text.'),
                        'uploadRequired' => __('Please choose an image.'),
                        'progressPending' => __('The next required field is highlighted on the page.'),
                        'progressDone' => __('All assigned fields have been completed.'),
                        'signatureFallbackText' => __('Signature'),
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
                    ],
                ]) !!}
            </script>
        @endif
    </div>
</x-layouts.guest-simple>
