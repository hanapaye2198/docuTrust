<?php

namespace App\Providers;

use App\Contracts\PadesSigningContract;
use App\Services\Signature\CscApiClient;
use App\Services\Signature\CscSigningOrchestrator;
use App\Services\Signature\LtvEmbedder;
use App\Services\Signature\PadesSigningService;
use App\Services\Signature\SadLifecycleService;
use App\Services\Signature\TimestampAuthorityClient;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;

class PadesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PadesSigningContract::class, PadesSigningService::class);
        $this->app->singleton(
            SadLifecycleService::class,
            function ($app): SadLifecycleService {
                return new SadLifecycleService(
                    $app->make('encrypter'),
                    $app->make(LogManager::class),
                );
            }
        );
        $this->app->singleton(
            TimestampAuthorityClient::class,
            fn ($app): TimestampAuthorityClient => new TimestampAuthorityClient
        );
        $this->app->singleton(
            LtvEmbedder::class,
            fn ($app): LtvEmbedder => new LtvEmbedder(
                $app->make(TimestampAuthorityClient::class),
                $app->make(LogManager::class),
            )
        );
        $this->app->bind(
            CscSigningOrchestrator::class,
            function ($app): CscSigningOrchestrator {
                return new CscSigningOrchestrator(
                    $app->make(PadesSigningContract::class),
                    $app->make(CscApiClient::class),
                    $app->make(LogManager::class),
                    $app->make(SadLifecycleService::class),
                );
            }
        );
    }
}
