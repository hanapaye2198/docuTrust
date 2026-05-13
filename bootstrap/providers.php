<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrevoMailServiceProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    BrevoMailServiceProvider::class,
    FolioServiceProvider::class,
    VoltServiceProvider::class,
];
