<?php

namespace App\Providers;

use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Services\Ekyc\TesseractIdDocumentTextExtractor;
use Illuminate\Support\ServiceProvider;

class EkycServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(IdDocumentTextExtractor::class, function (): IdDocumentTextExtractor {
            return match ((string) config('ekyc.ocr_driver', 'tesseract')) {
                'tesseract' => new TesseractIdDocumentTextExtractor,
                default => new TesseractIdDocumentTextExtractor,
            };
        });
    }
}
