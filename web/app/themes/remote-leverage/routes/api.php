<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Application\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for webhooks and external integrations.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group with the "api" prefix.
|
*/

Route::prefix('webhooks')->group(function () {
    Route::post('stripe', [StripeWebhookController::class, 'handle'])->name('api.webhooks.stripe');
    Route::post('calendly', [CalendlyWebhookController::class, 'handle'])->name('api.webhooks.calendly');
});

Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
    ]);
})->name('api.health');
