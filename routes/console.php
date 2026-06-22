<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:send-pending-signature-reminders')->hourly();
Schedule::command('app:recover-stale-einvoices')->everyFiveMinutes();
Schedule::command('app:prune-einvoice-submission-payloads')->daily();
Schedule::command('signature:expire-sad-sessions')->everyFiveMinutes();
