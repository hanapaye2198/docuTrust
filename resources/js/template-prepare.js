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
    const editorLocked = Boolean(config.editorLocked);
    const initialPage = Number(config.initialPage) > 0 ? Number(config.initialPage) : 1;
    const signerPagesUrl = config.signerPagesUrl || '';
    const pdfCanvas = document.getElementById('pdf-canvas');
    const fabricEl = document.getElementById('fabric-canvas');
    const pdfShell = document.getElementById('pdf-shell');
    const pdfPanel = document.getElementById('pdf-stage') || pdfShell?.parentElement;
    const pdfLoadError = document.getElementById('pdf-load-error');
    const pdfLoadingIndicator = document.getElementById('pdf-loading-indicator');
    const pdfLoadingLabel = document.getElementById('pdf-loading-label');
    const pdfLoadingProgress = document.getElementById('pdf-loading-progress');

    const pageIndicator = document.getElementById('page-indicator');
    const btnPrevPage = document.getElementById('btn-prev-page');
    const btnNextPage = document.getElementById('btn-next-page');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnZoomReset = document.getElementById('btn-zoom-reset');
    const zoomIndicator = document.getElementById('zoom-indicator');
    const btnSaveFields = document.getElementById('btn-save-fields');
    const btnSendToSigner = document.getElementById('btn-send-to-signer');
    const editorStatus = document.getElementById('editor-status');
    const fieldSigner = document.getElementById('field-signer');
    const signerAssignmentButtons = Array.from(document.querySelectorAll('.signer-assignment-btn'));
    const fieldPaletteButtons = Array.from(document.querySelectorAll('.field-palette-btn'));
    const fieldInspectorBody = document.getElementById('field-inspector-body');
    const fieldInspectorEmpty = document.getElementById('field-inspector-empty');
    const selectedFieldType = document.getElementById('selected-field-type');
    const selectedFieldSigner = document.getElementById('selected-field-signer');
    const selectedFieldAngle = document.getElementById('selected-field-angle');
    const btnRotate0 = document.getElementById('btn-rotate-0');
    const btnRotate90 = document.getElementById('btn-rotate-90');
    const btnRotate180 = document.getElementById('btn-rotate-180');
    const btnRotate270 = document.getElementById('btn-rotate-270');
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
    let isLoadingPageFields = false;
    let hasUnsavedChanges = false;
    let isSaving = false;
    let dragFieldType = null;
    let selectedFieldClientId = null;
    let clientFieldCounter = 0;
    let currentSnapGuide = null;
    let resizeTimer = null;
    let renderSequence = 0;
    let destroyed = false;
    let isSubmittingSave = false;
    let zoomMultiplier = 1;

    const pageFields = new Map();
    const signerById = new Map(signers.map((signer) => [Number(signer.id), signer]));
    const signerPageAssignments = new Map(signers.map((signer) => [Number(signer.id), signer.allowed_pages || null]));
    const canSendFromServer = Boolean(btnSendToSigner) && !btnSendToSigner.disabled;
    const signerColorPalette = [
        { accent: '#3b82f6', stroke: '#2563eb', text: '#1d4ed8', fill: 'rgba(59, 130, 246, 0.12)' },
        { accent: '#8b5cf6', stroke: '#7c3aed', text: '#6d28d9', fill: 'rgba(139, 92, 246, 0.12)' },
        { accent: '#10b981', stroke: '#059669', text: '#047857', fill: 'rgba(16, 185, 129, 0.12)' },
        { accent: '#f59e0b', stroke: '#d97706', text: '#b45309', fill: 'rgba(245, 158, 11, 0.14)' },
        { accent: '#f43f5e', stroke: '#e11d48', text: '#be123c', fill: 'rgba(244, 63, 94, 0.12)' },
        { accent: '#06b6d4', stroke: '#0891b2', text: '#0e7490', fill: 'rgba(6, 182, 212, 0.12)' },
    ];
    const signerColorById = new Map(signers.map((signer, index) => [
        Number(signer.id),
        signerColorPalette[index % signerColorPalette.length],
    ]));
    const minZoomMultiplier = 0.5;
    const maxZoomMultiplier = 2;
    const zoomStep = 0.1;

    function debugLog(message, payload) {
        if (import.meta.env.DEV) {
            console.log(debugPrefix, message, payload ?? '');
        }
    }

    function isEditableTarget(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        return target.isContentEditable
            || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
            || Boolean(target.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""], [role="dialog"]'));
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

    function isSinglePlacementType(type) {
        return ['signature', 'signature_left', 'signature_right', 'seal'].includes(String(type || ''));
    }

    function isSignaturePlacementType(type) {
        return ['signature', 'signature_left', 'signature_right'].includes(String(type || ''));
    }

    function hasSinglePlacementForSigner(type, signerId, ignoredClientFieldId = null) {
        if (!fabricCanvas || !Number.isFinite(Number(signerId))) {
            return false;
        }

        const normalizedType = String(type || '');
        return fabricCanvas.getObjects().some((object) => {
            if (!object?.fieldType) {
                return false;
            }

            if (normalizedType === 'seal') {
                if (object.fieldType !== 'seal') {
                    return false;
                }
            } else if (!['signature', 'signature_left', 'signature_right'].includes(object.fieldType)) {
                return false;
            }

            if (ignoredClientFieldId !== null && object.clientFieldId === ignoredClientFieldId) {
                return false;
            }

            return Number(object.signerId) === Number(signerId);
        });
    }

    function showDuplicatePlacementMessage(type, signerId) {
        const signerName = signerNameFor(signerId);
        const fieldLabel = String(type || '') === 'seal' ? 'seal' : 'signature';
        showPdfLoadError(`${signerName} already has a ${fieldLabel} field on this page. Move the existing ${fieldLabel} instead.`);
    }

    function rectanglesOverlap(first, second) {
        const padding = 0.008;

        return first.x < second.x + second.width + padding
            && first.x + first.width + padding > second.x
            && first.y < second.y + second.height + padding
            && first.y + first.height + padding > second.y;
    }

    function positionOverlapsExistingField(position) {
        if (!fabricCanvas) {
            return false;
        }

        return fabricCanvas.getObjects().some((object) => {
            if (!object?.fieldType) {
                return false;
            }

            const existingPosition = normalizedPositionFromObject(object, fabricCanvas);
            return existingPosition ? rectanglesOverlap(position, existingPosition) : false;
        });
    }

    function clampFieldPosition(position, width, height) {
        return {
            x: Math.max(0.01, Math.min(0.99 - width, position.x)),
            y: Math.max(0.01, Math.min(0.99 - height, position.y)),
            width,
            height,
        };
    }

    function resolveNonOverlappingPosition(basePosition, width, height) {
        const stepX = Math.min(0.34, width + 0.03);
        const stepY = Math.min(0.18, height + 0.03);
        const candidates = [];

        for (let row = 0; row <= 6; row += 1) {
            for (let col = 0; col <= 4; col += 1) {
                candidates.push({ x: basePosition.x + (col * stepX), y: basePosition.y + (row * stepY) });

                if (col > 0) {
                    candidates.push({ x: basePosition.x - (col * stepX), y: basePosition.y + (row * stepY) });
                }
            }
        }

        for (const candidate of candidates) {
            const clamped = clampFieldPosition(candidate, width, height);

            if (!positionOverlapsExistingField(clamped)) {
                return clamped;
            }
        }

        return clampFieldPosition(basePosition, width, height);
    }

    function updateSignerAssignmentButtons() {
        const selectedId = selectedSignerId();

        signerAssignmentButtons.forEach((button) => {
            const signerId = Number(button.dataset.signerId);
            const signerColor = signerColorById.get(signerId);
            const isSelected = signerId === selectedId;
            const dot = button.querySelector('[data-signer-color-dot]');

            if (dot && signerColor) {
                dot.style.backgroundColor = signerColor.accent;
            }

            button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            button.classList.toggle('border-blue-300', isSelected);
            button.classList.toggle('bg-blue-50', isSelected);
            button.classList.toggle('ring-2', isSelected);
            button.classList.toggle('ring-blue-500/20', isSelected);
            button.classList.toggle('dark:border-blue-800', isSelected);
            button.classList.toggle('dark:bg-blue-950/30', isSelected);

            if (isSelected && signerColor) {
                button.style.borderColor = signerColor.stroke;
                button.style.boxShadow = `0 0 0 2px ${signerColor.fill}`;
            } else {
                button.style.borderColor = '';
                button.style.boxShadow = '';
            }
        });
    }

    function selectSignerById(signerId) {
        const normalizedSignerId = Number(signerId);
        if (!fieldSigner || !Number.isFinite(normalizedSignerId) || normalizedSignerId <= 0) {
            return;
        }

        const option = Array.from(fieldSigner.options).find((candidate) => Number(candidate.value) === normalizedSignerId);
        if (!option || option.disabled) {
            return;
        }

        fieldSigner.value = String(normalizedSignerId);
        updateSignerAssignmentButtons();
    }

    function normalizedZoomMultiplier(value) {
        const roundedValue = Math.round((Number(value) || 1) * 10) / 10;

        return Math.min(maxZoomMultiplier, Math.max(minZoomMultiplier, roundedValue));
    }

    function updateZoomUi() {
        if (zoomIndicator) {
            zoomIndicator.textContent = `${Math.round(zoomMultiplier * 100)}%`;
        }

        if (btnZoomOut) {
            btnZoomOut.disabled = isRenderingPage || zoomMultiplier <= minZoomMultiplier;
        }

        if (btnZoomIn) {
            btnZoomIn.disabled = isRenderingPage || zoomMultiplier >= maxZoomMultiplier;
        }

        if (btnZoomReset) {
            btnZoomReset.disabled = isRenderingPage || zoomMultiplier === 1;
        }
    }

    async function setZoomMultiplier(value) {
        const nextZoomMultiplier = normalizedZoomMultiplier(value);

        if (nextZoomMultiplier === zoomMultiplier || isRenderingPage || destroyed) {
            updateZoomUi();
            return;
        }

        saveCurrentPageFields();
        zoomMultiplier = nextZoomMultiplier;
        updateZoomUi();
        await renderPage(currentPage);
    }

    /**
     * Get signers allowed on the current page.
     * A signer with allowed_pages = null is allowed on all pages.
     */
    function signersForCurrentPage() {
        return signers.filter((signer) => {
            const pages = signerPageAssignments.get(Number(signer.id));
            if (pages === null || pages === undefined) {
                return true; // all pages
            }
            return Array.isArray(pages) && pages.includes(currentPage);
        });
    }

    /**
     * Update the signer dropdown to only show signers allowed on the current page.
     */
    function filterSignerDropdownForPage() {
        if (!fieldSigner) {
            return;
        }

        const allowedSigners = signersForCurrentPage();
        const previousValue = Number(fieldSigner.value);

        // Clear and rebuild options
        fieldSigner.innerHTML = '';
        allowedSigners.forEach((signer) => {
            const option = document.createElement('option');
            option.value = String(signer.id);
            option.textContent = signer.name + (signer.email ? ' - ' + signer.email : '');
            fieldSigner.appendChild(option);
        });

        // Try to preserve the previous selection
        if (allowedSigners.some((s) => Number(s.id) === previousValue)) {
            fieldSigner.value = String(previousValue);
        } else if (allowedSigners.length > 0) {
            fieldSigner.value = String(allowedSigners[0].id);
        }

        updateSignerAssignmentButtons();
    }

    function signerNameFor(id) {
        return signerById.get(Number(id))?.name || 'Signer';
    }

    function signerStyleForField(type, signerId) {
        if (!isSignaturePlacementType(type)) {
            return {};
        }

        return signerColorById.get(Number(signerId)) || {};
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

    function updateSendButtonState() {
        if (!btnSendToSigner || destroyed) {
            return;
        }

        btnSendToSigner.disabled = !canSendFromServer || hasUnsavedChanges || isSaving || isSubmittingSave || isRenderingPage;
        btnSendToSigner.title = hasUnsavedChanges
            ? (msgs.saveBeforeSend || 'Save your latest field changes before sending to signer.')
            : '';
    }

    function markDirty() {
        hasUnsavedChanges = true;
        updateEditorStatus();
        updateSendButtonState();
    }

    function markSaved() {
        hasUnsavedChanges = false;
        isSaving = false;
        updateEditorStatus();
        updateSendButtonState();
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

        if (selectedFieldAngle) {
            selectedFieldAngle.value = String(Math.round(Number(target.angle) || 0));
        }
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
        setPdfLoadingState(true, {
            label: msgs.loadFailed || 'Unable to load document preview. Please refresh the page and try again.',
            progress: '',
        });
    }

    function formatRenderingPageMessage(pageNumber, pageCount) {
        return (msgs.renderingPage || 'Rendering page :page of :total...')
            .replace('__PAGE__', String(pageNumber))
            .replace('__TOTAL__', String(pageCount));
    }

    function setPdfLoadingState(active, { label = '', progress = '' } = {}) {
        if (!pdfLoadingIndicator || destroyed) {
            return;
        }

        pdfLoadingIndicator.classList.toggle('hidden', !active);

        if (pdfLoadingLabel) {
            pdfLoadingLabel.textContent = label || msgs.loadingDocument || 'Loading document...';
        }

        if (pdfLoadingProgress) {
            pdfLoadingProgress.textContent = progress || msgs.loadingProgress || 'Preparing secure preview';
        }
    }

    function setShellDropHighlight(active) {
        if (!pdfShell || destroyed) {
            return;
        }

        pdfShell.classList.toggle('ring-2', active);
        pdfShell.classList.toggle('ring-teal-400', active);
    }

    function applyFieldEditability(fieldObject) {
        if (!fieldObject) {
            return;
        }

        fieldObject.set({
            selectable: !editorLocked,
            evented: !editorLocked,
            hasControls: !editorLocked,
            hasBorders: !editorLocked,
            lockMovementX: editorLocked,
            lockMovementY: editorLocked,
            lockScalingX: editorLocked,
            lockScalingY: editorLocked,
            lockRotation: editorLocked,
            hoverCursor: editorLocked ? 'default' : 'move',
        });
    }

    function buildFieldGroup(type, signerId, position, clientFieldId) {
        const group = createFieldGroup({
            fabric: window.fabric,
            fabricCanvas,
            type,
            signerId,
            signerName: signerNameFor(signerId),
            fieldStyle: signerStyleForField(type, signerId),
            position,
            pageNumber: currentPage,
            clientFieldId: clientFieldId || nextClientFieldId(),
        });

        applyFieldEditability(group);

        return group;
    }

    function saveCurrentPageFields() {
        if (!fabricCanvas || destroyed || isLoadingPageFields) {
            return;
        }

        pageFields.set(currentPage, serializeCanvasFields(fabricCanvas));
    }

    function loadCurrentPageFields() {
        if (!fabricCanvas || destroyed) {
            return;
        }

        isLoadingPageFields = true;
        fabricCanvas.clear();

        const fields = pageFields.get(currentPage) || [];
        fields.forEach((field) => {
            const group = buildFieldGroup(field.type, field.signer_id, field.position_data, field.client_id);
            fabricCanvas.add(group);
        });

        isLoadingPageFields = false;

        if (editorLocked) {
            selectedFieldClientId = null;
            fabricCanvas.selection = false;
            fabricCanvas.discardActiveObject();
            setFieldInspectorState(null);

            return;
        }

        restoreSelectionByClientId(fabricCanvas, selectedFieldClientId);
    }

    function replaceActiveField(updates) {
        if (editorLocked) {
            return;
        }

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

        if (isSinglePlacementType(fieldType) && hasSinglePlacementForSigner(fieldType, signerId, clientFieldId)) {
            showDuplicatePlacementMessage(fieldType, signerId);
            setFieldInspectorState(active);
            return;
        }

        const nextPosition = {
            ...normalized,
            angle: updates?.angle ?? normalized.angle ?? 0,
        };

        fabricCanvas.remove(active);

        const replacement = buildFieldGroup(fieldType, signerId, nextPosition, clientFieldId);
        fabricCanvas.add(replacement);
        fabricCanvas.setActiveObject(replacement);
        selectedFieldClientId = replacement.clientFieldId;

        saveCurrentPageFields();
        markDirty();
        setFieldInspectorState(replacement);
        fabricCanvas.requestRenderAll();
    }

    function deleteActiveField() {
        if (editorLocked) {
            return;
        }

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

    function moveActiveFieldLayer(direction) {
        if (editorLocked) {
            return;
        }

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
        if (editorLocked || !hasAvailableSigner() || !fabricCanvas || isRenderingPage) {
            debugLog('Add field blocked', {
                editorLocked,
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
        const signerId = selectedSignerId();

        if (isSinglePlacementType(type) && hasSinglePlacementForSigner(type, signerId)) {
            showDuplicatePlacementMessage(type, signerId);
            return;
        }

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
        const placementPosition = dropPoint
            ? visiblePosition
            : resolveNonOverlappingPosition(visiblePosition, width, height);

        const field = buildFieldGroup(
            type,
            signerId,
            {
                x: placementPosition.x,
                y: placementPosition.y,
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
            position: placementPosition,
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

        updateZoomUi();

        // Filter signer dropdown based on current page assignments
        filterSignerDropdownForPage();

        const shouldDisableFieldButtons = editorLocked || !hasAvailableSigner() || isRenderingPage;

        if (editorLocked) {
            debugLog('Buttons disabled: editor locked');
        } else if (!hasAvailableSigner()) {
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

        signerAssignmentButtons.forEach((button) => {
            const signerId = Number(button.dataset.signerId);
            const isAllowedOnPage = signersForCurrentPage().some((signer) => Number(signer.id) === signerId);
            button.disabled = shouldDisableFieldButtons || !isAllowedOnPage;
            button.classList.toggle('opacity-45', !isAllowedOnPage);
            button.classList.toggle('cursor-not-allowed', shouldDisableFieldButtons || !isAllowedOnPage);
        });

        if (btnSaveFields) {
            btnSaveFields.disabled = shouldDisableFieldButtons;
        }

        updateSendButtonState();
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
        setPdfLoadingState(true, {
            label: msgs.loadingDocument || 'Loading document...',
            progress: formatRenderingPageMessage(pageNumber, totalPages || 1),
        });
        updatePageUi();

        try {
            debugLog('Rendering page', { pageNumber });

            const page = await pdfDoc.getPage(pageNumber);
            if (destroyed || requestId !== renderSequence) {
                return;
            }

            const viewport = page.getViewport({
                scale: resolveRenderScale(page, pdfPanel ? pdfPanel.clientWidth : 0) * zoomMultiplier,
            });

            const ctx = pdfCanvas?.getContext('2d');
            if (!ctx || !pdfCanvas || !fabricEl || !pdfShell) {
                throw new Error('Missing required DOM nodes');
            }

            pdfCanvas.width = viewport.width;
            pdfCanvas.height = viewport.height;
            pdfCanvas.style.width = `${viewport.width}px`;
            pdfCanvas.style.height = `${viewport.height}px`;
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
                // Use requestAnimationFrame to ensure the browser has reflowed
                // after canvas resize before re-enabling field interactions.
                // This prevents the issue where page 2+ fields cannot be placed
                // because getBoundingClientRect() returns stale values.
                requestAnimationFrame(() => {
                    if (destroyed || requestId !== renderSequence) {
                        return;
                    }

                    isRenderingPage = false;
                    setPdfLoadingState(false);
                    updatePageUi();
                    debugLog('Rendering complete', { pageNumber, isRenderingPage });
                });
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

        setPdfLoadingState(true, {
            label: msgs.loadingDocument || 'Loading document...',
            progress: msgs.loadingProgress || 'Preparing secure preview',
        });
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
        loadingTask.onProgress = (progress) => {
            if (destroyed) {
                return;
            }

            const loaded = Number(progress?.loaded || 0);
            const total = Number(progress?.total || 0);
            const percent = total > 0 ? Math.max(1, Math.min(100, Math.round((loaded / total) * 100))) : null;

            setPdfLoadingState(true, {
                label: msgs.loadingDocument || 'Loading document...',
                progress: percent === null
                    ? (msgs.loadingProgress || 'Preparing secure preview')
                    : `${msgs.loadingProgress || 'Preparing secure preview'} ${percent}%`,
            });
        };

        pdfDoc = await loadingTask.promise;
        if (destroyed) {
            return;
        }

        totalPages = pdfDoc.numPages || 1;
        currentPage = Math.min(initialPage, totalPages);

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
        setPdfLoadingState(false);

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

        listen(btnZoomOut, 'click', async () => {
            await setZoomMultiplier(zoomMultiplier - zoomStep);
        });

        listen(btnZoomIn, 'click', async () => {
            await setZoomMultiplier(zoomMultiplier + zoomStep);
        });

        listen(btnZoomReset, 'click', async () => {
            await setZoomMultiplier(1);
        });

        // Safety net: if isRenderingPage stays true for more than 5 seconds
        // after a page change, force-reset it. This prevents the UI from
        // becoming permanently locked due to race conditions.
        let renderingSafetyTimer = null;

        function startRenderingSafetyNet() {
            if (renderingSafetyTimer) {
                clearTimeout(renderingSafetyTimer);
            }

            renderingSafetyTimer = setTimeout(() => {
                if (!destroyed && isRenderingPage) {
                    debugLog('Safety net: force-resetting isRenderingPage after timeout');
                    isRenderingPage = false;
                    setPdfLoadingState(false);
                    updatePageUi();
                }
            }, 5000);
        }

        // Patch the page navigation to trigger the safety net
        const originalBtnPrevHandler = btnPrevPage?.onclick;
        const originalBtnNextHandler = btnNextPage?.onclick;

        listen(btnPrevPage, 'click', startRenderingSafetyNet);
        listen(btnNextPage, 'click', startRenderingSafetyNet);

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

        signerAssignmentButtons.forEach((button) => {
            listen(button, 'click', () => {
                selectSignerById(button.dataset.signerId);
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
            isSubmittingSave = true;
            hasUnsavedChanges = false;
            updateEditorStatus();
            updateSendButtonState();

            const fields = collectFields();
            fieldsPayload.value = JSON.stringify(fields);

            // Append current page number so the redirect returns to the same page
            let pageInput = saveFieldsForm.querySelector('input[name="return_to_page"]');
            if (!pageInput) {
                pageInput = document.createElement('input');
                pageInput.type = 'hidden';
                pageInput.name = 'return_to_page';
                saveFieldsForm.appendChild(pageInput);
            }
            pageInput.value = String(currentPage);

            saveFieldsForm.submit();
        });

        listen(btnSendToSigner, 'click', (event) => {
            if (!hasUnsavedChanges) {
                return;
            }

            event.preventDefault();
            showPdfLoadError(msgs.saveBeforeSend || 'Save your latest field changes before sending to signer.');
        });

        listen(selectedFieldType, 'change', () => {
            replaceActiveField({ type: selectedFieldType.value });
        });

        listen(selectedFieldSigner, 'change', () => {
            replaceActiveField({ signerId: Number(selectedFieldSigner.value) });
        });

        // Rotation controls
        function applyRotationToActiveField(angle) {
            if (!activeFieldObject() || !fabricCanvas) {
                return;
            }

            replaceActiveField({ angle });

            if (selectedFieldAngle) {
                selectedFieldAngle.value = String(angle);
            }
        }

        listen(selectedFieldAngle, 'change', () => {
            const angle = Math.max(0, Math.min(360, Number(selectedFieldAngle.value) || 0));
            applyRotationToActiveField(angle);
        });

        listen(btnRotate0, 'click', () => applyRotationToActiveField(0));
        listen(btnRotate90, 'click', () => applyRotationToActiveField(90));
        listen(btnRotate180, 'click', () => applyRotationToActiveField(180));
        listen(btnRotate270, 'click', () => applyRotationToActiveField(270));

        listen(btnDeleteField, 'click', () => {
            deleteActiveField();
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

             if (isEditableTarget(event.target)) {
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
            if (isSubmittingSave || !hasUnsavedChanges) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        // --- Page assignment panel logic ---
        initPageAssignmentPanel();
    }

    function initPageAssignmentPanel() {
        const toggleBtn = document.getElementById('toggle-page-assignments');
        const body = document.getElementById('page-assignments-body');
        const chevron = document.getElementById('page-assignments-chevron');
        const listContainer = document.getElementById('page-assignments-list');
        const saveBtn = document.getElementById('btn-save-page-assignments');

        if (!toggleBtn || !body || !listContainer) {
            return;
        }

        // Toggle collapse
        listen(toggleBtn, 'click', () => {
            const isHidden = body.classList.contains('hidden');
            body.classList.toggle('hidden', !isHidden);
            if (chevron) {
                chevron.style.transform = isHidden ? 'rotate(180deg)' : '';
            }
        });

        // Build the assignment UI for each signer
        function renderAssignmentList() {
            listContainer.innerHTML = '';

            signers.forEach((signer) => {
                const signerId = Number(signer.id);
                const currentPages = signerPageAssignments.get(signerId);
                const isAllPages = currentPages === null || currentPages === undefined;

                const wrapper = document.createElement('div');
                wrapper.className = 'rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900/80';

                const nameLabel = document.createElement('p');
                nameLabel.className = 'text-xs font-semibold text-zinc-700 dark:text-zinc-200 mb-2';
                nameLabel.textContent = signer.name + (signer.email ? ' — ' + signer.email : '');
                wrapper.appendChild(nameLabel);

                // Dropdown: All pages / Specific pages
                const select = document.createElement('select');
                select.className = 'w-full rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-xs text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100';
                select.dataset.signerId = String(signerId);

                const optAll = document.createElement('option');
                optAll.value = 'all';
                optAll.textContent = 'All pages';
                optAll.selected = isAllPages;
                select.appendChild(optAll);

                const optSpecific = document.createElement('option');
                optSpecific.value = 'specific';
                optSpecific.textContent = 'Specific pages';
                optSpecific.selected = !isAllPages;
                select.appendChild(optSpecific);

                wrapper.appendChild(select);

                // Checkboxes container
                const checkboxContainer = document.createElement('div');
                checkboxContainer.className = 'mt-2 flex flex-wrap gap-2' + (isAllPages ? ' hidden' : '');
                checkboxContainer.dataset.signerId = String(signerId);

                for (let p = 1; p <= totalPages; p++) {
                    const label = document.createElement('label');
                    label.className = 'inline-flex items-center gap-1 text-xs text-zinc-600 dark:text-zinc-300 cursor-pointer';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = String(p);
                    checkbox.className = 'size-3.5 rounded border-zinc-300 text-teal-600 focus:ring-teal-500 dark:border-zinc-600 dark:bg-zinc-800';
                    checkbox.checked = isAllPages || (Array.isArray(currentPages) && currentPages.includes(p));
                    checkbox.dataset.signerId = String(signerId);
                    checkbox.dataset.page = String(p);

                    checkbox.addEventListener('change', () => {
                        updateSignerPagesFromCheckboxes(signerId);
                    });

                    const span = document.createElement('span');
                    span.textContent = 'P' + p;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    checkboxContainer.appendChild(label);
                }

                wrapper.appendChild(checkboxContainer);

                // Toggle checkboxes visibility
                select.addEventListener('change', () => {
                    if (select.value === 'all') {
                        checkboxContainer.classList.add('hidden');
                        signerPageAssignments.set(signerId, null);
                        filterSignerDropdownForPage();
                    } else {
                        checkboxContainer.classList.remove('hidden');
                        updateSignerPagesFromCheckboxes(signerId);
                    }
                });

                listContainer.appendChild(wrapper);
            });
        }

        function updateSignerPagesFromCheckboxes(signerId) {
            const checkboxes = listContainer.querySelectorAll(`input[data-signer-id="${signerId}"]`);
            const pages = [];
            checkboxes.forEach((cb) => {
                if (cb.checked) {
                    pages.push(Number(cb.dataset.page));
                }
            });

            // If all pages are checked, treat as "all pages"
            if (pages.length === totalPages) {
                signerPageAssignments.set(signerId, null);
            } else {
                signerPageAssignments.set(signerId, pages.length > 0 ? pages : null);
            }

            filterSignerDropdownForPage();
        }

        // Save button
        if (saveBtn && signerPagesUrl) {
            listen(saveBtn, 'click', async () => {
                saveBtn.disabled = true;
                saveBtn.textContent = msgs.saving || 'Saving...';

                const assignments = {};
                signerPageAssignments.forEach((pages, signerId) => {
                    assignments[String(signerId)] = pages;
                });

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch(signerPagesUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ assignments }),
                    });

                    if (response.ok) {
                        saveBtn.textContent = msgs.pageAssignmentsSaved || 'Page assignments saved.';
                        setTimeout(() => {
                            saveBtn.textContent = 'Save assignments';
                            saveBtn.disabled = false;
                        }, 2000);
                    } else {
                        throw new Error('Save failed');
                    }
                } catch {
                    saveBtn.textContent = msgs.pageAssignmentsFailed || 'Failed to save.';
                    saveBtn.disabled = false;
                    setTimeout(() => {
                        saveBtn.textContent = 'Save assignments';
                    }, 2000);
                }
            });
        }

        // Render after PDF is loaded (totalPages is known)
        renderAssignmentList();
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
