<?php

use App\Http\Controllers\Api\CrlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRL API Routes
|--------------------------------------------------------------------------
|
| These routes provide Certificate Revocation List (CRL) distribution endpoints.
|
*/

Route::middleware(['vgw'])->prefix('api/crl')->group(function () {
    Route::get('/pem', [CrlController::class, 'getPem']);
    Route::get('/der', [CrlController::class, 'getDer']);
    Route::get('/distribution-points', [CrlController::class, 'getDistributionPoints']);
});

// Public endpoint for CRL distribution
Route::get('/crl.pem', [CrlController::class, 'getPem'])->name('crl.pem');
Route::get('/crl.der', [CrlController::class, 'getDer'])->name('crl.der');
