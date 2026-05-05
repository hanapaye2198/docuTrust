import pdfWorkerSrc from 'pdfjs-dist/build/pdf.worker.min.js?url';

export function ensureSignAssets() {
    const assetLoader =
        window.__docutrustSignAssetLoader
        || (window.__docutrustSignAssetLoader = (() => {
            return function loadAssets() {
                return Promise.all([
                    import('pdfjs-dist/build/pdf.js'),
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
