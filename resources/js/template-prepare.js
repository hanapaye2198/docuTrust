const PDF_JS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const PDF_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const FABRIC_JS = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';

let librariesPromise = null;
let prepareRunCounter = 0;

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);
        if (existing) {
            if (existing.dataset.loaded === '1') {
                resolve();
                return;
            }
            existing.addEventListener('load', () => resolve(), { once: true });
            existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
            return;
        }
        const s = document.createElement('script');
        s.src = src;
        s.crossOrigin = 'anonymous';
        s.onload = () => {
            s.dataset.loaded = '1';
            resolve();
        };
        s.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(s);
    });
}

function ensurePdfLibraries() {
    if (window.pdfjsLib && window.fabric) {
        return Promise.resolve();
    }
    if (!librariesPromise) {
        librariesPromise = loadScript(PDF_JS)
            .then(() => {
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
                return loadScript(FABRIC_JS);
            })
            .catch((e) => {
                librariesPromise = null;
                throw e;
            });
    }
    return librariesPromise;
}

function showTemplatePrepareError(message) {
    const el = document.getElementById('pdf-load-error');
    if (el) {
        el.textContent = message;
        el.classList.remove('hidden');
    }
}

export function initTemplatePreparePage() {
    const runId = ++prepareRunCounter;
    const cfgEl = document.getElementById('template-prepare-config');
    if (!cfgEl) {
        return;
    }

    let config;
    try {
        config = JSON.parse(cfgEl.textContent);
    } catch {
        return;
    }

    if (typeof window.__docutrustTemplatePrepareCleanup === 'function') {
        window.__docutrustTemplatePrepareCleanup();
        window.__docutrustTemplatePrepareCleanup = null;
    }

    const pdfCanvas = document.getElementById('pdf-canvas');
    const fabricEl = document.getElementById('fabric-canvas');
    const pdfShell = document.getElementById('pdf-shell');
    const msgs = config.messages || {};

    if (!pdfCanvas || !fabricEl || !pdfShell) {
        showTemplatePrepareError(msgs.missingDom || '');
        return;
    }

    const abort = new AbortController();
    let fabricCanvas = null;
    let loadingTask = null;
    let renderTask = null;

    window.__docutrustTemplatePrepareCleanup = () => {
        abort.abort();
        try {
            if (renderTask && typeof renderTask.cancel === 'function') {
                renderTask.cancel();
            }
        } catch {
            //
        }
        try {
            if (loadingTask && typeof loadingTask.destroy === 'function') {
                loadingTask.destroy();
            }
        } catch {
            //
        }
        renderTask = null;
        loadingTask = null;
        try {
            if (fabricCanvas && fabricCanvas.lowerCanvasEl?.isConnected) {
                fabricCanvas.dispose();
            }
        } catch {
            //
        }
        fabricCanvas = null;
    };

    ensurePdfLibraries()
        .then(() => {
            if (typeof window.pdfjsLib === 'undefined' || typeof window.fabric === 'undefined') {
                showTemplatePrepareError(msgs.libs || '');
                return;
            }

            const { pdfjsLib, fabric } = window;

            async function init() {
                loadingTask = pdfjsLib.getDocument({ url: config.pdfUrl, withCredentials: true });
                const pdf = await loadingTask.promise;
                if (runId !== prepareRunCounter || abort.signal.aborted) {
                    return;
                }
                const page = await pdf.getPage(1);
                const scale = 1.5;
                const rotation = page.rotate === 180 ? 0 : page.rotate;
                const viewport = page.getViewport({ scale, rotation });
                const ctx = pdfCanvas.getContext('2d');
                if (!ctx) {
                    throw new Error(msgs.missingDom || 'Canvas context unavailable.');
                }
                pdfCanvas.width = viewport.width;
                pdfCanvas.height = viewport.height;
                fabricEl.width = viewport.width;
                fabricEl.height = viewport.height;
                fabricEl.style.width = `${viewport.width}px`;
                fabricEl.style.height = `${viewport.height}px`;
                pdfShell.style.width = `${viewport.width}px`;
                ctx.clearRect(0, 0, viewport.width, viewport.height);

                renderTask = page.render({ canvasContext: ctx, viewport });
                await renderTask.promise;
                if (runId !== prepareRunCounter || abort.signal.aborted) {
                    return;
                }

                fabricCanvas = new fabric.Canvas('fabric-canvas', {
                    width: viewport.width,
                    height: viewport.height,
                    selection: true,
                });

                const firstSignerRoleName = config.firstSignerRoleName;
                const initialFields = config.initialFields || [];

                function selectedRoleName() {
                    const sel = document.getElementById('field-role');
                    return sel ? sel.value : firstSignerRoleName;
                }

                function makeFieldGroup(type, roleName, position) {
                    const w = fabricCanvas.getWidth();
                    const h = fabricCanvas.getHeight();
                    const left = position.x * w;
                    const top = position.y * h;
                    const width = position.width * w;
                    const height = position.height * h;

                    let fill = 'rgba(59, 130, 246, 0.12)';
                    let stroke = '#2563eb';
                    let label = 'Sign Here';
                    let fillText = '#1d4ed8';
                    if (type === 'text') {
                        fill = 'rgba(234, 179, 8, 0.15)';
                        stroke = '#ca8a04';
                        label = 'Text';
                        fillText = '#a16207';
                    } else if (type === 'name') {
                        fill = 'rgba(34, 197, 94, 0.15)';
                        stroke = '#15803d';
                        label = 'Name';
                        fillText = '#15803d';
                    } else if (type === 'date') {
                        fill = 'rgba(139, 92, 246, 0.12)';
                        stroke = '#6d28d9';
                        label = 'Date';
                        fillText = '#5b21b6';
                    }

                    const rect = new fabric.Rect({
                        width,
                        height,
                        fill,
                        stroke,
                        strokeWidth: 2,
                        rx: 4,
                        ry: 4,
                    });
                    const text = new fabric.Text(label, {
                        fontSize: Math.min(16, height * 0.35),
                        fill: fillText,
                        fontFamily: 'system-ui, sans-serif',
                        originX: 'center',
                        originY: 'center',
                        left: width / 2,
                        top: height / 2,
                    });
                    const sub = new fabric.Text(roleName, {
                        fontSize: Math.min(11, height * 0.22),
                        fill: '#64748b',
                        fontFamily: 'system-ui, sans-serif',
                        originX: 'center',
                        originY: 'center',
                        left: width / 2,
                        top: height - Math.min(12, height * 0.2),
                    });
                    const group = new fabric.Group([rect, text, sub], {
                        left,
                        top,
                        subTargetCheck: true,
                    });
                    group.fieldType = type;
                    group.roleName = roleName;
                    group.hasControls = false;
                    group.lockRotation = true;
                    group.set('lockScalingX', true);
                    group.set('lockScalingY', true);
                    return group;
                }

                function collectFields() {
                    const out = [];
                    const w = fabricCanvas.getWidth();
                    const h = fabricCanvas.getHeight();
                    fabricCanvas.getObjects().forEach((obj) => {
                        if (!obj.fieldType) {
                            return;
                        }
                        const br = obj.getBoundingRect(true);
                        out.push({
                            role_name: obj.roleName,
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

                initialFields.forEach((f) => {
                    const g = makeFieldGroup(f.type, f.role_name, f.position_data);
                    fabricCanvas.add(g);
                });

                document.getElementById('btn-add-signature')?.addEventListener(
                    'click',
                    () => {
                        const rn = selectedRoleName();
                        if (!rn || !fabricCanvas) {
                            return;
                        }
                        const g = makeFieldGroup('signature', rn, {
                            x: 0.08,
                            y: 0.08,
                            width: 0.28,
                            height: 0.06,
                        });
                        fabricCanvas.add(g);
                        fabricCanvas.setActiveObject(g);
                    },
                    { signal: abort.signal },
                );

                document.getElementById('btn-add-text')?.addEventListener(
                    'click',
                    () => {
                        const rn = selectedRoleName();
                        if (!rn || !fabricCanvas) {
                            return;
                        }
                        const g = makeFieldGroup('text', rn, {
                            x: 0.08,
                            y: 0.18,
                            width: 0.28,
                            height: 0.06,
                        });
                        fabricCanvas.add(g);
                        fabricCanvas.setActiveObject(g);
                    },
                    { signal: abort.signal },
                );

                document.getElementById('btn-add-name')?.addEventListener(
                    'click',
                    () => {
                        const rn = selectedRoleName();
                        if (!rn || !fabricCanvas) {
                            return;
                        }
                        const g = makeFieldGroup('name', rn, {
                            x: 0.08,
                            y: 0.28,
                            width: 0.28,
                            height: 0.06,
                        });
                        fabricCanvas.add(g);
                        fabricCanvas.setActiveObject(g);
                    },
                    { signal: abort.signal },
                );

                document.getElementById('btn-add-date')?.addEventListener(
                    'click',
                    () => {
                        const rn = selectedRoleName();
                        if (!rn || !fabricCanvas) {
                            return;
                        }
                        const g = makeFieldGroup('date', rn, {
                            x: 0.08,
                            y: 0.38,
                            width: 0.28,
                            height: 0.06,
                        });
                        fabricCanvas.add(g);
                        fabricCanvas.setActiveObject(g);
                    },
                    { signal: abort.signal },
                );

                document.getElementById('btn-save-fields')?.addEventListener(
                    'click',
                    () => {
                        if (!fabricCanvas) {
                            return;
                        }
                        const fields = collectFields();
                        const payload = document.getElementById('fields-payload');
                        const form = document.getElementById('save-fields-form');
                        if (payload && form) {
                            payload.value = JSON.stringify(fields);
                            form.submit();
                        }
                    },
                    { signal: abort.signal },
                );
            }

            return init();
        })
        .catch((e) => {
            console.error(e);
            const base = msgs.loadPdf || '';
            showTemplatePrepareError(base + (e && e.message ? ` ${e.message}` : ''));
        });
}
