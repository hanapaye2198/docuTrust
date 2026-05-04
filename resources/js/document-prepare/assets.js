const PDF_JS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const PDF_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
const FABRIC_JS = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';

function loadScript(src, registry) {
    if (registry[src]) {
        return registry[src];
    }

    registry[src] = new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);
        if (existing) {
            if (existing.dataset.loaded === '1') {
                resolve();
                return;
            }

            existing.addEventListener('load', () => {
                existing.dataset.loaded = '1';
                resolve();
            }, { once: true });
            existing.addEventListener('error', () => {
                reject(new Error(`Failed to load script: ${src}`));
            }, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.crossOrigin = 'anonymous';
        script.async = true;
        script.addEventListener('load', () => {
            script.dataset.loaded = '1';
            resolve();
        }, { once: true });
        script.addEventListener('error', () => {
            reject(new Error(`Failed to load script: ${src}`));
        }, { once: true });
        document.head.appendChild(script);
    });

    return registry[src];
}

export function ensurePrepareAssets(debugLog) {
    const scriptRegistry =
        window.__docutrustPrepareScriptRegistry
        || (window.__docutrustPrepareScriptRegistry = {});

    const assetLoader =
        window.__docutrustPrepareAssetLoader
        || (window.__docutrustPrepareAssetLoader = (() => {
            return function loadAssets() {
                debugLog('Loading PDF/Fabric assets');
                return Promise.all([
                    loadScript(PDF_JS, scriptRegistry),
                    loadScript(FABRIC_JS, scriptRegistry),
                ]);
            };
        })());

    return assetLoader().then(() => {
        if (window.pdfjsLib) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
        }
    });
}
