<?php

namespace App\Providers;

use App\Contracts\HsmService;
use App\Services\AwsCloudHsmService;
use App\Services\HsmCertificateAuthorityService;
use App\Services\HsmHealthMonitor;
use App\Services\HsmKeyManager;
use App\Services\HsmPkiSignatureService;
use App\Services\HsmSignerSealProvider;
use App\Services\MockHsmService;
use App\Services\ThalesHsmService;
use App\Services\UtimacoHsmService;
use Illuminate\Support\ServiceProvider;

/**
 * HSM Service Provider
 * 
 * Registers HSM-backed services for CSC-compliant PKI operations.
 */
class HsmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind HSM service interface
        $this->app->singleton(HsmService::class, function ($app) {
            $backend = (string) config('hsm.backend', 'mock');

            return match ($backend) {
                'thales' => new ThalesHsmService(),
                'aws-cloudhsm' => new AwsCloudHsmService(),
                'utimaco' => new UtimacoHsmService(),
                'mock' => new MockHsmService(),
                default => throw new \RuntimeException(
                    "Unsupported HSM backend: {$backend}. " .
                    'Supported: thales, aws-cloudhsm, utimaco, mock'
                ),
            };
        });

        // Bind HSM-backed services
        $this->app->singleton(HsmKeyManager::class);
        $this->app->singleton(HsmPkiSignatureService::class);
        $this->app->singleton(HsmCertificateAuthorityService::class);
        $this->app->singleton(HsmSignerSealProvider::class);
        $this->app->singleton(HsmHealthMonitor::class);
    }

    public function boot(): void
    {
        //
    }
}
