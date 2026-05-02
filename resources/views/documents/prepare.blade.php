<x-layouts.app>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-5 sm:gap-7 sm:px-6 lg:px-8">
        @if (session('status'))
            <div
                class="flex items-start gap-3 rounded-2xl border border-emerald-200/90 bg-gradient-to-r from-emerald-50 to-white px-4 py-3 text-sm text-emerald-900 shadow-sm dark:border-emerald-900/50 dark:from-emerald-950/40 dark:to-zinc-900 dark:text-emerald-100"
            >
                <span class="mt-0.5 inline-flex size-2 shrink-0 rounded-full bg-emerald-500"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="rounded-2xl border border-zinc-200/80 bg-white/85 p-4 shadow-sm shadow-zinc-950/5 backdrop-blur-sm dark:border-zinc-700/70 dark:bg-zinc-900/70">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">{{ __('Document setup') }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Prepare document') }}</h1>
                    <p class="mt-1 truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $document->title }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span class="rounded-full bg-zinc-100 px-2.5 py-1 font-medium dark:bg-zinc-800">{{ __('Step 3 of 4') }}</span>
                    <span>{{ __('Place fields, drag to position, then save.') }}</span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    id="btn-add-signature"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-500 dark:hover:bg-teal-600"
                    @if (! $firstSignerId) disabled @endif
                >
                    {{ __('Add signature field') }}
                </button>
                <button
                    type="button"
                    id="btn-add-text"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-800 shadow-sm transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                    @if (! $firstSignerId) disabled @endif
                >
                    {{ __('Add text field') }}
                </button>
                <button
                    type="button"
                    id="btn-add-name"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-900 shadow-sm transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-100 dark:hover:bg-emerald-950/60"
                    @if (! $firstSignerId) disabled @endif
                >
                    {{ __('Add name field') }}
                </button>
                <button
                    type="button"
                    id="btn-add-date"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-violet-300 bg-white px-4 py-2.5 text-sm font-semibold text-violet-900 shadow-sm transition hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-100 dark:hover:bg-violet-950/60"
                    @if (! $firstSignerId) disabled @endif
                >
                    {{ __('Add date field') }}
                </button>
                <button
                    type="button"
                    id="btn-save-fields"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-zinc-300 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-700 shadow-sm transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-white/5"
                    @if (! $firstSignerId) disabled @endif
                >
                    {{ __('Save fields') }}
                </button>
                <flux:button class="rounded-xl" variant="ghost" :href="route('documents.show', $document)" wire:navigate>{{ __('Back') }}</flux:button>
            </div>
        </div>

        @if (! $firstSignerId)
            <div class="rounded-2xl border border-amber-200/90 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
                {{ __('Add at least one signer on the document page before placing fields.') }}
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200/80 bg-white/80 px-3 py-2.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200">
                <span class="font-semibold">{{ __('Tip 1:') }}</span> {{ __('Add fields first, then drag to exact locations.') }}
            </div>
            <div class="rounded-xl border border-zinc-200/80 bg-white/80 px-3 py-2.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200">
                <span class="font-semibold">{{ __('Tip 2:') }}</span> {{ __('Use Name/Date for auto-filled signer details.') }}
            </div>
            <div class="rounded-xl border border-zinc-200/80 bg-white/80 px-3 py-2.5 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-200">
                <span class="font-semibold">{{ __('Tip 3:') }}</span> {{ __('Click Save fields before leaving this page.') }}
            </div>
        </div>

        <div class="ui-panel overflow-x-hidden overflow-y-auto rounded-2xl p-4 shadow-sm shadow-zinc-950/5 sm:p-6">
            <div id="pdf-load-error" class="mb-3 hidden rounded-xl border border-red-200/90 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"></div>
            <div class="mb-3 flex items-center gap-2">
                <button type="button" id="btn-prev-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Prev') }}</button>
                <button type="button" id="btn-next-page" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">{{ __('Next') }}</button>
                <span id="page-indicator" class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Page') }} 1 / 1</span>
            </div>
            <div id="pdf-shell" class="relative inline-block min-h-[200px] min-w-[200px] overflow-hidden rounded-xl bg-zinc-50 ring-1 ring-zinc-200/80 dark:bg-zinc-900/60 dark:ring-zinc-700/80">
                <canvas id="pdf-canvas" class="relative z-0 block max-w-none rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700"></canvas>
                <canvas id="fabric-canvas" class="absolute left-0 top-0 z-10 block cursor-move rounded-xl"></canvas>
            </div>
        </div>

        <form id="save-fields-form" method="POST" action="{{ route('documents.signature-fields.store', $document) }}" class="hidden">
            @csrf
            <input type="hidden" name="fields" id="fields-payload" value="[]" />
        </form>
    </div>
    <script>
        (function () {
            const debugPrefix = '[prepare-fields]';
            function debugLog(message, payload) {
                console.log(debugPrefix, message, payload ?? '');
            }

            const scriptRegistry = window.__docutrustPrepareScriptRegistry || (window.__docutrustPrepareScriptRegistry = {});
            const assetLoader = window.__docutrustPrepareAssetLoader || (window.__docutrustPrepareAssetLoader = (function () {
                function loadScript(src) {
                    if (scriptRegistry[src]) {
                        return scriptRegistry[src];
                    }

                    scriptRegistry[src] = new Promise(function (resolve, reject) {
                        const existing = document.querySelector(`script[src="${src}"]`);
                        if (existing) {
                            if (existing.dataset.loaded === '1') {
                                resolve();
                                return;
                            }

                            existing.addEventListener('load', function () {
                                existing.dataset.loaded = '1';
                                resolve();
                            }, { once: true });
                            existing.addEventListener('error', function () {
                                reject(new Error(`Failed to load script: ${src}`));
                            }, { once: true });
                            return;
                        }

                        const script = document.createElement('script');
                        script.src = src;
                        script.crossOrigin = 'anonymous';
                        script.async = true;
                        script.addEventListener('load', function () {
                            script.dataset.loaded = '1';
                            resolve();
                        }, { once: true });
                        script.addEventListener('error', function () {
                            reject(new Error(`Failed to load script: ${src}`));
                        }, { once: true });
                        document.head.appendChild(script);
                    });

                    return scriptRegistry[src];
                }

                return function loadAssets() {
                    debugLog('Loading PDF/Fabric assets');
                    return Promise.all([
                        loadScript('https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js'),
                        loadScript('https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js'),
                    ]);
                };
            })());

            const pdfUrl = @json($pdfUrl);
            const firstSignerId = @json($firstSignerId);
            const normalizedFirstSignerId = Number(firstSignerId) > 0 ? Number(firstSignerId) : null;
            const initialFields = @json($initialFields);
            const pdfCanvas = document.getElementById('pdf-canvas');
            const fabricEl = document.getElementById('fabric-canvas');
            const pdfShell = document.getElementById('pdf-shell');
            const pdfPanel = pdfShell?.closest('.ui-panel');
            const pdfLoadError = document.getElementById('pdf-load-error');

            if (!pdfCanvas || !fabricEl || !pdfShell) {
                debugLog('Missing required DOM nodes');
                return;
            }

            // Prevent duplicate initialization when the page script runs more than once (e.g. SPA navigate).
            if (pdfCanvas.dataset.initialized === '1') {
                debugLog('Skipped duplicate initialization');
                return;
            }
            pdfCanvas.dataset.initialized = '1';

            const pageIndicator = document.getElementById('page-indicator');
            const btnPrevPage = document.getElementById('btn-prev-page');
            const btnNextPage = document.getElementById('btn-next-page');
            const btnAddSignature = document.getElementById('btn-add-signature');
            const btnAddText = document.getElementById('btn-add-text');
            const btnAddName = document.getElementById('btn-add-name');
            const btnAddDate = document.getElementById('btn-add-date');
            const btnSaveFields = document.getElementById('btn-save-fields');
            let fabricCanvas = null;
            let pdfDoc = null;
            let currentPage = 1;
            let totalPages = 1;
            let isRenderingPage = true;
            const pageFields = new Map();
            let resizeTimer = null;

            function resolveRenderScale(page) {
                const baseViewport = page.getViewport({ scale: 1 });
                const panelWidth = pdfPanel ? pdfPanel.clientWidth : 0;
                const shellPadding = 24;
                const availableWidth = Math.max(320, panelWidth - shellPadding);

                if (baseViewport.width <= 0) {
                    return 1;
                }

                const fitScale = availableWidth / baseViewport.width;
                return Math.min(2, Math.max(0.5, fitScale));
            }

            function showPdfLoadError(message) {
                if (!pdfLoadError) {
                    return;
                }
                pdfLoadError.textContent = message;
                pdfLoadError.classList.remove('hidden');
            }

            function hidePdfLoadError() {
                if (!pdfLoadError) {
                    return;
                }
                pdfLoadError.classList.add('hidden');
            }

            function makeFieldGroup(type, signerId, position) {
                const w = fabricCanvas.getWidth();
                const h = fabricCanvas.getHeight();
                const left = position.x * w;
                const top = position.y * h;
                const width = position.width * w;
                const height = position.height * h;

                let fill = 'rgba(59, 130, 246, 0.12)';
                let stroke = '#2563eb';
                let label = 'Sign Here';
                if (type === 'text') {
                    fill = 'rgba(234, 179, 8, 0.15)';
                    stroke = '#ca8a04';
                    label = 'Text';
                } else if (type === 'name') {
                    fill = 'rgba(34, 197, 94, 0.15)';
                    stroke = '#15803d';
                    label = 'Name';
                } else if (type === 'date') {
                    fill = 'rgba(139, 92, 246, 0.12)';
                    stroke = '#6d28d9';
                    label = 'Date';
                }

                const rect = new fabric.Rect({
                    width: width,
                    height: height,
                    fill: fill,
                    stroke: stroke,
                    strokeWidth: 2,
                    rx: 4,
                    ry: 4,
                });
                const text = new fabric.Text(label, {
                    fontSize: Math.min(16, height * 0.35),
                    fill:
                        type === 'text'
                            ? '#a16207'
                            : type === 'name'
                              ? '#15803d'
                              : type === 'date'
                                ? '#5b21b6'
                                : '#1d4ed8',
                    fontFamily: 'system-ui, sans-serif',
                    originX: 'center',
                    originY: 'center',
                    left: width / 2,
                    top: height / 2,
                });
                const group = new fabric.Group([rect, text], {
                    left: left,
                    top: top,
                    subTargetCheck: true,
                });
                group.fieldType = type;
                group.signerId = signerId;
                group.pageNumber = currentPage;
                group.hasControls = false;
                group.lockRotation = true;
                group.set('lockScalingX', true);
                group.set('lockScalingY', true);
                return group;
            }

            function resolveVisiblePosition(width, height) {
                const fallback = {
                    x: Math.max(0.01, Math.min(0.99 - width, 0.08)),
                    y: Math.max(0.01, Math.min(0.99 - height, 0.12)),
                };

                if (!pdfPanel || !fabricEl) {
                    return fallback;
                }

                const panelRect = pdfPanel.getBoundingClientRect();
                const canvasRect = fabricEl.getBoundingClientRect();

                const visibleLeft = Math.max(panelRect.left, canvasRect.left);
                const visibleTop = Math.max(panelRect.top, canvasRect.top);
                const visibleRight = Math.min(panelRect.right, canvasRect.right);
                const visibleBottom = Math.min(panelRect.bottom, canvasRect.bottom);

                if (visibleRight <= visibleLeft || visibleBottom <= visibleTop) {
                    return fallback;
                }

                const centerX = visibleLeft + (visibleRight - visibleLeft) / 2;
                const centerY = visibleTop + (visibleBottom - visibleTop) / 2;
                const normalizedX = (centerX - canvasRect.left) / canvasRect.width;
                const normalizedY = (centerY - canvasRect.top) / canvasRect.height;

                return {
                    x: Math.max(0.01, Math.min(0.99 - width, normalizedX - width / 2)),
                    y: Math.max(0.01, Math.min(0.99 - height, normalizedY - height / 2)),
                };
            }

            function addField(type, width, height) {
                if (!firstSignerId || !fabricCanvas || isRenderingPage) {
                    debugLog('Add field blocked', {
                        firstSignerId: firstSignerId,
                        normalizedFirstSignerId: normalizedFirstSignerId,
                        hasFabricCanvas: Boolean(fabricCanvas),
                        isRenderingPage: isRenderingPage,
                    });
                    if (!fabricCanvas || isRenderingPage) {
                        showPdfLoadError(@json(__('Preview still loading. Please wait a second, then try again.')));
                    } else if (!firstSignerId) {
                        showPdfLoadError(@json(__('No signer found. Add at least one signer first.')));
                    }
                    return;
                }

                hidePdfLoadError();
                const visiblePosition = resolveVisiblePosition(width, height);
                const g = makeFieldGroup(type, normalizedFirstSignerId, {
                    x: visiblePosition.x,
                    y: visiblePosition.y,
                    width: width,
                    height: height,
                });
                fabricCanvas.add(g);
                fabricCanvas.setActiveObject(g);
                fabricCanvas.requestRenderAll();
                saveCurrentPageFields();
                debugLog('Field added', {
                    type: type,
                    signerId: normalizedFirstSignerId,
                    page: currentPage,
                    position: visiblePosition,
                });
            }

            function collectFields() {
                const out = [];
                saveCurrentPageFields();
                pageFields.forEach(function (fields, pageNumber) {
                    fields.forEach(function (field) {
                        out.push({
                            signer_id: field.signer_id,
                            type: field.type,
                            page_number: pageNumber,
                            position_data: field.position_data,
                        });
                    });
                });
                return out;
            }

            function serializeCanvasFields() {
                const out = [];
                const w = fabricCanvas.getWidth();
                const h = fabricCanvas.getHeight();
                fabricCanvas.getObjects().forEach(function (obj) {
                    if (!obj.fieldType) {
                        return;
                    }
                    const br = obj.getBoundingRect(true);
                    out.push({
                        signer_id: obj.signerId,
                        type: obj.fieldType,
                        position_data: {
                            x: br.left / w,
                            y: br.top / h,
                            width: br.width / w,
                            height: br.height / h,
                        },
                    });
                });
                return out;
            }

            function saveCurrentPageFields() {
                if (!fabricCanvas) {
                    return;
                }
                pageFields.set(currentPage, serializeCanvasFields());
            }

            function loadCurrentPageFields() {
                fabricCanvas.clear();
                const fields = pageFields.get(currentPage) || [];
                fields.forEach(function (f) {
                    const g = makeFieldGroup(f.type, f.signer_id, f.position_data);
                    fabricCanvas.add(g);
                });
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
                const shouldDisableFieldButtons = !firstSignerId || isRenderingPage;
                if (!firstSignerId) {
                    debugLog('Buttons disabled: missing first signer', { firstSignerId: firstSignerId });
                } else if (isRenderingPage) {
                    debugLog('Buttons disabled: page rendering');
                }
                if (btnAddSignature) {
                    btnAddSignature.disabled = shouldDisableFieldButtons;
                }
                if (btnAddText) {
                    btnAddText.disabled = shouldDisableFieldButtons;
                }
                if (btnAddName) {
                    btnAddName.disabled = shouldDisableFieldButtons;
                }
                if (btnAddDate) {
                    btnAddDate.disabled = shouldDisableFieldButtons;
                }
                if (btnSaveFields) {
                    btnSaveFields.disabled = shouldDisableFieldButtons;
                }
            }

            async function renderPage(pageNumber) {
                if (!pdfDoc) {
                    return;
                }
                isRenderingPage = true;
                updatePageUi();
                try {
                    debugLog('Rendering page', { pageNumber: pageNumber });
                    const page = await pdfDoc.getPage(pageNumber);
                    const viewport = page.getViewport({ scale: resolveRenderScale(page) });
                    const ctx = pdfCanvas.getContext('2d');
                    pdfCanvas.width = viewport.width;
                    pdfCanvas.height = viewport.height;
                    fabricEl.width = viewport.width;
                    fabricEl.height = viewport.height;
                    fabricEl.style.width = viewport.width + 'px';
                    fabricEl.style.height = viewport.height + 'px';
                    pdfShell.style.width = viewport.width + 'px';
                    pdfShell.style.height = viewport.height + 'px';
                    await page.render({ canvasContext: ctx, viewport: viewport }).promise;
                    if (!fabricCanvas) {
                        fabricCanvas = new fabric.Canvas('fabric-canvas', {
                            width: viewport.width,
                            height: viewport.height,
                            selection: true,
                        });
                        debugLog('Fabric canvas initialized');
                    } else {
                        fabricCanvas.setWidth(viewport.width);
                        fabricCanvas.setHeight(viewport.height);
                    }
                    loadCurrentPageFields();
                } finally {
                    isRenderingPage = false;
                    updatePageUi();
                    debugLog('Rendering complete', { pageNumber: pageNumber, isRenderingPage: isRenderingPage });
                }
            }

            async function init() {
                await assetLoader();

                if (typeof pdfjsLib === 'undefined' || typeof fabric === 'undefined') {
                    debugLog('Asset load failed', {
                        hasPdfJs: typeof pdfjsLib !== 'undefined',
                        hasFabric: typeof fabric !== 'undefined',
                    });
                    throw new Error('PDF.js or Fabric.js failed to load');
                }

                pdfjsLib.GlobalWorkerOptions.workerSrc =
                    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                const loadingTask = pdfjsLib.getDocument({
                    url: pdfUrl,
                    withCredentials: true,
                });
                pdfDoc = await loadingTask.promise;
                totalPages = pdfDoc.numPages || 1;

                initialFields.forEach(function (f) {
                    const pageNumber = Number(f.page_number) > 0 ? Number(f.page_number) : 1;
                    if (!pageFields.has(pageNumber)) {
                        pageFields.set(pageNumber, []);
                    }
                    pageFields.get(pageNumber).push({
                        signer_id: f.signer_id,
                        type: f.type,
                        position_data: f.position_data,
                    });
                });

                await renderPage(currentPage);
                hidePdfLoadError();

                btnPrevPage?.addEventListener('click', async function () {
                    if (currentPage <= 1 || isRenderingPage) {
                        return;
                    }
                    saveCurrentPageFields();
                    currentPage -= 1;
                    await renderPage(currentPage);
                });

                btnNextPage?.addEventListener('click', async function () {
                    if (currentPage >= totalPages || isRenderingPage) {
                        return;
                    }
                    saveCurrentPageFields();
                    currentPage += 1;
                    await renderPage(currentPage);
                });

                window.addEventListener('resize', function () {
                    if (resizeTimer) {
                        window.clearTimeout(resizeTimer);
                    }
                    resizeTimer = window.setTimeout(async function () {
                        if (!pdfDoc || isRenderingPage) {
                            return;
                        }
                        saveCurrentPageFields();
                        await renderPage(currentPage);
                    }, 150);
                });

                btnAddSignature?.addEventListener('click', function () {
                    debugLog('Add signature clicked');
                    addField('signature', 0.28, 0.06);
                });

                btnAddText?.addEventListener('click', function () {
                    debugLog('Add text clicked');
                    addField('text', 0.28, 0.06);
                });

                btnAddName?.addEventListener('click', function () {
                    debugLog('Add name clicked');
                    addField('name', 0.28, 0.06);
                });

                btnAddDate?.addEventListener('click', function () {
                    debugLog('Add date clicked');
                    addField('date', 0.28, 0.06);
                });

                btnSaveFields?.addEventListener('click', function () {
                    if (!fabricCanvas || isRenderingPage) {
                        return;
                    }
                    const fields = collectFields();
                    document.getElementById('fields-payload').value = JSON.stringify(fields);
                    document.getElementById('save-fields-form').submit();
                });

                fabricCanvas.on('object:modified', saveCurrentPageFields);
                fabricCanvas.on('object:removed', saveCurrentPageFields);
                fabricCanvas.on('object:added', saveCurrentPageFields);
            }

            init().catch(function (e) {
                console.error(e);
                showPdfLoadError(@json(__('Unable to load document preview. Please refresh the page and try again.')));
            });

            updatePageUi();
        })();
    </script>
</x-layouts.app>
