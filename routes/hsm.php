<?php

use App\Http\Controllers\Api\HsmController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HSM API Routes
|--------------------------------------------------------------------------
|
| These routes are protected by the Virtual Gateway middleware and provide
| access to HSM operations for PKI services.
|
*/

Route::middleware(['auth:sanctum', 'vgw'])->prefix('api/hsm')->group(function () {
    Route::post('/sign', [HsmController::class, 'sign']);
    Route::post('/verify', [HsmController::class, 'verify']);
    Route::post('/generate-key', [HsmController::class, 'generateKey']);
    Route::get('/public-key', [HsmController::class, 'getPublicKey']);
    Route::delete('/key/{keyId}', [HsmController::class, 'destroyKey']);
    Route::get('/status', [HsmController::class, 'status']);
});
