<?php

namespace App\Providers;

use App\Contracts\Ekyc\EkycVerificationProvider;
use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Services\Ekyc\EkycProviderManager;
use App\Services\Ekyc\Sumsub\SumsubApiClient;
use App\Services\Ekyc\TesseractIdDocumentTextExtractor;
use Illuminate\Support\ServiceProvider;

class EkycServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Legacy OCR extractor binding (used by TesseractEkycProvider internally)
        $this->app->singleton(IdDocumentTextExtractor::class, function (): IdDocumentTextExtractor {
            return match ((string) config('ekyc.ocr_driver', 'tesseract')) {
                'tesseract' => new TesseractIdDocumentTextExtractor,
                default => new TesseractIdDocumentTextExtractor,
            };
        });

        // Provider manager (resolves the active eKYC driver)
        $this->app->singleton(EkycProviderManager::class);

        // Convenience binding: resolve the default provider via the contract
        $this->app->bind(EkycVerificationProvider::class, function (): EkycVerificationProvider {
            return $this->app->make(EkycProviderManager::class)->driver();
        });

        // Sumsub API client (singleton — reuses HTTP config)
        $this->app->singleton(SumsubApiClient::class, function (): SumsubApiClient {
            return new SumsubApiClient;
        });
    }
}
