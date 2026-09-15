<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CalendlyWebhookController;
use App\Application\Http\Controllers\GatedDownloadController;
use App\Application\Http\Controllers\PaymentIntentController;
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

/*
 * Stripe Elements checkout (acf/payment-gateway). The browser posts customer details only —
 * the charge amount is resolved from the block on the server. Replaces the legacy
 * admin-ajax action `rl_create_payment_intent` from rl-elementor-blocks.
 */
Route::prefix('payments')->group(function () {
    Route::post('intent', [PaymentIntentController::class, 'store'])->name('api.payments.intent');
});

/*
 * Gated file downloads (acf/impact-report-hero). Name + email in, a Lead row and the usual
 * LeadCreated fan-out out, then the file. Replaces Gravity Forms form 33 on
 * /impact-report-2026/, retired by ADR-0008. Which file is served is decided server-side
 * from the posted slug against config/gated-assets.php — the browser never names a URL.
 */
Route::prefix('leads')->group(function () {
    Route::post('gated-download', [GatedDownloadController::class, 'store'])->name('api.leads.gated-download');
});

Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
    ]);
})->name('api.health');
