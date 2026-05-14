<?php

use App\Http\Controllers\Api\ScepController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SCEP API Routes
|--------------------------------------------------------------------------
|
| These routes provide SCEP (Simple Certificate Enrollment Protocol) endpoints
| for certificate enrollment operations.
|
*/

Route::middleware(['vgw'])->prefix('api/scep')->group(function () {
    // GETCA - Get CA information
    Route::get('/ca', [ScepController::class, 'getCa']);

    // PKI message handling
    Route::post('/pki-message', [ScepController::class, 'handlePkiMessage']);

    // Get CA certificate
    Route::get('/cacert', [ScepController::class, 'getCaCertificate']);
});

// Public endpoint for CA certificate retrieval
Route::get('/scep/cacert', [ScepController::class, 'getCaCertificate'])->name('scep.cacert');
