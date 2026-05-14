<?php

use App\Http\Controllers\Api\CmpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CMP API Routes
|--------------------------------------------------------------------------
|
| These routes provide PKIX-CMP (Certificate Management Protocol) endpoints
| for certificate management operations.
|
*/

Route::middleware(['auth:sanctum', 'vgw'])->prefix('api/cmp')->group(function () {
    Route::post('/message', [CmpController::class, 'handleCmpMessage']);
    Route::get('/ca-info', [CmpController::class, 'getCaInfo']);
});
