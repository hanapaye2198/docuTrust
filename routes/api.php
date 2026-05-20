<?php

use App\Http\Controllers\Api\GatewayHubWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/gatewayhub/webhook', GatewayHubWebhookController::class);
