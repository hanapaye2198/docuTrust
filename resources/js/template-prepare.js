import { ensurePrepareAssets } from './document-prepare/assets';
import { getFieldConfig } from './document-prepare/field-types';
import {
    createFieldGroup,
    normalizedPositionFromObject,
    restoreSelectionByClientId,
    serializeCanvasFields,
} from './document-prepare/fabric-fields';
import {
    canvasContainsClientPoint,
    enforceObjectMinimumSize,
    keepObjectInsideCanvas,
    normalizedPositionFromClientPoint,
    resolveRenderScale,
    resolveVisiblePosition,
    snapObjectToGuides,
} from './document-prepare/geometry';

let activePrepareSession = null;

export function initTemplatePreparePage() {
    const cfgEl = document.getElementById('template-prepare-config');

    if (!cfgEl) {
        activePrepareSession?.destroy();
        activePrepareSession = null;

        return;
    }

    if (activePrepareSession?.owns(cfgEl)) {
        return;
    }

    activePrepareSession?.destroy();

    const session = createPrepareSession(cfgEl);
    activePrepareSession = session;

    session.init().catch((error) => {
        if (activePrepareSession !== session || session.isDestroyed()) {
            return;
        }

        console.error(error);
        session.showLoadError();
    });
}

function createPrepareSession(cfgEl) {
    let config;
    try {
        config = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        config = {};
    }

    const msgs = config.messages || {};
    const debugPrefix = '[prepare-fields]';
    const abortController = new AbortController();
    const { signal } = abortController;

    const pdfUrl = config.pdfUrl;
    const firstSignerId = config.firstSignerId;
    const signers = Array.isArray(config.signers) ? config.signers : [];
    const normalizedFirstSignerId = Number(firstSignerId) > 0 ? Number(firstSignerId) : null;
    const initialFields = Array.isArray(config.initialFields) ? config.initialFields : [];
    const pdfCanvas = document.getElementById('pdf-canvas');
    const fabricEl = document.getElementById('fabric-canvas');
    const pdfShell = document.getElementById('pdf-shell');
    const pdfPanel = pdfShell?.closest('.ui-panel');
    const pdfLoadError = document.getElementById('pdf-load-error');

    const pageIndicator = document.getElementById('page-indicator');
    const btnPrevPage = document.getElementById('btn-prev-page');
    const btnNextPage = document.getElementById('btn-next-page');
    const btnSaveFields = document.getElementById('btn-save-fields');
    const editorStatus = document.getElementById('editor-status');
    const fieldSigner = document.getElementById('field-signer');
    const fieldPaletteButtons = Array.from(document.querySelectorAll('.field-palette-btn'));
    const fieldInspectorBody = document.getElementById('field-inspector-body');
    const fieldInspectorEmpty = document.getElementById('field-inspector-empty');
    const selectedFieldType = document.getElementById('selected-field-type');
    const selectedFieldSigner = document.getElementById('selected-field-signer');
    const btnDuplicateField = document.getElementById('btn-duplicate-field');
    const btnDeleteField = document.getElementById('btn-delete-field');
    const btnBringForward = document.getElementById('btn-bring-forward');
    const btnSendBackward = document.getElementById('btn-send-backward');
    const saveFieldsForm = document.getElementById('save-fields-form');
    const fieldsPayload = document.getElementById('fields-payload');

    let fabricCanvas = null;
    let pdfDoc = null;
    let loadingTask = null;
    let activeRenderTask = null;
    let currentPage = 1;
    let totalPages = 1;
    let isRenderingPage = true;
    let hasUnsavedChanges = false;
    let isSaving = false;
    let dragFieldType = null;
    let selectedFieldClientId = null;
    let clientFieldCounter = 0;
    let copiedField = null;
    let currentSnapGuide = null;
    let resizeTimer = null;
    let renderSequence = 0;
    let destroyed = false;

    const pageFields = new Map();
    const signerById = new Map(signers.map((signer) => [Number(signer.id), signer]));

    function debugLog(message, payload) {
        console.log(debugPrefix, message, payload ?? '');
    }

    function owns(node) {
        return cfgEl === node;
    }

    function isDestroyed() {
        return destroyed;
    }

    function listen(target, eventName, handler, options = undefined) {
        if (!target) {
            return;
        }

        const listenerOptions =
            typeof options === 'boolean'
                ? { capture: options, signal }
                : { ...(options || {}), signal };

        target.addEventListener(eventName, handler, listenerOptions);
    }

    function selectedSignerId() {
        const value = fieldSigner ? Number(fieldSigner.value) : normalizedFirstSignerId;
        return Number.isFinite(value) && value > 0 ? value : normalizedFirstSignerId;
    }

    function hasAvailableSigner() {
        return Number.isFinite(selectedSignerId()) && selectedSignerId() > 0;
    }

    function signerNameFor(id) {
        return signerById.get(Number(id))?.name || 'Signer';
    }

    function activeFieldObject() {
        if (!fabricCanvas) {
            return null;
        }

        const active = fabricCanvas.getActiveObject();
        return active && active.fieldType ? active : null;
    }

    function nextClientFieldId() {
        clientFieldCounter += 1;
        return `field-${clientFieldCounter}`;
    }

    function updateEditorStatus() {
        if (!editorStatus || destroyed) {
            return;
        }

        let text = msgs.saved || 'Saved';
        let classes = 'rounded-full px-2.5 py-1 text-xs font-medium ';

        if (isSaving) {
            text = msgs.saving || 'Saving...';
            classes += 'border border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100';
        } else if (hasUnsavedChanges) {
            text = msgs.unsaved || 'Unsaved changes';
            classes += 'border border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100';
        } else {
            classes += 'border border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100';
        }

        editorStatus.className = classes;
        editorStatus.textContent = text;
    }

    function markDirty() {
        hasUnsavedChanges = true;
        updateEditorStatus();
    }

    function markSaved() {
        hasUnsavedChanges = false;
        isSaving = false;
        updateEditorStatus();
    }

    function setFieldInspectorState(target) {
        if (!fieldInspectorBody || !fieldInspectorEmpty || !selectedFieldType || !selectedFieldSigner || destroyed) {
            return;
        }

        if (!target) {
            fieldInspectorBody.classList.add('hidden');
            fieldInspectorEmpty.textContent = msgs.none || 'None';
            return;
        }

        fieldInspectorBody.classList.remove('hidden');
        fieldInspectorEmpty.textContent = target.clientFieldId || msgs.selected || 'Selected';
        selectedFieldType.value = target.fieldType;
        selectedFieldSigner.value = String(target.signerId);
    }

    function clearSnapGuide() {
        if (!currentSnapGuide || !fabricCanvas) {
            return;
        }

        fabricCanvas.remove(currentSnapGuide);
        currentSnapGuide = null;
    }

    function showPdfLoadError(message) {
        if (!pdfLoadError || destroyed) {
            return;
        }

        pdfLoadError.textContent = message;
        pdfLoadError.classList.remove('hidden');
    }

    function hidePdfLoadError() {
        if (!pdfLoadError || destroyed) {
            return;
        }

        pdfLoadError.classList.add('hidden');
    }

    function showLoadError() {
        showPdfLoadError(msgs.loadFailed || 'Unable to load document preview. Please refresh the page and try again.');
    }

    function setShellDropHighlight(active) {
        if (!pdfShell || destroyed) {
            return;
        }

        pdfShell.classList.toggle('ring-2', active);
        pdfShell.classList.toggle('ring-teal-400', active);
    }

    function buildFieldGroup(type, signerId, position, clientFieldId) {
        return createFieldGroup({
            fabric: window.fabric,
            fabricCanvas,
            type,
            signerId,
            signerName: signerNameFor(signerId),
            position,
            pageNumber: currentPage,
            clientFieldId: clientFieldId || nextClientFieldId(),
        });
    }

    function saveCurrentPageFields() {
        if (!fabricCanvas || destroyed) {
            return;
        }

        pageFields.set(currentPage, serializeCanvasFields(fabricCanvas));
    }

    function loadCurrentPageFields() {
        if (!fabricCanvas || destroyed) {
            return;
        }

        fabricCanvas.clear();

        const fields = pageFields.get(currentPage) || [];
        fields.forEach((field) => {
            const group = buildFieldGroup(field.type, field.signer_id, field.position_data, field.client_id);
            fabricCanvas.add(group);
        });

        restoreSelectionByClientId(fabricCanvas, selectedFieldClientId);
    }

    function replaceActiveField(updates) {
        const active = activeFieldObject();
        if (!active) {
            return;
        }

        const normalized = normalizedPositionFromObject(active, fabricCanvas);
        if (!normalized) {
            return;
        }

        const fieldType = updates?.type || active.fieldType;
        const signerId = updates?.signerId || active.signerId;
        const clientFieldId = active.clientFieldId;

        fabricCanvas.remove(active);

        const replacement = buildFieldGroup(fieldType, signerId, normalized, clientFieldId);
        fabricCanvas.add(replacement);
        fabricCanvas.setActiveObject(replacement);
        selectedFieldClientId = replacement.clientFieldId;

        saveCurrentPageFields();
        markDirty();
        setFieldInspectorState(replacement);
        fabricCanvas.requestRenderAll();
    }

    function deleteActiveField() {
        const active = activeFieldObject();
        if (!active) {
            return;
        }

        fabricCanvas.remove(active);
        fabricCanvas.discardActiveObject();
        selectedFieldClientId = null;

        saveCurrentPageFields();
        markDirty();
        setFieldInspectorState(null);
        fabricCanvas.requestRenderAll();
    }

    function duplicateField(target) {
        if (!target || !fabricCanvas) {
            return;
        }

        const normalized = normalizedPositionFromObject(target, fabricCanvas);
        if (!normalized) {
            return;
        }

        const copy = buildFieldGroup(
            target.fieldType,
            target.signerId,
            {
                x: Math.min(0.99 - normalized.width, normalized.x + 0.02),
                y: Math.min(0.99 - normalized.height, normalized.y + 0.02),
                width: normalized.width,
                height: normalized.height,
            },
            nextClientFieldId(),
        );

        fabricCanvas.add(copy);
        fabricCanvas.setActiveObject(copy);
        selectedFieldClientId = copy.clientFieldId;

        saveCurrentPageFields();
        markDirty();
        setFieldInspectorState(copy);
        fabricCanvas.requestRenderAll();
    }

    function moveActiveFieldLayer(direction) {
        const active = activeFieldObject();
        if (!active || !fabricCanvas) {
            return;
        }

        if (direction === 'forward') {
            active.bringForward();
        } else {
            active.sendBackwards();
        }

        selectedFieldClientId = active.clientFieldId;
        saveCurrentPageFields();
        markDirty();
        setFieldInspectorState(active);
        fabricCanvas.requestRenderAll();
    }

    function addField(type, dropPoint) {
        if (!hasAvailableSigner() || !fabricCanvas || isRenderingPage) {
            debugLog('Add field blocked', {
                firstSignerId,
                normalizedFirstSignerId,
                selectedSignerId: selectedSignerId(),
                hasFabricCanvas: Boolean(fabricCanvas),
                isRenderingPage,
            });

            if (!fabricCanvas || isRenderingPage) {
                showPdfLoadError(msgs.previewLoading || 'Preview still loading. Please wait a second, then try again.');
            } else if (!hasAvailableSigner()) {
                showPdfLoadError(msgs.noSigner || 'No signer found. Add at least one signer first.');
            }

            return;
        }

        hidePdfLoadError();

        const fieldConfig = getFieldConfig(type);
        const width = fieldConfig.width;
        const height = fieldConfig.height;
        const visiblePosition = dropPoint
            ? normalizedPositionFromClientPoint({
                clientX: dropPoint.x,
                clientY: dropPoint.y,
                width,
                height,
                fabricEl,
                pdfPanel,
            })
            : resolveVisiblePosition({ width, height, pdfPanel, fabricEl });

        const signerId = selectedSignerId();
        const field = buildFieldGroup(
            type,
            signerId,
            {
                x: visiblePosition.x,
                y: visiblePosition.y,
                width,
                height,
            },
            nextClientFieldId(),
        );

        fabricCanvas.add(field);
        fabricCanvas.setActiveObject(field);
        selectedFieldClientId = field.clientFieldId;
        fabricCanvas.requestRenderAll();

        saveCurrentPageFields();
        markDirty();

        debugLog('Field added', {
            type,
            signerId,
            page: currentPage,
            position: visiblePosition,
        });
    }

    function collectFields() {
        const out = [];

        saveCurrentPageFields();

        pageFields.forEach((fields, pageNumber) => {
            fields.forEach((field) => {
                out.push({
                    client_id: field.client_id,
                    signer_id: field.signer_id,
                    type: field.type,
                    page_number: pageNumber,
                    position_data: field.position_data,
                });
            });
        });

        return out;
    }

    function updatePageUi() {
        if (destroyed) {
            return;
        }

        if (pageIndicator) {
            pageIndicator.textContent = `Page ${currentPage} / ${totalPages}`;
        }

        if (btnPrevPage) {
            btnPrevPage.disabled = currentPage <= 1 || isRenderingPage;
        }

        if (btnNextPage) {
            btnNextPage.disabled = currentPage >= totalPages || isRenderingPage;
        }

        const shouldDisableFieldButtons = !hasAvailableSigner() || isRenderingPage;

        if (!hasAvailableSigner()) {
            debugLog('Buttons disabled: missing first signer', { firstSignerId });
        } else if (isRenderingPage) {
            debugLog('Buttons disabled: page rendering');
        }

        fieldPaletteButtons.forEach((button) => {
            button.disabled = shouldDisableFieldButtons;
        });

        if (fieldSigner) {
            fieldSigner.disabled = shouldDisableFieldButtons;
        }

        if (btnSaveFields) {
            btnSaveFields.disabled = shouldDisableFieldButtons;
        }
    }

    function bindFabricCanvasEvents() {
        if (!fabricCanvas) {
            return;
        }

        fabricCanvas.on('selection:created', (event) => {
            const target = event.selected?.[0] ?? null;
            selectedFieldClientId = target?.clientFieldId ?? null;
            setFieldInspectorState(target);
        });

        fabricCanvas.on('selection:updated', (event) => {
            const target = event.selected?.[0] ?? null;
            selectedFieldClientId = target?.clientFieldId ?? null;
            setFieldInspectorState(target);
        });

        fabricCanvas.on('selection:cleared', () => {
            selectedFieldClientId = null;
            setFieldInspectorState(null);
            clearSnapGuide();
        });

        fabricCanvas.on('object:moving', (event) => {
            keepObjectInsideCanvas(event.target, fabricCanvas);
        });

        fabricCanvas.on('object:scaling', (event) => {
            enforceObjectMinimumSize(event.target, fabricCanvas);
            keepObjectInsideCanvas(event.target, fabricCanvas);
        });

        fabricCanvas.on('object:modified', () => {
            currentSnapGuide = snapObjectToGuides({
                target: activeFieldObject(),
                fabric: window.fabric,
                fabricCanvas,
                currentSnapGuide,
            });

            clearSnapGuide();
            saveCurrentPageFields();
            markDirty();
            setFieldInspectorState(activeFieldObject());
        });

        fabricCanvas.on('object:removed', () => {
            saveCurrentPageFields();
            markDirty();
        });

        fabricCanvas.on('object:added', saveCurrentPageFields);
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
            wrapper.style.width = `${width}px`;
            wrapper.style.height = `${height}px`;
            wrapper.style.zIndex = '20';
        }

        [lowerCanvas, upperCanvas].forEach((canvas) => {
            if (!canvas) {
                return;
            }

            canvas.style.position = 'absolute';
            canvas.style.left = '0';
            canvas.style.top = '0';
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;
        });
    }

    async function renderPage(pageNumber) {
        if (!pdfDoc || destroyed) {
            return;
        }

        const requestId = ++renderSequence;
        let renderTask = null;

        isRenderingPage = true;
        updatePageUi();

        try {
            debugLog('Rendering page', { pageNumber });

            const page = await pdfDoc.getPage(pageNumber);
            if (destroyed || requestId !== renderSequence) {
                return;
            }

            const viewport = page.getViewport({
                scale: resolveRenderScale(page, pdfPanel ? pdfPanel.clientWidth : 0),
            });

            const ctx = pdfCanvas?.getContext('2d');
            if (!ctx || !pdfCanvas || !fabricEl || !pdfShell) {
                throw new Error('Missing required DOM nodes');
            }

            pdfCanvas.width = viewport.width;
            pdfCanvas.height = viewport.height;
            fabricEl.width = viewport.width;
            fabricEl.height = viewport.height;
            fabricEl.style.width = `${viewport.width}px`;
            fabricEl.style.height = `${viewport.height}px`;
            pdfShell.style.width = `${viewport.width}px`;
            pdfShell.style.height = `${viewport.height}px`;

            activeRenderTask?.cancel?.();
            renderTask = page.render({ canvasContext: ctx, viewport });
            activeRenderTask = renderTask;
            await renderTask.promise;

            if (destroyed || requestId !== renderSequence) {
                return;
            }

            activeRenderTask = null;

            if (!fabricCanvas) {
                fabricCanvas = new window.fabric.Canvas('fabric-canvas', {
                    width: viewport.width,
                    height: viewport.height,
                    selection: true,
                    preserveObjectStacking: true,
                });
                bindFabricCanvasEvents();
                debugLog('Fabric canvas initialized');
            } else {
                fabricCanvas.setWidth(viewport.width);
                fabricCanvas.setHeight(viewport.height);
            }

            syncFabricOverlayLayout(viewport.width, viewport.height);
            loadCurrentPageFields();
            fabricCanvas.requestRenderAll();
        } catch (error) {
            if (error?.name === 'RenderingCancelledException') {
                return;
            }

            throw error;
        } finally {
            if (activeRenderTask === renderTask) {
                activeRenderTask = null;
            }

            if (!destroyed && requestId === renderSequence) {
                isRenderingPage = false;
                updatePageUi();
                debugLog('Rendering complete', { pageNumber, isRenderingPage });
            }
        }
    }

    function destroy() {
        if (destroyed) {
            return;
        }

        renderSequence += 1;
        isRenderingPage = false;

        if (resizeTimer) {
            window.clearTimeout(resizeTimer);
            resizeTimer = null;
        }

        activeRenderTask?.cancel?.();
        loadingTask?.destroy?.();
        dragFieldType = null;
        clearSnapGuide();
        setShellDropHighlight(false);

        if (fabricCanvas) {
            fabricCanvas.dispose();
            fabricCanvas = null;
        }

        abortController.abort();
        destroyed = true;

        if (activePrepareSession === api) {
            activePrepareSession = null;
        }
    }

    async function init() {
        if (!pdfCanvas || !fabricEl || !pdfShell) {
            debugLog('Missing required DOM nodes');
            return;
        }

        if (!pdfUrl) {
            throw new Error('Missing PDF preview URL');
        }

        markSaved();
        updatePageUi();

        await ensurePrepareAssets(debugLog);
        if (destroyed) {
            return;
        }

        if (typeof window.pdfjsLib === 'undefined' || typeof window.fabric === 'undefined') {
            debugLog('Asset load failed', {
                hasPdfJs: typeof window.pdfjsLib !== 'undefined',
                hasFabric: typeof window.fabric !== 'undefined',
            });

            throw new Error('PDF.js or Fabric.js failed to load');
        }

        loadingTask = window.pdfjsLib.getDocument({
            url: pdfUrl,
            withCredentials: true,
        });

        pdfDoc = await loadingTask.promise;
        if (destroyed) {
            return;
        }

        totalPages = pdfDoc.numPages || 1;

        initialFields.forEach((field) => {
            const pageNumber = Number(field.page_number) > 0 ? Number(field.page_number) : 1;
            if (!pageFields.has(pageNumber)) {
                pageFields.set(pageNumber, []);
            }

            pageFields.get(pageNumber).push({
                client_id: field.id ? `persisted-${field.id}` : nextClientFieldId(),
                signer_id: field.signer_id,
                type: field.type,
                position_data: field.position_data,
            });
        });

        await renderPage(currentPage);
        if (destroyed) {
            return;
        }

        hidePdfLoadError();

        listen(btnPrevPage, 'click', async () => {
            if (currentPage <= 1 || isRenderingPage) {
                return;
            }

            saveCurrentPageFields();
            currentPage -= 1;
            await renderPage(currentPage);
        });

        listen(btnNextPage, 'click', async () => {
            if (currentPage >= totalPages || isRenderingPage) {
                return;
            }

            saveCurrentPageFields();
            currentPage += 1;
            await renderPage(currentPage);
        });

        listen(window, 'resize', () => {
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }

            resizeTimer = window.setTimeout(async () => {
                if (!pdfDoc || isRenderingPage || destroyed) {
                    return;
                }

                saveCurrentPageFields();
                await renderPage(currentPage);
            }, 150);
        });

        fieldPaletteButtons.forEach((button) => {
            button.setAttribute('draggable', 'true');

            listen(button, 'dragstart', (event) => {
                dragFieldType = button.dataset.fieldType || 'signature';
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'copy';
                    event.dataTransfer.setData('text/plain', dragFieldType);
                }
                setShellDropHighlight(true);
                debugLog('Field drag started', { type: dragFieldType });
            });

            listen(button, 'dragend', () => {
                debugLog('Field drag ended', { type: dragFieldType });
                dragFieldType = null;
                setShellDropHighlight(false);
            });

            listen(button, 'click', () => {
                const type = button.dataset.fieldType || 'signature';
                debugLog('Field add requested from palette click', { type });
                addField(type);
            });
        });

        listen(pdfShell, 'dragover', (event) => {
            if (!dragFieldType || !hasAvailableSigner() || isRenderingPage) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            setShellDropHighlight(true);
        });

        listen(pdfShell, 'dragleave', () => {
            if (!dragFieldType) {
                return;
            }

            setShellDropHighlight(false);
        });

        listen(pdfShell, 'drop', (event) => {
            if (!dragFieldType || !hasAvailableSigner() || isRenderingPage) {
                return;
            }

            event.preventDefault();
            setShellDropHighlight(false);

            if (!canvasContainsClientPoint(event.clientX, event.clientY, fabricEl)) {
                dragFieldType = null;
                return;
            }

            addField(dragFieldType, { x: event.clientX, y: event.clientY });
            dragFieldType = null;
        });

        listen(btnSaveFields, 'click', () => {
            if (!fabricCanvas || isRenderingPage || !fieldsPayload || !saveFieldsForm) {
                return;
            }

            isSaving = true;
            updateEditorStatus();

            const fields = collectFields();
            fieldsPayload.value = JSON.stringify(fields);
            saveFieldsForm.submit();
        });

        listen(selectedFieldType, 'change', () => {
            replaceActiveField({ type: selectedFieldType.value });
        });

        listen(selectedFieldSigner, 'change', () => {
            replaceActiveField({ signerId: Number(selectedFieldSigner.value) });
        });

        listen(btnDeleteField, 'click', () => {
            deleteActiveField();
        });

        listen(btnDuplicateField, 'click', () => {
            duplicateField(activeFieldObject());
        });

        listen(btnBringForward, 'click', () => {
            moveActiveFieldLayer('forward');
        });

        listen(btnSendBackward, 'click', () => {
            moveActiveFieldLayer('backward');
        });

        listen(window, 'keydown', (event) => {
            if (!fabricCanvas || isRenderingPage) {
                return;
            }

            const ctrlOrMeta = event.ctrlKey || event.metaKey;

            if (ctrlOrMeta && event.key.toLowerCase() === 'v' && copiedField?.position) {
                event.preventDefault();

                const copy = buildFieldGroup(
                    copiedField.fieldType,
                    copiedField.signerId,
                    {
                        x: Math.min(0.99 - copiedField.position.width, copiedField.position.x + 0.02),
                        y: Math.min(0.99 - copiedField.position.height, copiedField.position.y + 0.02),
                        width: copiedField.position.width,
                        height: copiedField.position.height,
                    },
                    nextClientFieldId(),
                );

                fabricCanvas.add(copy);
                fabricCanvas.setActiveObject(copy);
                selectedFieldClientId = copy.clientFieldId;
                saveCurrentPageFields();
                markDirty();
                setFieldInspectorState(copy);
                fabricCanvas.requestRenderAll();
                return;
            }

            const active = fabricCanvas.getActiveObject();

            if (event.key === 'Escape') {
                clearSnapGuide();
                setShellDropHighlight(false);
                fabricCanvas.discardActiveObject();
                selectedFieldClientId = null;
                setFieldInspectorState(null);
                fabricCanvas.requestRenderAll();
                return;
            }

            if (!active || !active.fieldType) {
                return;
            }

            if (ctrlOrMeta && event.key.toLowerCase() === 'c') {
                event.preventDefault();
                copiedField = {
                    fieldType: active.fieldType,
                    signerId: active.signerId,
                    position: normalizedPositionFromObject(active, fabricCanvas),
                };
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                deleteActiveField();
                return;
            }

            const step = event.shiftKey ? 10 : 2;

            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(event.key)) {
                event.preventDefault();

                if (event.key === 'ArrowUp') {
                    active.top -= step;
                } else if (event.key === 'ArrowDown') {
                    active.top += step;
                } else if (event.key === 'ArrowLeft') {
                    active.left -= step;
                } else if (event.key === 'ArrowRight') {
                    active.left += step;
                }

                keepObjectInsideCanvas(active, fabricCanvas);
                active.setCoords();
                fabricCanvas.requestRenderAll();
                saveCurrentPageFields();
                markDirty();
            }
        });

        listen(window, 'beforeunload', (event) => {
            if (!hasUnsavedChanges) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
    }

    const api = {
        destroy,
        init,
        isDestroyed,
        owns,
        showLoadError,
    };

    return api;
}
