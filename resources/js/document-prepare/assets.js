import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.js?url';

export function ensurePrepareAssets(debugLog) {
    const assetLoader =
        window.__docutrustPrepareAssetLoader
        || (window.__docutrustPrepareAssetLoader = (() => {
            return function loadAssets() {
                debugLog('Loading local PDF/Fabric assets');
                return Promise.all([
                    import('pdfjs-dist/build/pdf'),
                    import('fabric'),
                ]).then(([pdfjsModule, fabricModule]) => {
                    window.pdfjsLib = pdfjsModule;
                    window.fabric = fabricModule.fabric;
                });
            };
        })());

    return assetLoader().then(() => {
        if (window.pdfjsLib) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorkerSrc;
        }
    });
}
