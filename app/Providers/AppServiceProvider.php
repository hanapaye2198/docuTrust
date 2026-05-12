<?php

namespace App\Providers;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Contracts\SignerKeyStore;
use App\Events\DocumentCompleted;
use App\Events\DocumentSent;
use App\Events\DocumentSignerCompleted;
use App\Events\NotaryRequestApproved;
use App\Events\NotaryRequestNotarized;
use App\Events\NotaryRequestSubmitted;
use App\Events\NotarySessionScheduled;
use App\Services\DatabaseSignerKeyStore;
use App\Services\DocumentNotificationService;
use App\Services\FileBackedCertificateAuthorityKeyStore;
use App\Services\NotaryNotificationService;
use App\View\Breadcrumbs;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CertificateAuthorityKeyStore::class, FileBackedCertificateAuthorityKeyStore::class);
        $this->app->bind(SignerKeyStore::class, DatabaseSignerKeyStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp-verification', function (Request $request): Limit {
            $actor = (string) ($request->user()?->id ?? 'guest');

            return Limit::perMinute(10)->by('otp|'.$actor.'|'.$request->ip());
        });

        RateLimiter::for('signing-links', function (Request $request): Limit {
            $token = (string) ($request->route('token') ?? 'missing');

            return Limit::perMinute(30)->by('sign|'.$token.'|'.$request->ip());
        });

        View::composer('components.layouts.app', function ($view): void {
            $view->with('layoutBreadcrumbs', Breadcrumbs::items());
        });

        Event::listen(DocumentSent::class, function (DocumentSent $event): void {
            app(DocumentNotificationService::class)->handleDocumentSent($event);
        });

        Event::listen(DocumentSignerCompleted::class, function (DocumentSignerCompleted $event): void {
            app(DocumentNotificationService::class)->handleSignerCompleted($event);
        });

        Event::listen(DocumentCompleted::class, function (DocumentCompleted $event): void {
            app(DocumentNotificationService::class)->handleDocumentCompleted($event);
        });

        Event::listen(NotaryRequestSubmitted::class, function (NotaryRequestSubmitted $event): void {
            app(NotaryNotificationService::class)->handleRequestSubmitted($event);
        });

        Event::listen(NotarySessionScheduled::class, function (NotarySessionScheduled $event): void {
            app(NotaryNotificationService::class)->handleSessionScheduled($event);
        });

        Event::listen(NotaryRequestApproved::class, function (NotaryRequestApproved $event): void {
            app(NotaryNotificationService::class)->handleRequestApproved($event);
        });

        Event::listen(NotaryRequestNotarized::class, function (NotaryRequestNotarized $event): void {
            app(NotaryNotificationService::class)->handleRequestNotarized($event);
        });
    }
}
