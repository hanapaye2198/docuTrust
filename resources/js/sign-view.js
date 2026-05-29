import { ensureSignAssets } from './sign-assets';

let activeSignView = null;

export function initSignView() {
    const cfgEl = document.getElementById('sign-view-config');

    if (!cfgEl) {
        activeSignView?.destroy();
        activeSignView = null;
        return;
    }

    if (activeSignView?.owns(cfgEl)) {
        return;
    }

    activeSignView?.destroy();

    const session = createSignViewSession(cfgEl);
    activeSignView = session;

    session.init().catch((error) => {
        if (activeSignView !== session || session.isDestroyed()) {
            return;
        }

        console.error(error);
        session.setPdfLoadingState(true, {
            label: session.messages.loadFailed || 'Unable to load the document. Please refresh the page and try again.',
            progress: '',
        });
        session.showFeedback(session.messages.loadFailed || session.messages.genericSaveError, 'error');
    });
}

function createSignViewSession(cfgEl) {
    let config = {};
    try {
        config = JSON.parse(cfgEl.textContent || '{}');
    } catch {
        config = {};
    }

    const messages = {
        signed: 'Signed',
        fieldSaved: 'Field saved.',
        genericSaveError: 'Unable to save your signature right now. Please try again.',
        drawRequired: 'Please draw your signature.',
        textRequired: 'Please enter the required text.',
        uploadRequired: 'Please choose an image.',
        progressPending: 'The next required field is highlighted on the page.',
        progressDone: 'All assigned fields have been completed.',
        signatureFallbackText: 'Signature',
        signatureModalTitle: 'Add your signature',
        signatureModalDescription: 'Choose how you want to sign this field.',
        signatureTypeLabel: 'Your name',
        signatureTypePlaceholder: 'Type your name',
        signatureSubmitLabel: 'Apply signature',
        textModalTitle: 'Enter text',
        textModalDescription: 'Type the value that should appear in this field.',
        textTypeLabel: 'Field value',
        textTypePlaceholder: 'Enter text',
        textSubmitLabel: 'Apply text',
        ...config.messages,
    };

    const abortController = new AbortController();
    const { signal } = abortController;

    const pdfUrl = config.pdfUrl;
    const fieldsJson = Array.isArray(config.fieldsJson) ? config.fieldsJson : [];
    const signedByFieldId = { ...(config.signedByFieldId || {}) };
    const signerName = config.signerName || '';
    const signerEmail = config.signerEmail || '';
    const dateLocale = config.dateLocale || 'en-US';
    const canEditFields = Boolean(config.canEditFields);
    const trustAuthorizationConfig = config.trustAuthorization && typeof config.trustAuthorization === 'object'
        ? config.trustAuthorization
        : {};
    const trustAuthorizationEnabled = Boolean(trustAuthorizationConfig.enabled);

    const pdfCanvas = document.getElementById('pdf-canvas');
    const fabricEl = document.getElementById('fabric-canvas');
    const pdfShell = document.getElementById('pdf-shell');
    const pdfLoadingIndicator = document.getElementById('pdf-loading-indicator');
    const pdfLoadingLabel = document.getElementById('pdf-loading-label');
    const pdfLoadingProgress = document.getElementById('pdf-loading-progress');
    const modal = document.getElementById('sign-modal');
    const modalTitle = document.getElementById('sign-modal-title');
    const modalDescription = document.getElementById('sign-modal-description');
    const modalTabs = document.getElementById('sign-modal-tabs');
    const modalFieldId = document.getElementById('modal-field-id');
    const signForm = document.getElementById('sign-form');
    const pageIndicator = document.getElementById('page-indicator');
    const btnPrevPage = document.getElementById('btn-prev-page');
    const btnNextPage = document.getElementById('btn-next-page');
    const drawCanvasEl = document.getElementById('draw-canvas');
    const feedbackEl = document.getElementById('sign-feedback');
    const assignedFieldCountEl = document.getElementById('assigned-field-count');
    const completedFieldCountEl = document.getElementById('completed-field-count');
    const remainingFieldCountEl = document.getElementById('remaining-field-count');
    const signingProgressLabelEl = document.getElementById('signing-progress-label');
    const signingProgressBarEl = document.getElementById('signing-progress-bar');
    const signingProgressNoteEl = document.getElementById('signing-progress-note');
    const modalSubmitButton = document.getElementById('modal-submit');
    const typeInput = document.getElementById('type-input');
    const typeInputLabel = document.getElementById('type-input-label');
    const uploadInput = document.getElementById('upload-input');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const tabDraw = document.getElementById('tab-draw');
    const tabType = document.getElementById('tab-type');
    const tabUpload = document.getElementById('tab-upload');
    const panelDraw = document.getElementById('panel-draw');
    const panelType = document.getElementById('panel-type');
    const panelUpload = document.getElementById('panel-upload');
    const trustAuthorizationPanel = document.getElementById('trust-authorization-panel');
    const trustAuthorizationStatusEl = document.getElementById('trust-authorization-status');
    const trustAuthorizationProviderEl = document.getElementById('trust-authorization-provider');
    const trustAuthorizationCredentialEl = document.getElementById('trust-authorization-credential');
    const trustAuthorizationDetailEl = document.getElementById('trust-authorization-detail');
    const trustAuthorizationTimingEl = document.getElementById('trust-authorization-timing');
    const trustAuthorizationDescriptionEl = document.getElementById('trust-authorization-description');
    const trustAuthorizationStartButton = document.getElementById('trust-authorization-start');
    const legacySignSubmitButton = document.getElementById('legacy-sign-submit');
    const legacySignTrustNoteEl = document.getElementById('legacy-sign-trust-note');

    let fabricCanvas = null;
    let drawContext = null;
    let drawPointerId = null;
    let drawCanvasRect = null;
    let isDrawing = false;
    let hasDrawnStroke = false;
    let pendingDrawPoints = [];
    let drawLastPoint = null;
    let drawStrokeSegmentCount = 0;
    let drawFrameId = null;
    let isSubmitting = false;
    let pdfDoc = null;
    let currentPage = 1;
    let totalPages = 1;
    let isRenderingPage = false;
    let currentCanEditFields = canEditFields;
    let currentViewport = null;
    let currentModalFieldType = 'signature';
    let renderSequence = 0;
    let destroyed = false;
    let trustAuthorizationSession = trustAuthorizationConfig.currentSession || null;
    let trustAuthorizationStartInFlight = false;
    let trustAuthorizationPollTimerId = null;

    const renderScale = 1.5;
    const pdfPageCache = new Map();
    const signatureImageCache = new Map();
    const fieldObjectMap = new Map();

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

    function showFeedback(message, type) {
        if (!feedbackEl) {
            return;
        }

        if (!message) {
            feedbackEl.className = 'hidden rounded-2xl px-4 py-3 text-sm shadow-sm';
            feedbackEl.textContent = '';
            return;
        }

        const baseClasses = 'rounded-2xl px-4 py-3 text-sm shadow-sm';
        const variantClasses = type === 'error'
            ? ' border border-red-200/90 bg-red-50 text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100'
            : ' border border-emerald-200/90 bg-emerald-50 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-100';

        feedbackEl.className = baseClasses + variantClasses;
        feedbackEl.textContent = message;
    }

    function formatRenderingPageMessage(pageNumber, pageCount) {
        return (messages.renderingPage || 'Rendering page :page of :total...')
            .replace('__PAGE__', String(pageNumber))
            .replace('__TOTAL__', String(pageCount));
    }

    function setPdfLoadingState(active, { label = '', progress = '' } = {}) {
        if (!pdfLoadingIndicator) {
            return;
        }

        pdfLoadingIndicator.classList.toggle('hidden', !active);

        if (pdfLoadingLabel) {
            pdfLoadingLabel.textContent = label || messages.loadingDocument || 'Loading document...';
        }

        if (pdfLoadingProgress) {
            pdfLoadingProgress.textContent = progress || messages.loadingProgress || 'Preparing secure preview';
        }
    }

    function clearTrustAuthorizationPollTimer() {
        if (trustAuthorizationPollTimerId === null) {
            return;
        }

        window.clearTimeout(trustAuthorizationPollTimerId);
        trustAuthorizationPollTimerId = null;
    }

    function hasTrustAuthorizationCredential() {
        return typeof trustAuthorizationConfig.credentialId === 'string'
            && trustAuthorizationConfig.credentialId.trim() !== '';
    }

    function hasActiveTrustAuthorization() {
        if (!trustAuthorizationEnabled) {
            return true;
        }

        if (trustAuthorizationConfig.isActive === true && !trustAuthorizationSession) {
            return true;
        }

        if (!trustAuthorizationSession || trustAuthorizationSession.status !== 'authorized') {
            return false;
        }

        if (!trustAuthorizationSession.expires_at) {
            return true;
        }

        const expiresAt = new Date(trustAuthorizationSession.expires_at);
        return !Number.isNaN(expiresAt.getTime()) && expiresAt.getTime() > Date.now();
    }

    function trustAuthorizationPollUrl(sessionId) {
        const template = typeof trustAuthorizationConfig.pollUrlTemplate === 'string'
            ? trustAuthorizationConfig.pollUrlTemplate
            : '';

        return template.replace('__SESSION__', String(sessionId));
    }

    function trustAuthorizationTimingLabel(session) {
        if (!session) {
            return messages.trustAuthorizationTimingNone;
        }

        if (session.completed_at) {
            return `${messages.trustAuthorizationCompletedAt} ${session.completed_at}`;
        }

        if (session.expires_at) {
            return `${messages.trustAuthorizationExpiresAt} ${session.expires_at}`;
        }

        return messages.trustAuthorizationTimingNone;
    }

    function renderTrustAuthorizationSession() {
        if (!trustAuthorizationPanel) {
            renderLegacySignState();
            return;
        }

        const status = !trustAuthorizationSession
            ? 'not_started'
            : hasActiveTrustAuthorization()
                ? 'authorized'
                : trustAuthorizationSession.status === 'pending'
                    ? 'pending'
                    : trustAuthorizationSession.expires_at && new Date(trustAuthorizationSession.expires_at).getTime() <= Date.now()
                        ? 'expired'
                        : trustAuthorizationSession.status || 'not_started';

        const statusMap = {
            not_started: {
                label: messages.trustAuthorizationNotStarted,
                classes: 'inline-flex items-center rounded-full border border-amber-200 bg-amber-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200',
            },
            pending: {
                label: messages.trustAuthorizationPending,
                classes: 'inline-flex items-center rounded-full border border-sky-200 bg-sky-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/50 dark:text-sky-200',
            },
            authorized: {
                label: messages.trustAuthorizationAuthorized,
                classes: 'inline-flex items-center rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-200',
            },
            expired: {
                label: messages.trustAuthorizationExpired,
                classes: 'inline-flex items-center rounded-full border border-red-200 bg-red-100 px-2.5 py-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-red-800 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-200',
            },
        };

        const currentStatus = statusMap[status] || statusMap.not_started;
        const canStartAuthorization = Boolean(trustAuthorizationConfig.canStart) && hasTrustAuthorizationCredential();

        if (trustAuthorizationStatusEl) {
            trustAuthorizationStatusEl.className = currentStatus.classes;
            trustAuthorizationStatusEl.textContent = currentStatus.label;
        }

        if (trustAuthorizationProviderEl) {
            trustAuthorizationProviderEl.textContent = trustAuthorizationSession?.provider_name
                || trustAuthorizationConfig.providerName
                || 'remote_managed';
        }

        if (trustAuthorizationCredentialEl) {
            trustAuthorizationCredentialEl.textContent = hasTrustAuthorizationCredential()
                ? trustAuthorizationConfig.credentialId
                : messages.trustAuthorizationProviderMissing;
        }

        if (trustAuthorizationDetailEl) {
            trustAuthorizationDetailEl.textContent = trustAuthorizationSession?.authorization_reference
                || messages.trustAuthorizationSessionMissing;
        }

        if (trustAuthorizationTimingEl) {
            trustAuthorizationTimingEl.textContent = trustAuthorizationTimingLabel(trustAuthorizationSession);
        }

        if (trustAuthorizationDescriptionEl) {
            trustAuthorizationDescriptionEl.textContent = status === 'authorized'
                ? messages.trustAuthorizationReady
                : status === 'pending'
                    ? messages.trustAuthorizationInProgress
                    : messages.trustAuthorizationDescription;
        }

        if (trustAuthorizationStartButton) {
            trustAuthorizationStartButton.textContent = trustAuthorizationStartInFlight
                ? messages.trustAuthorizationStarting
                : status === 'authorized'
                    ? messages.trustAuthorizationRestart
                    : messages.trustAuthorizationStart;

            trustAuthorizationStartButton.disabled = trustAuthorizationStartInFlight || !canStartAuthorization;
        }

        renderLegacySignState();
    }

    function renderLegacySignState() {
        if (!legacySignSubmitButton && !legacySignTrustNoteEl) {
            return;
        }

        const active = hasActiveTrustAuthorization();

        if (legacySignSubmitButton) {
            legacySignSubmitButton.disabled = trustAuthorizationEnabled && !active;
        }

        if (legacySignTrustNoteEl) {
            legacySignTrustNoteEl.textContent = active
                ? messages.legacyTrustAuthorizationReady
                : messages.legacyTrustAuthorizationRequired;
        }
    }

    function scheduleTrustAuthorizationPoll(sessionId) {
        clearTrustAuthorizationPollTimer();

        trustAuthorizationPollTimerId = window.setTimeout(() => {
            pollTrustAuthorizationSession(sessionId).catch((error) => {
                showFeedback(error.message || messages.genericSaveError, 'error');
            });
        }, 3000);
    }

    async function pollTrustAuthorizationSession(sessionId) {
        if (!trustAuthorizationEnabled || !sessionId) {
            return;
        }

        const response = await fetch(trustAuthorizationPollUrl(sessionId), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }

        if (!response.ok) {
            throw new Error(payload?.message || messages.genericSaveError);
        }

        trustAuthorizationSession = payload?.session || null;
        renderTrustAuthorizationSession();

        if (trustAuthorizationSession?.status === 'pending') {
            scheduleTrustAuthorizationPoll(trustAuthorizationSession.id);
            return;
        }

        clearTrustAuthorizationPollTimer();
        showFeedback(payload?.message || messages.trustAuthorizationReady, 'success');
    }

    async function startTrustAuthorizationSession() {
        if (!trustAuthorizationEnabled || trustAuthorizationStartInFlight || !trustAuthorizationConfig.startUrl) {
            return;
        }

        trustAuthorizationStartInFlight = true;
        renderTrustAuthorizationSession();

        try {
            const unsignedFieldCount = orderedFields().filter((field) => !isSigned(field.id)).length;
            const response = await fetch(trustAuthorizationConfig.startUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    num_signatures: Math.max(1, unsignedFieldCount || 1),
                }),
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch {
                payload = null;
            }

            if (!response.ok) {
                throw new Error(payload?.message || messages.genericSaveError);
            }

            trustAuthorizationSession = payload?.session || null;
            renderTrustAuthorizationSession();
            showFeedback(payload?.message || messages.trustAuthorizationReady, 'success');

            if (trustAuthorizationSession?.status === 'pending') {
                scheduleTrustAuthorizationPoll(trustAuthorizationSession.id);
            } else {
                clearTrustAuthorizationPollTimer();
            }
        } finally {
            trustAuthorizationStartInFlight = false;
            renderTrustAuthorizationSession();
        }
    }

    function isSigned(fieldId) {
        return Object.prototype.hasOwnProperty.call(signedByFieldId, String(fieldId));
    }

    function orderedFields() {
        return [...fieldsJson].sort((a, b) => {
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
        return orderedFields().find((field) => !isSigned(field.id)) || null;
    }

    /**
     * Find the next unsigned field, preferring forward progress from the current page.
     * Checks current page first, then later pages, then wraps to earlier pages only if needed.
     */
    function nextUnsignedFieldForward() {
        const unsigned = orderedFields().filter((field) => !isSigned(field.id));
        if (unsigned.length === 0) {
            return null;
        }

        // First: unsigned field on current page or later
        const forward = unsigned.find((field) => {
            const page = Number(field.page_number) > 0 ? Number(field.page_number) : 1;
            return page >= currentPage;
        });

        if (forward) {
            return forward;
        }

        // Fallback: wrap to the first unsigned field (earlier pages)
        return unsigned[0];
    }

    function initialPageNumber() {
        const nextField = firstUnsignedField();
        if (!nextField) {
            return 1;
        }

        return Number(nextField.page_number) > 0 ? Number(nextField.page_number) : 1;
    }

    function fieldById(fieldId) {
        return fieldsJson.find((field) => String(field.id) === String(fieldId)) || null;
    }

    function modalFieldType(fieldId) {
        return fieldById(fieldId)?.type || 'signature';
    }

    function isTextFieldType(type) {
        return type === 'text';
    }

    function rectFromNormalized(position, cw, ch) {
        return {
            left: position.x * cw,
            top: position.y * ch,
            width: position.width * cw,
            height: position.height * ch,
            angle: Number(position.angle) || 0,
        };
    }

    function positionedGroupOptions(rect, extra = {}) {
        if (Math.abs(Number(rect.angle) || 0) > 0.01) {
            return {
                left: rect.left + (rect.width / 2),
                top: rect.top + (rect.height / 2),
                originX: 'center',
                originY: 'center',
                angle: rect.angle || 0,
                ...extra,
            };
        }

        return {
            left: rect.left,
            top: rect.top,
            originX: 'left',
            originY: 'top',
            angle: 0,
            ...extra,
        };
    }

    function getFieldChrome(type) {
        switch (type) {
            case 'signature_left':
                return { kind: 'signature', signatureAlignment: 'left', stroke: '#0f766e', fill: 'rgba(20, 184, 166, 0.12)', fillText: '#115e59', label: 'Signature' };
            case 'signature_right':
                return { kind: 'signature', signatureAlignment: 'right', stroke: '#0369a1', fill: 'rgba(14, 165, 233, 0.12)', fillText: '#075985', label: 'Signature' };
            case 'text':
                return { kind: 'input', stroke: '#ca8a04', fill: 'rgba(234, 179, 8, 0.15)', fillText: '#a16207', label: 'Text field' };
            case 'name':
                return { kind: 'input', stroke: '#15803d', fill: 'rgba(34, 197, 94, 0.15)', fillText: '#15803d', label: 'Name' };
            case 'date':
                return { kind: 'input', stroke: '#6d28d9', fill: 'rgba(139, 92, 246, 0.12)', fillText: '#5b21b6', label: 'Date' };
            case 'email':
                return { kind: 'input', stroke: '#be123c', fill: 'rgba(244, 63, 94, 0.10)', fillText: '#9f1239', label: 'Email' };
            case 'initials':
                return { kind: 'input', stroke: '#a21caf', fill: 'rgba(217, 70, 239, 0.10)', fillText: '#86198f', label: 'Initials' };
            case 'checkbox':
                return { kind: 'toggle', control: 'square', stroke: '#0284c7', fill: 'rgba(56, 189, 248, 0.14)', fillText: '#0369a1', label: 'Checkbox' };
            case 'radio':
                return { kind: 'toggle', control: 'circle', stroke: '#4f46e5', fill: 'rgba(99, 102, 241, 0.12)', fillText: '#4338ca', label: 'Radio' };
            default:
                return { kind: 'signature', signatureAlignment: 'center', stroke: '#2563eb', fill: 'rgba(59, 130, 246, 0.12)', fillText: '#1d4ed8', label: 'Signature' };
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

        return text.slice(0, Math.max(1, maxCharacters - 1)).trimEnd() + '...';
    }

    function inputFieldMetrics(rect) {
        const inset = Math.max(6, rect.height * 0.16);
        const accentWidth = clamp(rect.width * 0.055, 8, 14);
        const labelLeft = accentWidth + Math.max(10, rect.width * 0.05);
        const labelFontSize = clamp(rect.height * 0.22, 9, 12);
        const baselineY = rect.height - Math.max(5, rect.height * 0.16);
        const valueFontSize = clamp(rect.height * 0.24, 10, 17);
        const valueLeft = labelLeft;
        const valueWidth = Math.max(24, rect.width - valueLeft - inset);

        return {
            inset,
            accentWidth,
            labelLeft,
            labelFontSize,
            baselineY,
            valueFontSize,
            valueLeft,
            valueWidth,
        };
    }

    function buildFieldPreviewGroup(chrome, rect) {
        const fabric = window.fabric;
        const nodes = [];
        const inset = Math.max(6, rect.height * 0.16);

        if (chrome.kind === 'toggle') {
            const controlSize = clamp(rect.height * 0.5, 14, 20);
            const controlTop = (rect.height - controlSize) / 2;
            const labelLeft = inset + controlSize + Math.max(7, rect.width * 0.05);
            const labelFontSize = clamp(rect.height * 0.3, 10, 14);
            const usableWidth = Math.max(18, rect.width - labelLeft - inset);

            nodes.push(new fabric.Rect({ width: rect.width, height: rect.height, fill: chrome.fill, stroke: chrome.stroke, strokeWidth: 1.25, rx: 8, ry: 8 }));
            if (chrome.control === 'circle') {
                nodes.push(new fabric.Circle({ radius: controlSize / 2, left: inset, top: controlTop, fill: '#ffffff', stroke: chrome.stroke, strokeWidth: 1.5, originX: 'left', originY: 'top' }));
            } else {
                nodes.push(new fabric.Rect({ width: controlSize, height: controlSize, left: inset, top: controlTop, fill: '#ffffff', stroke: chrome.stroke, strokeWidth: 1.5, rx: 4, ry: 4, originX: 'left', originY: 'top' }));
            }
            nodes.push(new fabric.Text(truncateFieldText(chrome.label, usableWidth, labelFontSize), { fontSize: labelFontSize, fill: chrome.fillText, fontFamily: 'system-ui, sans-serif', fontWeight: 700, originX: 'left', originY: 'center', left: labelLeft, top: rect.height / 2 }));
        } else if (chrome.kind === 'input') {
            const metrics = inputFieldMetrics(rect);
            const ghostText = chrome.label === 'Email'
                ? 'value appears here'
                : chrome.label === 'Date'
                    ? 'date appears here'
                    : 'type here';

            nodes.push(new fabric.Rect({ width: rect.width, height: rect.height, fill: chrome.fill, stroke: chrome.stroke, strokeWidth: 1.5, rx: 8, ry: 8 }));
            nodes.push(new fabric.Rect({ width: metrics.accentWidth, height: rect.height, fill: chrome.stroke, rx: 8, ry: 8, left: 0, top: 0, originX: 'left', originY: 'top' }));
            nodes.push(new fabric.Text(truncateFieldText(chrome.label, metrics.valueWidth, metrics.labelFontSize), {
                fontSize: metrics.labelFontSize,
                fill: chrome.fillText,
                fontFamily: 'system-ui, sans-serif',
                fontWeight: 700,
                originX: 'left',
                originY: 'top',
                left: metrics.labelLeft,
                top: inset * 0.72,
            }));
            nodes.push(new fabric.Line([metrics.valueLeft, metrics.baselineY, rect.width - inset, metrics.baselineY], {
                stroke: chrome.stroke,
                strokeWidth: 1,
                selectable: false,
                evented: false,
                opacity: 0.42,
            }));
            nodes.push(new fabric.Line([metrics.valueLeft, metrics.baselineY - (metrics.valueFontSize * 0.9), metrics.valueLeft, metrics.baselineY + 1], {
                stroke: chrome.stroke,
                strokeWidth: 1,
                selectable: false,
                evented: false,
                opacity: 0.5,
            }));
            nodes.push(new fabric.Text(truncateFieldText(ghostText, metrics.valueWidth - 6, metrics.valueFontSize * 0.92), {
                fontSize: clamp(metrics.valueFontSize * 0.92, 9, 15),
                fill: chrome.stroke,
                opacity: 0.45,
                fontFamily: 'Georgia, serif',
                fontWeight: 400,
                originX: 'left',
                originY: 'bottom',
                left: metrics.valueLeft + 4,
                top: metrics.baselineY,
                textAlign: 'left',
            }));
        } else {
            const alignment = chrome.signatureAlignment || 'center';
            const accentWidth = clamp(rect.width * 0.07, 10, 18);
            const labelFontSize = clamp(rect.height * 0.28, 11, 16);
            const lineY = rect.height - Math.max(7, rect.height * 0.14);
            const textInset = Math.max(10, rect.width * 0.05);
            const leftLabelLeft = accentWidth + textInset;
            const rightLabelRight = rect.width - accentWidth - textInset;
            const centerLabelWidth = Math.max(24, rect.width - Math.max(inset * 2, rect.width * 0.32));
            const leftUsableWidth = Math.max(24, rect.width - leftLabelLeft - inset);
            const rightUsableWidth = Math.max(24, rightLabelRight - inset);

            nodes.push(new fabric.Rect({ width: rect.width, height: rect.height, fill: chrome.fill, stroke: chrome.stroke, strokeWidth: 1.5, rx: 8, ry: 8 }));
            if (alignment === 'right') {
                nodes.push(new fabric.Rect({ width: accentWidth, height: rect.height, fill: chrome.stroke, rx: 8, ry: 8, left: rect.width - accentWidth, top: 0, originX: 'left', originY: 'top' }));
                nodes.push(new fabric.Text(truncateFieldText(chrome.label, rightUsableWidth, labelFontSize), { fontSize: labelFontSize, fill: chrome.fillText, fontFamily: 'system-ui, sans-serif', fontWeight: 700, originX: 'right', originY: 'top', left: rightLabelRight, top: inset, textAlign: 'right' }));
                nodes.push(new fabric.Line([inset, lineY, rightLabelRight, lineY], { stroke: chrome.stroke, strokeWidth: 1, selectable: false, evented: false, opacity: 0.65 }));
            } else if (alignment === 'center') {
                const topBandWidth = Math.max(rect.width * 0.42, 44);
                const topBandHeight = clamp(rect.height * 0.16, 5, 9);
                const guideWidth = Math.max(rect.width * 0.58, 40);
                const guideLeft = (rect.width - guideWidth) / 2;

                nodes.push(new fabric.Rect({ width: topBandWidth, height: topBandHeight, fill: chrome.stroke, rx: 999, ry: 999, left: (rect.width - topBandWidth) / 2, top: inset * 0.6, originX: 'left', originY: 'top' }));
                nodes.push(new fabric.Text(truncateFieldText(chrome.label, centerLabelWidth, labelFontSize), { fontSize: labelFontSize, fill: chrome.fillText, fontFamily: 'system-ui, sans-serif', fontWeight: 700, originX: 'center', originY: 'top', left: rect.width / 2, top: inset + 3, textAlign: 'center' }));
                nodes.push(new fabric.Line([guideLeft, lineY, guideLeft + guideWidth, lineY], { stroke: chrome.stroke, strokeWidth: 1, selectable: false, evented: false, opacity: 0.65 }));
            } else {
                nodes.push(new fabric.Rect({ width: accentWidth, height: rect.height, fill: chrome.stroke, rx: 8, ry: 8, left: 0, top: 0, originX: 'left', originY: 'top' }));
                nodes.push(new fabric.Text(truncateFieldText(chrome.label, leftUsableWidth, labelFontSize), { fontSize: labelFontSize, fill: chrome.fillText, fontFamily: 'system-ui, sans-serif', fontWeight: 700, originX: 'left', originY: 'top', left: leftLabelLeft, top: inset }));
                nodes.push(new fabric.Line([leftLabelLeft, lineY, rect.width - inset, lineY], { stroke: chrome.stroke, strokeWidth: 1, selectable: false, evented: false, opacity: 0.65 }));
            }
        }

        return new fabric.Group(nodes, positionedGroupOptions(rect, { subTargetCheck: true }));
    }

    function signerInitialsValue() {
        return signerName.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'S';
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }

        assignedFieldCountEl && (assignedFieldCountEl.textContent = String(summary.assigned));
        completedFieldCountEl && (completedFieldCountEl.textContent = String(summary.completed));
        remainingFieldCountEl && (remainingFieldCountEl.textContent = String(summary.remaining));
        signingProgressLabelEl && (signingProgressLabelEl.textContent = `${summary.progress_percent}%`);
        if (signingProgressBarEl) {
            signingProgressBarEl.style.width = `${summary.progress_percent}%`;
        }
        if (signingProgressNoteEl) {
            signingProgressNoteEl.textContent = summary.remaining > 0 ? messages.progressPending : messages.progressDone;
        }

        currentCanEditFields = Boolean(summary.can_edit_fields);
    }

    function updatePageUi() {
        pageIndicator && (pageIndicator.textContent = `Page ${currentPage} / ${totalPages}`);
        btnPrevPage && (btnPrevPage.disabled = currentPage <= 1 || isRenderingPage);
        btnNextPage && (btnNextPage.disabled = currentPage >= totalPages || isRenderingPage);
    }

    function resetModalInputs() {
        if (typeInput) {
            typeInput.value = '';
        }
        if (uploadInput) {
            uploadInput.value = '';
        }
    }

    function configureModalForField(field) {
        const type = field?.type || 'signature';
        const isTextField = isTextFieldType(type);
        const signedField = field ? signedByFieldId[String(field.id)] || {} : {};

        currentModalFieldType = type;

        if (modalTitle) {
            modalTitle.textContent = isTextField ? messages.textModalTitle : messages.signatureModalTitle;
        }
        if (modalDescription) {
            modalDescription.textContent = isTextField ? messages.textModalDescription : messages.signatureModalDescription;
        }
        if (typeInputLabel) {
            typeInputLabel.textContent = isTextField ? messages.textTypeLabel : messages.signatureTypeLabel;
        }
        if (typeInput) {
            typeInput.placeholder = isTextField ? messages.textTypePlaceholder : messages.signatureTypePlaceholder;
            typeInput.autocomplete = isTextField ? 'off' : 'name';
            typeInput.value = isTextField
                ? String(signedField.submitted_value || '')
                : '';
        }
        if (modalSubmitButton) {
            modalSubmitButton.textContent = isTextField ? messages.textSubmitLabel : messages.signatureSubmitLabel;
        }
        if (modalTabs) {
            modalTabs.classList.toggle('hidden', isTextField);
        }
        if (tabDraw) {
            tabDraw.classList.toggle('hidden', isTextField);
        }
        if (tabUpload) {
            tabUpload.classList.toggle('hidden', isTextField);
        }

        if (isTextField) {
            panelDraw?.classList.add('hidden');
            panelUpload?.classList.add('hidden');
            showTab('type');
        } else {
            showTab('draw');
        }
    }

    function fieldsForCurrentPage() {
        return fieldsJson.filter((field) => (Number(field.page_number) > 0 ? Number(field.page_number) : 1) === currentPage);
    }

    function cachePdfPage(pageNumber) {
        const cachedCanvas = document.createElement('canvas');
        cachedCanvas.width = pdfCanvas.width;
        cachedCanvas.height = pdfCanvas.height;
        const cachedCtx = cachedCanvas.getContext('2d');
        if (!cachedCtx) {
            return;
        }

        cachedCtx.drawImage(pdfCanvas, 0, 0);
        pdfPageCache.set(pageNumber, cachedCanvas);
    }

    function paintCachedPdfPage(pageNumber) {
        const cachedCanvas = pdfPageCache.get(pageNumber);
        if (!cachedCanvas) {
            return false;
        }

        const ctx = pdfCanvas.getContext('2d');
        if (!ctx) {
            return false;
        }

        pdfCanvas.width = cachedCanvas.width;
        pdfCanvas.height = cachedCanvas.height;
        fabricEl.width = cachedCanvas.width;
        fabricEl.height = cachedCanvas.height;
        pdfCanvas.style.width = `${cachedCanvas.width}px`;
        pdfCanvas.style.height = `${cachedCanvas.height}px`;
        fabricEl.style.width = `${cachedCanvas.width}px`;
        fabricEl.style.height = `${cachedCanvas.height}px`;
        pdfShell.style.width = `${cachedCanvas.width}px`;
        pdfShell.style.height = `${cachedCanvas.height}px`;
        ctx.clearRect(0, 0, cachedCanvas.width, cachedCanvas.height);
        ctx.drawImage(cachedCanvas, 0, 0);
        currentViewport = { width: cachedCanvas.width, height: cachedCanvas.height };

        return true;
    }

    function registerFieldObject(fieldId, object) {
        if (!fieldObjectMap.has(fieldId)) {
            fieldObjectMap.set(fieldId, []);
        }

        fieldObjectMap.get(fieldId).push(object);
    }

    function clearFieldObjects(fieldId) {
        const objects = fieldObjectMap.get(fieldId) || [];
        objects.forEach((object) => fabricCanvas?.remove(object));
        fieldObjectMap.delete(fieldId);
    }

    function clearAllFieldObjects() {
        fieldObjectMap.forEach((objects) => objects.forEach((object) => fabricCanvas?.remove(object)));
        fieldObjectMap.clear();
    }

    function loadSignatureImage(url) {
        if (!url) {
            return Promise.resolve(null);
        }

        if (signatureImageCache.has(url)) {
            return signatureImageCache.get(url);
        }

        const imagePromise = new Promise((resolve) => {
            window.fabric.Image.fromURL(
                url,
                (img) => resolve(img || null),
                { crossOrigin: 'anonymous' },
            );
        });

        signatureImageCache.set(url, imagePromise);
        return imagePromise;
    }

    async function applySignedField(payload) {
        if (!payload?.field?.id) {
            return;
        }

        const previousPage = currentPage;
        const previousNextFieldId = firstUnsignedField()?.id ?? null;

        signedByFieldId[String(payload.field.id)] = {
            image_url: payload.field.image_url || null,
            submitted_value: payload.field.submitted_value || '',
        };

        updateSummary(payload.summary);
        modal.open && closeModal();
        showFeedback(payload.message || messages.fieldSaved, 'success');

        if (currentCanEditFields) {
            // Navigate to the next unsigned field, preferring forward progress.
            // First check for unsigned fields on the current page or later pages.
            // Only go back to earlier pages if there are no forward fields left.
            const nextField = nextUnsignedFieldForward();
            if (nextField) {
                currentPage = Number(nextField.page_number) > 0 ? Number(nextField.page_number) : currentPage;
            }
        }

        if (currentPage !== previousPage) {
            await renderPage(currentPage);
            return;
        }

        if (fabricCanvas && currentViewport) {
            const nextFieldId = firstUnsignedField()?.id ?? null;
            await renderFieldsByIds([payload.field.id, previousNextFieldId, nextFieldId].filter(Boolean), currentViewport.width, currentViewport.height);
        }
    }

    async function postSignatureField(fieldId, dataUrl, submittedValue = '') {
        const response = await fetch(signForm.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                signature_field_id: String(fieldId),
                signature_image: dataUrl,
                submitted_value: submittedValue,
            }),
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch {
            payload = null;
        }

        if (!response.ok) {
            throw new Error(payload?.message || messages.genericSaveError);
        }

        await applySignedField(payload);
    }

    function activateField(field) {
        if (!currentCanEditFields || isSubmitting) {
            return;
        }

        if (!hasActiveTrustAuthorization()) {
            showFeedback(messages.trustAuthorizationRequired, 'error');
            return;
        }

        if (field.type === 'name') {
            submitSignatureField(field.id, textToDataUrl(signerName), signerName);
            return;
        }
        if (field.type === 'date') {
            const value = new Intl.DateTimeFormat(dateLocale, { dateStyle: 'medium' }).format(new Date());
            submitSignatureField(field.id, textToDataUrl(value), value);
            return;
        }
        if (field.type === 'email') {
            submitSignatureField(field.id, textToDataUrl(signerEmail), signerEmail);
            return;
        }
        if (field.type === 'initials') {
            const initials = signerInitialsValue();
            submitSignatureField(field.id, textToDataUrl(initials), initials);
            return;
        }
        if (field.type === 'checkbox') {
            submitSignatureField(field.id, textToDataUrl('X'), 'X');
            return;
        }
        if (field.type === 'radio') {
            submitSignatureField(field.id, textToDataUrl('O'), 'O');
            return;
        }

        openModal(field.id);
    }

    function signatureImagePlacement(rect, imageWidth, imageHeight, fieldType) {
        const margin = Math.max(3, Math.min(8, rect.height * 0.12));
        const renderWidth = Math.max(12, rect.width - (margin * 2));
        const renderHeight = Math.max(12, rect.height - (margin * 2));
        const scale = Math.min(renderWidth / imageWidth, renderHeight / imageHeight);
        const placedWidth = imageWidth * scale;
        const placedHeight = imageHeight * scale;
        let left = rect.left + margin;

        if (fieldType === 'signature_right') {
            left = rect.left + rect.width - placedWidth - margin;
        } else if (fieldType === 'signature') {
            left = rect.left + ((rect.width - placedWidth) / 2);
        }

        return { left, top: rect.top + ((rect.height - placedHeight) / 2), scale };
    }

    function buildSignedValueGroup(field, rect, submittedValue) {
        const fabric = window.fabric;
        const chrome = getFieldChrome(field.type);
        const nodes = [
            new fabric.Rect({
                width: rect.width,
                height: rect.height,
                left: 0,
                top: 0,
                fill: 'rgba(0,0,0,0)',
                strokeWidth: 0,
                selectable: false,
                evented: false,
                originX: 'left',
                originY: 'top',
            }),
        ];
        const inset = Math.max(4, rect.height * 0.12);

        if (field.type === 'checkbox' || field.type === 'radio') {
            nodes.push(new fabric.Text(submittedValue || (field.type === 'checkbox' ? 'X' : 'O'), {
                fontSize: clamp(rect.height * 0.56, 14, 24),
                fill: chrome.stroke,
                fontFamily: 'Georgia, serif',
                fontWeight: 700,
                originX: 'center',
                originY: 'center',
                left: rect.width / 2,
                top: rect.height / 2,
                textAlign: 'center',
            }));
        } else {
            const textValue = String(submittedValue || '');
            const metrics = inputFieldMetrics(rect);

            nodes.push(new fabric.Text(textValue, {
                fontSize: metrics.valueFontSize,
                fill: '#0f172a',
                fontFamily: 'Georgia, serif',
                fontWeight: 500,
                originX: 'left',
                originY: 'bottom',
                left: metrics.valueLeft + 4,
                top: metrics.baselineY,
                textAlign: 'left',
                lineHeight: 1,
            }));
        }

        return new fabric.Group(nodes, positionedGroupOptions(rect, { subTargetCheck: true }));
    }

    async function renderFieldObject(field, cw, ch, nextFieldId) {
        const fabric = window.fabric;
        clearFieldObjects(field.id);

        const rect = rectFromNormalized(field.position_data, cw, ch);
        if (isSigned(field.id)) {
            const signedField = signedByFieldId[String(field.id)] || {};
            const url = signedField.image_url || null;
            const submittedValue = signedField.submitted_value || '';

            const addSignedDecorators = (target) => {
                const badge = new fabric.Text(messages.signed, {
                    left: rect.left + 4,
                    top: Math.max(2, rect.top - 13),
                    fontSize: 10,
                    fill: '#0f766e',
                    fontFamily: 'system-ui, sans-serif',
                    selectable: false,
                    evented: false,
                    hoverCursor: 'default',
                    opacity: 0.82,
                });
                const hitbox = new fabric.Rect({
                    left: rect.left,
                    top: rect.top,
                    width: rect.width,
                    height: rect.height,
                    fill: 'rgba(0,0,0,0.001)',
                    selectable: false,
                    evented: currentCanEditFields,
                    hasControls: false,
                    hoverCursor: currentCanEditFields ? 'pointer' : 'default',
                });

                // For rotated fields, wrap all elements in a group so rotation
                // happens around the field center (matching the preview behavior)
                if (rect.angle) {
                    // Reposition target relative to field bounds for grouping
                    // and remove any existing angle to avoid double-rotation
                    const targetLeft = target.left !== undefined ? target.left - rect.left : 0;
                    const targetTop = target.top !== undefined ? target.top - rect.top : 0;
                    target.set({ left: targetLeft, top: targetTop, angle: 0 });

                    badge.set({ left: 4, top: -13 });
                    hitbox.set({ left: 0, top: 0 });

                    const group = new fabric.Group([hitbox, target, badge], positionedGroupOptions(rect, {
                        selectable: false,
                        evented: currentCanEditFields,
                        hasControls: false,
                        hoverCursor: currentCanEditFields ? 'pointer' : 'default',
                        subTargetCheck: true,
                    }));

                    if (currentCanEditFields) {
                        group.on('mousedown', (event) => {
                            event.e.preventDefault();
                            activateField(field);
                        });
                    }

                    fabricCanvas.add(group);
                    registerFieldObject(field.id, group);
                    return;
                }

                if (currentCanEditFields) {
                    hitbox.on('mousedown', (event) => {
                        event.e.preventDefault();
                        activateField(field);
                    });
                }

                [target, badge, hitbox].forEach((object) => {
                    fabricCanvas.add(object);
                    registerFieldObject(field.id, object);
                });
            };

            if (url && ['signature', 'signature_left', 'signature_right'].includes(field.type)) {
                const cachedImage = await loadSignatureImage(url);
                if (cachedImage) {
                    const element = cachedImage.getElement ? cachedImage.getElement() : cachedImage._element;
                    const img = new fabric.Image(element);
                    const placement = signatureImagePlacement(rect, cachedImage.width, cachedImage.height, field.type);
                    img.set({
                        left: placement.left,
                        top: placement.top,
                        scaleX: placement.scale,
                        scaleY: placement.scale,
                        selectable: false,
                        evented: currentCanEditFields,
                        hasControls: false,
                        hoverCursor: currentCanEditFields ? 'pointer' : 'default',
                        opacity: 0.98,
                    });
                    if (currentCanEditFields) {
                        img.on('mousedown', (event) => {
                            event.e.preventDefault();
                            activateField(field);
                        });
                    }
                    addSignedDecorators(img);
                    return;
                }
            }

            const valueGroup = buildSignedValueGroup(field, rect, submittedValue);
            valueGroup.selectable = false;
            valueGroup.evented = currentCanEditFields;
            valueGroup.hasControls = false;
            valueGroup.hoverCursor = currentCanEditFields ? 'pointer' : 'default';
            if (currentCanEditFields) {
                valueGroup.on('mousedown', (event) => {
                    event.e.preventDefault();
                    activateField(field);
                });
            }
            addSignedDecorators(valueGroup);
            return;
        }

        const group = buildFieldPreviewGroup(getFieldChrome(field.type), rect);
        group.fieldId = field.id;
        group.selectable = false;
        group.evented = currentCanEditFields;
        group.hasControls = false;
        group.hoverCursor = currentCanEditFields ? 'pointer' : 'default';
        if (currentCanEditFields && nextFieldId !== null && field.id === nextFieldId) {
            group.shadow = new fabric.Shadow({ color: 'rgba(20, 184, 166, 0.35)', blur: 18, offsetX: 0, offsetY: 0 });
        }
        group.on('mousedown', (event) => {
            event.e.preventDefault();
            activateField(field);
        });
        fabricCanvas.add(group);
        registerFieldObject(field.id, group);
    }

    async function renderFieldsByIds(fieldIds, cw, ch) {
        const nextFieldId = firstUnsignedField()?.id ?? null;
        const currentPageFieldMap = new Map(fieldsForCurrentPage().map((field) => [field.id, field]));

        for (const fieldId of [...new Set(fieldIds)]) {
            const field = currentPageFieldMap.get(fieldId);
            if (!field) {
                clearFieldObjects(fieldId);
                continue;
            }

            await renderFieldObject(field, cw, ch, nextFieldId);
        }

        fabricCanvas.requestRenderAll();
    }

    async function renderPageFields(cw, ch) {
        clearAllFieldObjects();
        fabricCanvas.clear();
        const pageFields = fieldsForCurrentPage();
        const nextFieldId = firstUnsignedField()?.id ?? null;

        for (const field of pageFields) {
            await renderFieldObject(field, cw, ch, nextFieldId);
        }

        fabricCanvas.requestRenderAll();
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
            wrapper.style.zIndex = '10';
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
        if (!pdfDoc) {
            return;
        }

        const requestId = ++renderSequence;
        isRenderingPage = true;
        setPdfLoadingState(true, {
            label: messages.loadingDocument || 'Loading document...',
            progress: formatRenderingPageMessage(pageNumber, totalPages || 1),
        });
        updatePageUi();

        try {
            let viewport = null;

            if (!paintCachedPdfPage(pageNumber)) {
                const page = await pdfDoc.getPage(pageNumber);
                if (requestId !== renderSequence) {
                    return;
                }

                viewport = page.getViewport({ scale: renderScale });
                const ctx = pdfCanvas.getContext('2d');
                pdfCanvas.width = viewport.width;
                pdfCanvas.height = viewport.height;
                fabricEl.width = viewport.width;
                fabricEl.height = viewport.height;
                pdfCanvas.style.width = `${viewport.width}px`;
                pdfCanvas.style.height = `${viewport.height}px`;
                fabricEl.style.width = `${viewport.width}px`;
                fabricEl.style.height = `${viewport.height}px`;
                pdfShell.style.width = `${viewport.width}px`;
                pdfShell.style.height = `${viewport.height}px`;
                await page.render({ canvasContext: ctx, viewport }).promise;
                if (requestId !== renderSequence) {
                    return;
                }

                currentViewport = { width: viewport.width, height: viewport.height };
                cachePdfPage(pageNumber);
            } else {
                viewport = currentViewport;
            }

            viewport ||= currentViewport;

            if (!fabricCanvas) {
                fabricCanvas = new window.fabric.Canvas('fabric-canvas', {
                    width: viewport.width,
                    height: viewport.height,
                    selection: false,
                });
            } else {
                fabricCanvas.setWidth(viewport.width);
                fabricCanvas.setHeight(viewport.height);
            }

            syncFabricOverlayLayout(viewport.width, viewport.height);
            await renderPageFields(viewport.width, viewport.height);
        } finally {
            if (requestId === renderSequence) {
                isRenderingPage = false;
                setPdfLoadingState(false);
                updatePageUi();
            }
        }
    }

    function textToDataUrl(text) {
        const canvas = document.createElement('canvas');
        canvas.width = 400;
        canvas.height = 120;
        const fabricCanvas = new window.fabric.Canvas(canvas);
        const textNode = new window.fabric.Text(text, {
            fontSize: 34,
            fontFamily: 'Georgia, serif',
            fill: '#0f172a',
            originX: 'center',
            originY: 'center',
            left: 200,
            top: 60,
        });
        fabricCanvas.add(textNode);
        return trimmedCanvasDataUrl(canvas, { mode: 'transparent', padding: 12 });
    }

    function trimmedCanvasDataUrl(sourceCanvas, options = {}) {
        if (!sourceCanvas) {
            return '';
        }

        const mode = options.mode || 'transparent';
        const padding = Number.isFinite(options.padding) ? Math.max(0, Math.round(options.padding)) : 8;
        const ctx = sourceCanvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return sourceCanvas.toDataURL('image/png');
        }

        const { width, height } = sourceCanvas;
        const imageData = ctx.getImageData(0, 0, width, height);
        const data = imageData.data;
        let minX = width;
        let minY = height;
        let maxX = -1;
        let maxY = -1;

        for (let y = 0; y < height; y += 1) {
            for (let x = 0; x < width; x += 1) {
                const idx = ((y * width) + x) * 4;
                const r = data[idx];
                const g = data[idx + 1];
                const b = data[idx + 2];
                const a = data[idx + 3];
                const hasInk = mode === 'light-bg' ? a > 0 && (r < 245 || g < 245 || b < 245) : a > 8;
                if (!hasInk) {
                    continue;
                }

                minX = Math.min(minX, x);
                minY = Math.min(minY, y);
                maxX = Math.max(maxX, x);
                maxY = Math.max(maxY, y);
            }
        }

        if (maxX < minX || maxY < minY) {
            return sourceCanvas.toDataURL('image/png');
        }

        minX = Math.max(0, minX - padding);
        minY = Math.max(0, minY - padding);
        maxX = Math.min(width - 1, maxX + padding);
        maxY = Math.min(height - 1, maxY + padding);

        const trimmedWidth = Math.max(1, (maxX - minX) + 1);
        const trimmedHeight = Math.max(1, (maxY - minY) + 1);
        const outputCanvas = document.createElement('canvas');
        outputCanvas.width = trimmedWidth;
        outputCanvas.height = trimmedHeight;
        const outputCtx = outputCanvas.getContext('2d');
        if (!outputCtx) {
            return sourceCanvas.toDataURL('image/png');
        }

        if (mode === 'light-bg') {
            outputCtx.fillStyle = '#ffffff';
            outputCtx.fillRect(0, 0, trimmedWidth, trimmedHeight);
        }

        outputCtx.drawImage(sourceCanvas, minX, minY, trimmedWidth, trimmedHeight, 0, 0, trimmedWidth, trimmedHeight);
        return outputCanvas.toDataURL('image/png');
    }

    function submitSignatureField(fieldId, dataUrl, submittedValue = '') {
        if (!currentCanEditFields || isSubmitting) {
            return;
        }

        isSubmitting = true;
        modalSubmitButton?.setAttribute('disabled', 'disabled');

        postSignatureField(fieldId, dataUrl, submittedValue)
            .catch((error) => {
                showFeedback(error.message || messages.genericSaveError, 'error');
            })
            .finally(() => {
                isSubmitting = false;
                modalSubmitButton?.removeAttribute('disabled');
            });
    }

    function openModal(fieldId) {
        if (!currentCanEditFields) {
            return;
        }

        const field = fieldById(fieldId);
        modalFieldId.value = String(fieldId);
        resetModalInputs();
        configureModalForField(field);
        modal.showModal();
        drawCanvasEl && drawContext && clearSignatureCanvas();
        if (isTextFieldType(currentModalFieldType)) {
            typeInput?.focus({ preventScroll: true });
        }
    }

    function closeModal() {
        currentModalFieldType = 'signature';
        resetModalInputs();
        modal.close();
    }

    function clearSignatureCanvas() {
        if (!drawCanvasEl || !drawContext) {
            return;
        }

        drawContext.save();
        drawContext.setTransform(1, 0, 0, 1, 0, 0);
        drawContext.fillStyle = '#ffffff';
        drawContext.fillRect(0, 0, drawCanvasEl.width, drawCanvasEl.height);
        drawContext.restore();
        hasDrawnStroke = false;
        pendingDrawPoints = [];
        drawLastPoint = null;
        drawStrokeSegmentCount = 0;

        if (drawFrameId !== null) {
            cancelAnimationFrame(drawFrameId);
            drawFrameId = null;
        }
    }

    function configureSignatureCanvas() {
        if (!drawCanvasEl) {
            return;
        }

        const rect = drawCanvasEl.getBoundingClientRect();
        const cssWidth = Math.max(320, Math.round(rect.width || 400));
        const cssHeight = Math.max(160, Math.round(cssWidth / 2));
        const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

        drawCanvasEl.width = Math.round(cssWidth * pixelRatio);
        drawCanvasEl.height = Math.round(cssHeight * pixelRatio);
        drawCanvasEl.style.width = `${cssWidth}px`;
        drawCanvasEl.style.height = `${cssHeight}px`;

        drawContext = drawCanvasEl.getContext('2d', { alpha: false, desynchronized: true });
        drawContext.setTransform(1, 0, 0, 1, 0, 0);
        drawContext.scale(pixelRatio, pixelRatio);
        drawContext.lineCap = 'round';
        drawContext.lineJoin = 'round';
        drawContext.lineWidth = 2.5;
        drawContext.strokeStyle = '#0f172a';

        clearSignatureCanvas();
    }

    function signaturePoint(event) {
        const rect = drawCanvasRect || drawCanvasEl.getBoundingClientRect();
        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
            pressure: typeof event.pressure === 'number' && event.pressure > 0 ? event.pressure : 0.5,
            pointerType: event.pointerType || 'mouse',
        };
    }

    function flushSignatureStroke() {
        drawFrameId = null;
        if (!isDrawing || pendingDrawPoints.length === 0 || !drawContext || !drawLastPoint) {
            return;
        }

        for (const point of pendingDrawPoints) {
            const distance = Math.hypot(point.x - drawLastPoint.x, point.y - drawLastPoint.y);
            const movementThreshold = point.pointerType === 'mouse' ? 1.5 : 0.35;
            if (distance < movementThreshold) {
                continue;
            }

            // Velocity-based width: faster strokes get thinner, slower get thicker
            const velocity = Math.min(distance, 50);
            const velocityFactor = 1 - Math.min(velocity / 50, 0.6);
            const pressure = point.pointerType === 'mouse' ? Math.max(point.pressure, 0.72) : point.pressure;
            const targetWidth = (1.4 + (pressure * 1.8)) * (0.6 + velocityFactor * 0.4);
            drawContext.lineWidth = targetWidth;

            // Use quadratic Bézier curve for smooth interpolation
            const midX = (drawLastPoint.x + point.x) / 2;
            const midY = (drawLastPoint.y + point.y) / 2;
            drawContext.quadraticCurveTo(drawLastPoint.x, drawLastPoint.y, midX, midY);
            drawContext.stroke();
            drawContext.beginPath();
            drawContext.moveTo(midX, midY);

            drawLastPoint = point;
            drawStrokeSegmentCount += 1;
        }

        pendingDrawPoints = [];
    }

    function queueSignatureStroke(point) {
        pendingDrawPoints.push(point);
        if (drawFrameId !== null) {
            return;
        }

        drawFrameId = requestAnimationFrame(flushSignatureStroke);
    }

    function beginSignatureStroke(event) {
        if (!drawCanvasEl || !drawContext) {
            return;
        }

        drawCanvasRect = drawCanvasEl.getBoundingClientRect();
        const point = signaturePoint(event);
        isDrawing = true;
        drawPointerId = event.pointerId;
        hasDrawnStroke = true;
        pendingDrawPoints = [];
        drawLastPoint = point;
        drawStrokeSegmentCount = 0;
        drawCanvasEl.setPointerCapture?.(event.pointerId);
        drawContext.beginPath();
        drawContext.moveTo(point.x, point.y);
        event.preventDefault();
    }

    function extendSignatureStroke(event) {
        if (!isDrawing || drawPointerId !== event.pointerId || !drawContext) {
            return;
        }

        const events = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [event];
        for (const nextEvent of events) {
            queueSignatureStroke(signaturePoint(nextEvent));
        }

        event.preventDefault();
    }

    function endSignatureStroke(event) {
        if (!isDrawing || drawPointerId !== event.pointerId || !drawCanvasEl) {
            return;
        }

        flushSignatureStroke();
        if (drawStrokeSegmentCount === 0 && drawLastPoint && drawContext) {
            drawContext.beginPath();
            drawContext.arc(drawLastPoint.x, drawLastPoint.y, 1.2, 0, Math.PI * 2);
            drawContext.fillStyle = '#0f172a';
            drawContext.fill();
        }

        isDrawing = false;
        drawPointerId = null;
        drawCanvasRect = null;
        drawContext?.closePath();
        drawLastPoint = null;
        drawCanvasEl.releasePointerCapture?.(event.pointerId);
        event.preventDefault();
    }

    function signatureDataUrl() {
        return drawCanvasEl ? trimmedCanvasDataUrl(drawCanvasEl, { mode: 'light-bg', padding: 10 }) : '';
    }

    function showTab(name) {
        document.querySelectorAll('.sign-tab').forEach((element) => {
            element.classList.remove('text-teal-700', 'ring-1', 'ring-zinc-200', 'dark:text-teal-300', 'dark:ring-zinc-600');
            element.classList.add('text-zinc-600', 'dark:text-zinc-400');
        });
        document.querySelectorAll('.sign-panel').forEach((element) => {
            element.classList.add('hidden');
        });
        document.getElementById(`panel-${name}`)?.classList.remove('hidden');
        const tab = document.getElementById(`tab-${name}`);
        tab?.classList.add('text-teal-700', 'ring-1', 'ring-zinc-200', 'dark:text-teal-300', 'dark:ring-zinc-600');
        tab?.classList.remove('text-zinc-600', 'dark:text-zinc-400');
    }

    function submitModalForm() {
        if (!currentCanEditFields || isSubmitting) {
            return;
        }

        const fieldType = currentModalFieldType || modalFieldType(modalFieldId.value);

        if (isTextFieldType(fieldType)) {
            const textValue = typeInput?.value.trim() || '';
            if (!textValue) {
                alert(messages.textRequired);
                return;
            }
            submitSignatureField(modalFieldId.value, '', textValue);
            return;
        }

        const drawHidden = panelDraw?.classList.contains('hidden');
        const typeHidden = panelType?.classList.contains('hidden');
        const uploadHidden = panelUpload?.classList.contains('hidden');

        if (!drawHidden) {
            if (!hasDrawnStroke) {
                alert(messages.drawRequired);
                return;
            }
            submitSignatureField(modalFieldId.value, signatureDataUrl(), '');
            return;
        }

        if (!typeHidden) {
            const text = typeInput?.value.trim() || messages.signatureFallbackText;
            const canvas = document.createElement('canvas');
            canvas.width = 400;
            canvas.height = 120;
            const fabricCanvas = new window.fabric.Canvas(canvas);
            const textNode = new window.fabric.Text(text, {
                fontSize: 42,
                fontFamily: 'Georgia, serif',
                fill: '#0f172a',
                originX: 'center',
                originY: 'center',
                left: 200,
                top: 60,
            });
            fabricCanvas.add(textNode);
            submitSignatureField(modalFieldId.value, trimmedCanvasDataUrl(canvas, { mode: 'transparent', padding: 12 }), '');
            return;
        }

        if (!uploadHidden) {
            if (!uploadInput?.files || !uploadInput.files[0]) {
                alert(messages.uploadRequired);
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                submitSignatureField(modalFieldId.value, event.target.result, '');
            };
            reader.readAsDataURL(uploadInput.files[0]);
        }
    }

    async function init() {
        renderTrustAuthorizationSession();

        if (trustAuthorizationEnabled) {
            listen(trustAuthorizationStartButton, 'click', () => {
                startTrustAuthorizationSession().catch((error) => {
                    trustAuthorizationStartInFlight = false;
                    renderTrustAuthorizationSession();
                    showFeedback(error.message || messages.genericSaveError, 'error');
                });
            });

            if (trustAuthorizationSession?.status === 'pending' && trustAuthorizationSession.id) {
                scheduleTrustAuthorizationPoll(trustAuthorizationSession.id);
            }
        }

        if (!pdfCanvas || !fabricEl || !pdfShell || !modal || !signForm || !pdfUrl) {
            return;
        }

        setPdfLoadingState(true, {
            label: messages.loadingDocument || 'Loading document...',
            progress: messages.loadingProgress || 'Preparing secure preview',
        });
        await ensureSignAssets();
        if (destroyed || typeof window.pdfjsLib === 'undefined' || typeof window.fabric === 'undefined') {
            return;
        }

        const loadingTask = window.pdfjsLib.getDocument(pdfUrl);
        loadingTask.onProgress = (progress) => {
            if (destroyed) {
                return;
            }

            const loaded = Number(progress?.loaded || 0);
            const total = Number(progress?.total || 0);
            const percent = total > 0 ? Math.max(1, Math.min(100, Math.round((loaded / total) * 100))) : null;

            setPdfLoadingState(true, {
                label: messages.loadingDocument || 'Loading document...',
                progress: percent === null
                    ? (messages.loadingProgress || 'Preparing secure preview')
                    : `${messages.loadingProgress || 'Preparing secure preview'} ${percent}%`,
            });
        };
        pdfDoc = await loadingTask.promise;
        if (destroyed) {
            return;
        }

        totalPages = pdfDoc.numPages || 1;
        currentPage = Math.min(totalPages, Math.max(1, initialPageNumber()));
        await renderPage(currentPage);
        if (destroyed) {
            return;
        }

        setPdfLoadingState(false);

        listen(btnPrevPage, 'click', async () => {
            if (currentPage <= 1 || isRenderingPage) {
                return;
            }
            currentPage -= 1;
            await renderPage(currentPage);
        });

        listen(btnNextPage, 'click', async () => {
            if (currentPage >= totalPages || isRenderingPage) {
                return;
            }
            currentPage += 1;
            await renderPage(currentPage);
        });

        showTab('draw');
        configureSignatureCanvas();

        listen(drawCanvasEl, 'pointerdown', beginSignatureStroke);
        listen(drawCanvasEl, 'pointermove', extendSignatureStroke);
        listen(drawCanvasEl, 'pointerup', endSignatureStroke);
        listen(drawCanvasEl, 'pointercancel', endSignatureStroke);
        listen(window, 'resize', configureSignatureCanvas);
        listen(document.getElementById('draw-clear'), 'click', clearSignatureCanvas);
        listen(tabDraw, 'click', () => showTab('draw'));
        listen(tabType, 'click', () => showTab('type'));
        listen(tabUpload, 'click', () => showTab('upload'));
        listen(document.getElementById('modal-cancel'), 'click', closeModal);
        listen(signForm, 'submit', (event) => {
            event.preventDefault();
            submitModalForm();
        });
    }

    function destroy() {
        if (destroyed) {
            return;
        }

        renderSequence += 1;
        abortController.abort();
        if (drawFrameId !== null) {
            cancelAnimationFrame(drawFrameId);
            drawFrameId = null;
        }
        clearTrustAuthorizationPollTimer();
        clearAllFieldObjects();
        fabricCanvas?.dispose();
        destroyed = true;

        if (activeSignView === api) {
            activeSignView = null;
        }
    }

    const api = {
        destroy,
        init,
        isDestroyed,
        owns,
        showFeedback,
        setPdfLoadingState,
        messages,
    };

    return api;
}
