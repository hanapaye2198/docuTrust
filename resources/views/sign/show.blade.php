@php
    use App\Enums\DocumentSignerStatus;
    use App\Enums\DocumentStatus;

    $showFieldSigning =
        count($fieldsJson) > 0
        && $signer->status === DocumentSignerStatus::Pending
        && $signer->document->status === DocumentStatus::Pending;

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
            @if (session('status'))
                <div
                    class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100"
                >
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div
                    class="rounded-2xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"
                >
                    {{ session('error') }}
                </div>
            @endif

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

        @if ($showFieldSigning)
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Assigned fields') }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ $assignedFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Fields assigned to you on this document.') }}</p>
                </div>
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Completed') }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-emerald-700 dark:text-emerald-300">{{ $signedFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Completed fields are locked immediately after signing.') }}</p>
                </div>
                <div class="ui-signsurface p-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">{{ __('Remaining') }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-amber-700 dark:text-amber-300">{{ $remainingFieldCount }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Complete every remaining field to finish your signing session.') }}</p>
                </div>
            </div>

            <div class="ui-panel overflow-auto rounded-3xl p-5 sm:p-7">
                <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">
                            {{ __('Document') }}
                        </h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Tap a highlighted field to sign. Signed fields cannot be changed.') }}
                        </p>
                    </div>
                    <div class="mt-4 w-full max-w-xs sm:mt-0">
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.15em] text-zinc-500 dark:text-zinc-400">
                            <span>{{ __('Progress') }}</span>
                            <span>{{ $signingProgressPercent }}%</span>
                        </div>
                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-zinc-200/80 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-[width] duration-300" style="width: {{ $signingProgressPercent }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
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
                class="w-[calc(100vw-2rem)] max-w-lg rounded-3xl border border-zinc-200/90 bg-white p-0 shadow-2xl shadow-zinc-950/25 backdrop:bg-zinc-950/60 open:backdrop:backdrop-blur-sm dark:border-zinc-700 dark:bg-zinc-900 dark:shadow-black/60"
            >
                <div class="border-b border-zinc-200/90 bg-gradient-to-r from-zinc-50 to-white px-6 py-5 dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-900">
                    <h3 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">{{ __('Add your signature') }}</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose how you want to sign this field.') }}</p>
                </div>
                <div class="flex gap-1 border-b border-zinc-200/90 bg-zinc-50/80 px-3 pt-3 dark:border-zinc-700 dark:bg-zinc-950/50">
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
                        <canvas id="draw-canvas" width="400" height="200" class="w-full max-w-full rounded-lg border border-zinc-200 bg-white dark:border-zinc-600"></canvas>
                        <button type="button" id="draw-clear" class="mt-3 text-sm font-medium text-zinc-600 underline dark:text-zinc-400">
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div id="panel-type" class="sign-panel hidden">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300" for="type-input">{{ __('Your name') }}</label>
                        <input
                            id="type-input"
                            type="text"
                            class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-950 dark:text-zinc-100"
                            autocomplete="name"
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
        @if ($showFieldSigning)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" crossorigin="anonymous"></script>
        <script>
            (function () {
                const pdfUrl = @json($pdfUrl);
                const fieldsJson = @json($fieldsJson);
                const signedByFieldId = @json($signedByFieldId);
                const signerName = @json($signer->name);
                const signerEmail = @json($signer->email);
                const dateLocale = @json(str_replace('_', '-', app()->getLocale()));
                const pdfCanvas = document.getElementById('pdf-canvas');
                const fabricEl = document.getElementById('fabric-canvas');
                const pdfShell = document.getElementById('pdf-shell');
                const modal = document.getElementById('sign-modal');
                const modalFieldId = document.getElementById('modal-field-id');
                const modalSignatureImage = document.getElementById('modal-signature-image');
                const signForm = document.getElementById('sign-form');
                const pageIndicator = document.getElementById('page-indicator');
                const btnPrevPage = document.getElementById('btn-prev-page');
                const btnNextPage = document.getElementById('btn-next-page');
                let fabricCanvas = null;
                let drawCanvas = null;
                let isSubmitting = false;
                let pdfDoc = null;
                let currentPage = 1;
                let totalPages = 1;
                let isRenderingPage = false;
                const renderScale = 1.5;

                if (!pdfCanvas || !fabricEl || !pdfShell || !modal || !signForm || typeof pdfjsLib === 'undefined' || typeof fabric === 'undefined') {
                    console.error('Signing view failed to initialize required PDF/signing dependencies.');
                    return;
                }

                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                function isSigned(fieldId) {
                    return Object.prototype.hasOwnProperty.call(signedByFieldId, String(fieldId));
                }

                function orderedFields() {
                    return [...fieldsJson].sort(function (a, b) {
                        const pageA = Number(a.page_number) > 0 ? Number(a.page_number) : 1;
                        const pageB = Number(b.page_number) > 0 ? Number(b.page_number) : 1;
                        if (pageA !== pageB) {
                            return pageA - pageB;
                        }
                        if (a.position_data.y !== b.position_data.y) {
                            return a.position_data.y - b.position_data.y;
                        }
                        return a.position_data.x - b.position_data.x;
                    });
                }

                function firstUnsignedField() {
                    return orderedFields().find(function (field) {
                        return !isSigned(field.id);
                    }) || null;
                }

                function initialPageNumber() {
                    const nextField = firstUnsignedField();
                    if (!nextField) {
                        return 1;
                    }
                    return Number(nextField.page_number) > 0 ? Number(nextField.page_number) : 1;
                }

                /** position_data uses 0–1 coordinates relative to each PDF page; multiply by current canvas size at render time. */
                function rectFromNormalized(position, cw, ch) {
                    return {
                        left: position.x * cw,
                        top: position.y * ch,
                        width: position.width * cw,
                        height: position.height * ch,
                    };
                }

                function getFieldChrome(type) {
                    switch (type) {
                        case 'signature_left':
                            return {
                                kind: 'signature',
                                stroke: '#0f766e',
                                fill: 'rgba(20, 184, 166, 0.12)',
                                fillText: '#115e59',
                                label: 'Signature',
                            };
                        case 'signature_right':
                            return {
                                kind: 'signature',
                                stroke: '#0369a1',
                                fill: 'rgba(14, 165, 233, 0.12)',
                                fillText: '#075985',
                                label: 'Signature',
                            };
                        case 'text':
                            return {
                                kind: 'input',
                                stroke: '#ca8a04',
                                fill: 'rgba(234, 179, 8, 0.15)',
                                fillText: '#a16207',
                                label: 'Text field',
                            };
                        case 'name':
                            return {
                                kind: 'input',
                                stroke: '#15803d',
                                fill: 'rgba(34, 197, 94, 0.15)',
                                fillText: '#15803d',
                                label: 'Name',
                            };
                        case 'date':
                            return {
                                kind: 'input',
                                stroke: '#6d28d9',
                                fill: 'rgba(139, 92, 246, 0.12)',
                                fillText: '#5b21b6',
                                label: 'Date',
                            };
                        case 'email':
                            return {
                                kind: 'input',
                                stroke: '#be123c',
                                fill: 'rgba(244, 63, 94, 0.10)',
                                fillText: '#9f1239',
                                label: 'Email',
                            };
                        case 'initials':
                            return {
                                kind: 'input',
                                stroke: '#a21caf',
                                fill: 'rgba(217, 70, 239, 0.10)',
                                fillText: '#86198f',
                                label: 'Initials',
                            };
                        case 'checkbox':
                            return {
                                kind: 'toggle',
                                control: 'square',
                                stroke: '#0284c7',
                                fill: 'rgba(56, 189, 248, 0.14)',
                                fillText: '#0369a1',
                                label: 'Checkbox',
                            };
                        case 'radio':
                            return {
                                kind: 'toggle',
                                control: 'circle',
                                stroke: '#4f46e5',
                                fill: 'rgba(99, 102, 241, 0.12)',
                                fillText: '#4338ca',
                                label: 'Radio',
                            };
                        default:
                            return {
                                kind: 'signature',
                                stroke: '#2563eb',
                                fill: 'rgba(59, 130, 246, 0.12)',
                                fillText: '#1d4ed8',
                                label: 'Signature',
                            };
                    }
                }

                function clamp(value, min, max) {
                    return Math.max(min, Math.min(max, value));
                }

                function truncateFieldText(value, width, fontSize) {
                    const text = String(value || '').trim();
                    const maxCharacters = Math.max(4, Math.floor(width / Math.max(5.5, fontSize * 0.58)));

                    if (text.length <= maxCharacters) {
                        return text;
                    }

                    return text.slice(0, Math.max(1, maxCharacters - 1)).trimEnd() + '…';
                }

                function buildFieldPreviewGroup(chrome, rect) {
                    const nodes = [];
                    const inset = Math.max(6, rect.height * 0.16);

                    if (chrome.kind === 'toggle') {
                        const controlSize = clamp(rect.height * 0.5, 14, 20);
                        const controlTop = (rect.height - controlSize) / 2;
                        const labelLeft = inset + controlSize + Math.max(7, rect.width * 0.05);
                        const labelFontSize = clamp(rect.height * 0.3, 10, 14);
                        const usableWidth = Math.max(18, rect.width - labelLeft - inset);

                        nodes.push(new fabric.Rect({
                            width: rect.width,
                            height: rect.height,
                            fill: chrome.fill,
                            stroke: chrome.stroke,
                            strokeWidth: 1.25,
                            rx: 8,
                            ry: 8,
                        }));

                        if (chrome.control === 'circle') {
                            nodes.push(new fabric.Circle({
                                radius: controlSize / 2,
                                left: inset,
                                top: controlTop,
                                fill: '#ffffff',
                                stroke: chrome.stroke,
                                strokeWidth: 1.5,
                                originX: 'left',
                                originY: 'top',
                            }));
                        } else {
                            nodes.push(new fabric.Rect({
                                width: controlSize,
                                height: controlSize,
                                left: inset,
                                top: controlTop,
                                fill: '#ffffff',
                                stroke: chrome.stroke,
                                strokeWidth: 1.5,
                                rx: 4,
                                ry: 4,
                                originX: 'left',
                                originY: 'top',
                            }));
                        }

                        nodes.push(new fabric.Text(truncateFieldText(chrome.label, usableWidth, labelFontSize), {
                            fontSize: labelFontSize,
                            fill: chrome.fillText,
                            fontFamily: 'system-ui, sans-serif',
                            fontWeight: 700,
                            originX: 'left',
                            originY: 'center',
                            left: labelLeft,
                            top: rect.height / 2,
                        }));
                    } else if (chrome.kind === 'input') {
                        const accentWidth = clamp(rect.width * 0.055, 8, 14);
                        const labelLeft = accentWidth + Math.max(10, rect.width * 0.05);
                        const labelFontSize = clamp(rect.height * 0.28, 10, 15);
                        const lineY = rect.height - Math.max(7, rect.height * 0.18);
                        const usableWidth = Math.max(24, rect.width - labelLeft - inset);

                        nodes.push(new fabric.Rect({
                            width: rect.width,
                            height: rect.height,
                            fill: chrome.fill,
                            stroke: chrome.stroke,
                            strokeWidth: 1.5,
                            rx: 8,
                            ry: 8,
                        }));
                        nodes.push(new fabric.Rect({
                            width: accentWidth,
                            height: rect.height,
                            fill: chrome.stroke,
                            rx: 8,
                            ry: 8,
                            left: 0,
                            top: 0,
                            originX: 'left',
                            originY: 'top',
                        }));
                        nodes.push(new fabric.Text(truncateFieldText(chrome.label, usableWidth, labelFontSize), {
                            fontSize: labelFontSize,
                            fill: chrome.fillText,
                            fontFamily: 'system-ui, sans-serif',
                            fontWeight: 700,
                            originX: 'left',
                            originY: 'top',
                            left: labelLeft,
                            top: inset,
                        }));
                        nodes.push(new fabric.Line([labelLeft, lineY, rect.width - inset, lineY], {
                            stroke: chrome.stroke,
                            strokeWidth: 1,
                            selectable: false,
                            evented: false,
                            opacity: 0.4,
                        }));
                    } else {
                        const accentWidth = clamp(rect.width * 0.07, 10, 18);
                        const labelLeft = accentWidth + Math.max(10, rect.width * 0.05);
                        const labelFontSize = clamp(rect.height * 0.28, 11, 16);
                        const lineY = rect.height - Math.max(7, rect.height * 0.14);
                        const usableWidth = Math.max(24, rect.width - labelLeft - inset);

                        nodes.push(new fabric.Rect({
                            width: rect.width,
                            height: rect.height,
                            fill: chrome.fill,
                            stroke: chrome.stroke,
                            strokeWidth: 1.5,
                            rx: 8,
                            ry: 8,
                        }));
                        nodes.push(new fabric.Rect({
                            width: accentWidth,
                            height: rect.height,
                            fill: chrome.stroke,
                            rx: 8,
                            ry: 8,
                            left: 0,
                            top: 0,
                            originX: 'left',
                            originY: 'top',
                        }));
                        nodes.push(new fabric.Text(truncateFieldText(chrome.label, usableWidth, labelFontSize), {
                            fontSize: labelFontSize,
                            fill: chrome.fillText,
                            fontFamily: 'system-ui, sans-serif',
                            fontWeight: 700,
                            originX: 'left',
                            originY: 'top',
                            left: labelLeft,
                            top: inset,
                        }));
                        nodes.push(new fabric.Line([labelLeft, lineY, rect.width - inset, lineY], {
                            stroke: chrome.stroke,
                            strokeWidth: 1,
                            selectable: false,
                            evented: false,
                            opacity: 0.65,
                        }));
                    }

                    return new fabric.Group(nodes, {
                        left: rect.left,
                        top: rect.top,
                        subTargetCheck: true,
                    });
                }

                function signerInitialsValue() {
                    return signerName
                        .split(/\s+/)
                        .filter(Boolean)
                        .slice(0, 2)
                        .map(function (part) {
                            return part.charAt(0).toUpperCase();
                        })
                        .join('') || 'S';
                }

                function updatePageUi() {
                    if (pageIndicator) {
                        pageIndicator.textContent = `Page ${currentPage} / ${totalPages}`;
                    }
                    if (btnPrevPage) {
                        btnPrevPage.disabled = currentPage <= 1 || isRenderingPage;
                    }
                    if (btnNextPage) {
                        btnNextPage.disabled = currentPage >= totalPages || isRenderingPage;
                    }
                }

                function fieldsForCurrentPage() {
                    return fieldsJson.filter(function (field) {
                        const pageNumber = Number(field.page_number) > 0 ? Number(field.page_number) : 1;
                        return pageNumber === currentPage;
                    });
                }

                function renderPageFields(cw, ch) {
                    fabricCanvas.clear();
                    const nextField = firstUnsignedField();
                    const nextFieldId = nextField?.id ?? null;
                    fieldsForCurrentPage().forEach(function (field) {
                        const r = rectFromNormalized(field.position_data, cw, ch);
                        if (isSigned(field.id)) {
                            const url = signedByFieldId[String(field.id)];
                            fabric.Image.fromURL(
                                url,
                                function (img) {
                                    const scaleImg = Math.min(r.width / img.width, r.height / img.height);
                                    img.set({
                                        left: r.left,
                                        top: r.top,
                                        scaleX: scaleImg,
                                        scaleY: scaleImg,
                                        selectable: false,
                                        evented: false,
                                        hasControls: false,
                                        hoverCursor: 'default',
                                        opacity: 0.98,
                                    });
                                    const badge = new fabric.Text(@json(__('Signed')), {
                                        left: r.left + 4,
                                        top: r.top + r.height - 16,
                                        fontSize: 11,
                                        fill: '#0f766e',
                                        fontFamily: 'system-ui, sans-serif',
                                        selectable: false,
                                        evented: false,
                                        hoverCursor: 'default',
                                    });
                                    fabricCanvas.add(img);
                                    fabricCanvas.add(badge);
                                    fabricCanvas.requestRenderAll();
                                },
                                { crossOrigin: 'anonymous' }
                            );
                        } else {
                            const chrome = getFieldChrome(field.type);
                            const group = buildFieldPreviewGroup(chrome, r);
                            group.fieldId = field.id;
                            group.selectable = false;
                            group.evented = true;
                            group.hasControls = false;
                            group.hoverCursor = 'pointer';
                            if (nextFieldId !== null && field.id === nextFieldId) {
                                group.shadow = new fabric.Shadow({
                                    color: 'rgba(20, 184, 166, 0.35)',
                                    blur: 18,
                                    offsetX: 0,
                                    offsetY: 0,
                                });
                            }
                            group.on('mousedown', function (e) {
                                e.e.preventDefault();
                                if (isSigned(field.id) || isSubmitting) {
                                    return;
                                }
                                if (field.type === 'name') {
                                    submitSignatureField(field.id, textToDataUrl(signerName));
                                    return;
                                }
                                if (field.type === 'date') {
                                    const d = new Intl.DateTimeFormat(dateLocale, { dateStyle: 'medium' }).format(
                                        new Date()
                                    );
                                    submitSignatureField(field.id, textToDataUrl(d));
                                    return;
                                }
                                if (field.type === 'email') {
                                    submitSignatureField(field.id, textToDataUrl(signerEmail));
                                    return;
                                }
                                if (field.type === 'initials') {
                                    submitSignatureField(field.id, textToDataUrl(signerInitialsValue()));
                                    return;
                                }
                                if (field.type === 'checkbox') {
                                    submitSignatureField(field.id, textToDataUrl('X'));
                                    return;
                                }
                                if (field.type === 'radio') {
                                    submitSignatureField(field.id, textToDataUrl('O'));
                                    return;
                                }
                                openModal(field.id);
                            });
                            fabricCanvas.add(group);
                        }
                    });
                }

                function syncFabricOverlayLayout(width, height) {
                    if (!fabricCanvas) {
                        return;
                    }

                    const wrapper = fabricCanvas.wrapperEl;
                    const lowerCanvas = fabricCanvas.lowerCanvasEl;
                    const upperCanvas = fabricCanvas.upperCanvasEl;

                    if (wrapper) {
                        wrapper.style.position = 'absolute';
                        wrapper.style.left = '0';
                        wrapper.style.top = '0';
                        wrapper.style.width = width + 'px';
                        wrapper.style.height = height + 'px';
                        wrapper.style.zIndex = '10';
                    }

                    [lowerCanvas, upperCanvas].forEach(function (canvas) {
                        if (!canvas) {
                            return;
                        }

                        canvas.style.position = 'absolute';
                        canvas.style.left = '0';
                        canvas.style.top = '0';
                        canvas.style.width = width + 'px';
                        canvas.style.height = height + 'px';
                    });
                }

                async function renderPage(pageNumber) {
                    if (!pdfDoc) {
                        return;
                    }
                    isRenderingPage = true;
                    updatePageUi();
                    const page = await pdfDoc.getPage(pageNumber);
                    const viewport = page.getViewport({ scale: renderScale });
                    const ctx = pdfCanvas.getContext('2d');
                    pdfCanvas.width = viewport.width;
                    pdfCanvas.height = viewport.height;
                    fabricEl.width = viewport.width;
                    fabricEl.height = viewport.height;
                    pdfCanvas.style.width = viewport.width + 'px';
                    pdfCanvas.style.height = viewport.height + 'px';
                    fabricEl.style.width = viewport.width + 'px';
                    fabricEl.style.height = viewport.height + 'px';
                    pdfShell.style.width = viewport.width + 'px';
                    pdfShell.style.height = viewport.height + 'px';
                    await page.render({ canvasContext: ctx, viewport: viewport }).promise;

                    if (!fabricCanvas) {
                        fabricCanvas = new fabric.Canvas('fabric-canvas', {
                            width: viewport.width,
                            height: viewport.height,
                            selection: false,
                        });
                    } else {
                        fabricCanvas.setWidth(viewport.width);
                        fabricCanvas.setHeight(viewport.height);
                    }

                    syncFabricOverlayLayout(viewport.width, viewport.height);
                    renderPageFields(viewport.width, viewport.height);
                    isRenderingPage = false;
                    updatePageUi();
                }

                function textToDataUrl(text) {
                    const el = document.createElement('canvas');
                    el.width = 400;
                    el.height = 120;
                    const fc = new fabric.Canvas(el);
                    const t = new fabric.Text(text, {
                        fontSize: 28,
                        fontFamily: 'Georgia, serif',
                        fill: '#0f172a',
                        originX: 'center',
                        originY: 'center',
                        left: 200,
                        top: 60,
                    });
                    fc.add(t);
                    return fc.toDataURL({ format: 'png' });
                }

                function submitSignatureField(fieldId, dataUrl) {
                    if (isSubmitting) {
                        return;
                    }
                    isSubmitting = true;
                    document.getElementById('modal-submit')?.setAttribute('disabled', 'disabled');
                    modalFieldId.value = String(fieldId);
                    modalSignatureImage.value = dataUrl;
                    signForm.submit();
                }

                function openModal(fieldId) {
                    modalFieldId.value = String(fieldId);
                    modalSignatureImage.value = '';
                    modal.showModal();
                    if (drawCanvas) {
                        drawCanvas.clear();
                        drawCanvas.backgroundColor = '#ffffff';
                        drawCanvas.isDrawingMode = true;
                    }
                }

                function closeModal() {
                    modal.close();
                }

                function showTab(name) {
                    document.querySelectorAll('.sign-tab').forEach(function (el) {
                        el.classList.remove('text-teal-700', 'ring-1', 'ring-zinc-200', 'dark:text-teal-300', 'dark:ring-zinc-600');
                        el.classList.add('text-zinc-600', 'dark:text-zinc-400');
                    });
                    document.querySelectorAll('.sign-panel').forEach(function (el) {
                        el.classList.add('hidden');
                    });
                    document.getElementById('panel-' + name).classList.remove('hidden');
                    const tab = document.getElementById('tab-' + name);
                    tab.classList.add('text-teal-700', 'ring-1', 'ring-zinc-200', 'dark:text-teal-300', 'dark:ring-zinc-600');
                    tab.classList.remove('text-zinc-600', 'dark:text-zinc-400');
                }

                document.getElementById('tab-draw')?.addEventListener('click', function () {
                    showTab('draw');
                });
                document.getElementById('tab-type')?.addEventListener('click', function () {
                    showTab('type');
                });
                document.getElementById('tab-upload')?.addEventListener('click', function () {
                    showTab('upload');
                });
                document.getElementById('modal-cancel')?.addEventListener('click', closeModal);

                async function init() {
                    const loadingTask = pdfjsLib.getDocument(pdfUrl);
                    pdfDoc = await loadingTask.promise;
                    totalPages = pdfDoc.numPages || 1;
                    currentPage = Math.min(totalPages, Math.max(1, initialPageNumber()));
                    await renderPage(currentPage);

                    btnPrevPage?.addEventListener('click', async function () {
                        if (currentPage <= 1 || isRenderingPage) {
                            return;
                        }
                        currentPage -= 1;
                        await renderPage(currentPage);
                    });
                    btnNextPage?.addEventListener('click', async function () {
                        if (currentPage >= totalPages || isRenderingPage) {
                            return;
                        }
                        currentPage += 1;
                        await renderPage(currentPage);
                    });

                    drawCanvas = new fabric.Canvas('draw-canvas', { isDrawingMode: true });
                    drawCanvas.isDrawingMode = true;
                    drawCanvas.backgroundColor = '#ffffff';
                    drawCanvas.freeDrawingBrush.width = 2;
                    drawCanvas.freeDrawingBrush.color = '#0f172a';
                    drawCanvas.setWidth(400);
                    drawCanvas.setHeight(200);
                    drawCanvas.renderAll();

                    document.getElementById('draw-clear')?.addEventListener('click', function () {
                        drawCanvas.clear();
                        drawCanvas.backgroundColor = '#ffffff';
                        drawCanvas.renderAll();
                    });

                    signForm?.addEventListener('submit', function (e) {
                        const drawHidden = document.getElementById('panel-draw').classList.contains('hidden');
                        const typeHidden = document.getElementById('panel-type').classList.contains('hidden');
                        const uploadHidden = document.getElementById('panel-upload').classList.contains('hidden');

                        if (!drawHidden) {
                            modalSignatureImage.value = drawCanvas.toDataURL({ format: 'png' });
                            return;
                        }
                        if (!typeHidden) {
                            const txt = document.getElementById('type-input').value.trim() || 'Signature';
                            const el = document.createElement('canvas');
                            el.width = 400;
                            el.height = 120;
                            const fc = new fabric.Canvas(el);
                            const t = new fabric.Text(txt, {
                                fontSize: 36,
                                fontFamily: 'Georgia, serif',
                                fill: '#0f172a',
                                originX: 'center',
                                originY: 'center',
                                left: 200,
                                top: 60,
                            });
                            fc.add(t);
                            modalSignatureImage.value = fc.toDataURL({ format: 'png' });
                            return;
                        }
                        if (!uploadHidden) {
                            const input = document.getElementById('upload-input');
                            if (!input.files || !input.files[0]) {
                                e.preventDefault();
                                alert(@json(__('Please choose an image.')));
                                return;
                            }
                            e.preventDefault();
                            const reader = new FileReader();
                            reader.onload = function (ev) {
                                modalSignatureImage.value = ev.target.result;
                                signForm.submit();
                            };
                            reader.readAsDataURL(input.files[0]);
                        }
                    });
                }

                init().catch(function (err) {
                    console.error(err);
                });
            })();
        </script>
        @endif
    </div>
</x-layouts.guest-simple>
