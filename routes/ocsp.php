<?php

use App\Http\Controllers\Api\OcspController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OCSP Routes
|--------------------------------------------------------------------------
|
| RFC 6960 OCSP responder endpoints. These are public endpoints that
| PKI-aware applications (browsers, VPNs, email clients) use to check
| certificate revocation status in real-time.
|
*/

// Standard OCSP endpoints (no auth required - public service)
Route::post('/ocsp', [OcspController::class, 'post'])->name('ocsp.post');
Route::get('/ocsp/{encodedRequest}', [OcspController::class, 'get'])->name('ocsp.get');

// JSON convenience endpoint (authenticated)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/api/ocsp/check', [OcspController::class, 'checkStatus'])->name('ocsp.check');
});
