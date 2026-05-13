<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class BrevoMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /** @var MailManager $mail */
        $mail = $this->app->make('mail.manager');

        $mail->extend('brevo', function () {
            $apiKey = config('services.brevo.key', '');

            $dsn = new Dsn('brevo+api', 'default', $apiKey);
            $factory = new BrevoTransportFactory();

            return $factory->create($dsn);
        });
    }
}
