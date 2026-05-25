<?php

use App\Http\Controllers\Api\EkycTokenController;
use App\Http\Controllers\Api\GatewayHubWebhookController;
use App\Http\Controllers\Api\NotaryRequestStatusController;
use App\Http\Controllers\Api\SumsubWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/gatewayhub/webhook', GatewayHubWebhookController::class);

/*
|--------------------------------------------------------------------------
| eKYC Routes
|--------------------------------------------------------------------------
*/

// Authenticated: generate Sumsub WebSDK access token
Route::post('/ekyc/token', EkycTokenController::class)
    ->middleware(['web', 'auth'])
    ->name('api.ekyc.token');

// Webhook: Sumsub sends verification results here (no auth — signature-validated)
Route::post('/webhooks/sumsub', SumsubWebhookController::class)
    ->name('api.webhooks.sumsub');

/*
|--------------------------------------------------------------------------
| Real-time Status Polling
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notary-requests/{notaryRequest}/status', NotaryRequestStatusController::class)
        ->name('api.notary-requests.status');
});
