<?php

use App\Providers\AppServiceProvider;
use App\Providers\BrevoMailServiceProvider;
use App\Providers\EkycServiceProvider;
use App\Providers\FolioServiceProvider;
use App\Providers\HsmServiceProvider;
use App\Providers\PadesServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    EkycServiceProvider::class,
    BrevoMailServiceProvider::class,
    FolioServiceProvider::class,
    HsmServiceProvider::class,
    PadesServiceProvider::class,
    VoltServiceProvider::class,
];
