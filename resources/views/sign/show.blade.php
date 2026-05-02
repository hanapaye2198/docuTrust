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
                </div>
                <div class="mb-3 flex items-center gap-2">
                    <button type="button" id="btn-prev-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Prev') }}</button>
                    <button type="button" id="btn-next-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}</button>
                    <span id="page-indicator" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
                </div>
                <div
                    id="pdf-shell"
                    class="relative inline-block min-h-[200px] min-w-[200px] rounded-xl shadow-xl shadow-zinc-950/15 ring-1 ring-zinc-300/70 dark:shadow-black/50 dark:ring-zinc-600/80"
                >
                    <canvas id="pdf-canvas" class="block max-w-none rounded-[0.65rem] border border-zinc-200/90 bg-white dark:border-zinc-700"></canvas>
                    <canvas id="fabric-canvas" class="absolute left-0 top-0 block rounded-lg"></canvas>
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
    </div>
</x-layouts.guest-simple>

@if ($showFieldSigning)
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js" crossorigin="anonymous"></script>
        <script>
            (function () {
                const pdfUrl = @json($pdfUrl);
                const fieldsJson = @json($fieldsJson);
                const signedByFieldId = @json($signedByFieldId);
                const signerName = @json($signer->name);
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

                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                function isSigned(fieldId) {
                    return Object.prototype.hasOwnProperty.call(signedByFieldId, String(fieldId));
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
                        case 'text':
                            return {
                                stroke: '#ca8a04',
                                fill: 'rgba(234, 179, 8, 0.15)',
                                fillText: '#a16207',
                                label: 'Text',
                            };
                        case 'name':
                            return {
                                stroke: '#15803d',
                                fill: 'rgba(34, 197, 94, 0.15)',
                                fillText: '#15803d',
                                label: 'Name',
                            };
                        case 'date':
                            return {
                                stroke: '#6d28d9',
                                fill: 'rgba(139, 92, 246, 0.12)',
                                fillText: '#5b21b6',
                                label: 'Date',
                            };
                        default:
                            return {
                                stroke: '#2563eb',
                                fill: 'rgba(59, 130, 246, 0.12)',
                                fillText: '#1d4ed8',
                                label: 'Sign Here',
                            };
                    }
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
                            const rect = new fabric.Rect({
                                width: r.width,
                                height: r.height,
                                fill: chrome.fill,
                                stroke: chrome.stroke,
                                strokeWidth: 2,
                                rx: 4,
                                ry: 4,
                            });
                            const text = new fabric.Text(chrome.label, {
                                fontSize: Math.min(16, r.height * 0.35),
                                fill: chrome.fillText,
                                fontFamily: 'system-ui, sans-serif',
                                originX: 'center',
                                originY: 'center',
                                left: r.width / 2,
                                top: r.height / 2,
                            });
                            const group = new fabric.Group([rect, text], {
                                left: r.left,
                                top: r.top,
                                subTargetCheck: true,
                            });
                            group.fieldId = field.id;
                            group.selectable = false;
                            group.hasControls = false;
                            group.hoverCursor = 'pointer';
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
                                openModal(field.id);
                            });
                            fabricCanvas.add(group);
                        }
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
                    fabricEl.style.width = viewport.width + 'px';
                    fabricEl.style.height = viewport.height + 'px';
                    pdfShell.style.width = viewport.width + 'px';
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
                    drawCanvas.freeDrawingBrush.width = 2;
                    drawCanvas.freeDrawingBrush.color = '#0f172a';
                    drawCanvas.setWidth(400);
                    drawCanvas.setHeight(200);

                    document.getElementById('draw-clear')?.addEventListener('click', function () {
                        drawCanvas.clear();
                        drawCanvas.backgroundColor = '#ffffff';
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
    @endpush
@endif
