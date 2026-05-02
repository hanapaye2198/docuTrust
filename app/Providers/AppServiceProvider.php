<?php

namespace App\Providers;

use App\Events\DocumentCompleted;
use App\Events\DocumentSent;
use App\Events\DocumentSignerCompleted;
use App\Services\DocumentNotificationService;
use App\View\Breadcrumbs;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
    }
}
