<?php

declare(strict_types=1);

use App\Domains\Scheduling\Actions\RouteInstantCallAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Dedicated Application Pages)
|--------------------------------------------------------------------------
|
| Distinct application routes ported from legacy plugins (rl-referral-program,
| rl-join-live-call, rl-elementor-blocks, rl-social-kit) ensuring business funnels
| remain clean, modular, and separated from standard WordPress pages.
|
*/

// Booking & Strategy Consultation Funnel (replaces rl-elementor-blocks booking widgets)
Route::get('book-consultation', function () {
    return view('pages.book-consultation');
})->name('funnel.book-consultation');

Route::get('book', function () {
    return redirect()->route('funnel.book-consultation');
});

// Instant Live Call Router (replaces rl-join-live-call plugin)
Route::get('live-call/connect', function (Request $request, RouteInstantCallAction $action) {
    $result = $action->execute([
        'email' => $request->query('email', ''),
        'calendly_event_uri' => $request->query('event_uri', ''),
        'calendly_invitee_uri' => $request->query('invitee_uri', ''),
    ]);

    if (! empty($result['routed']) && ! empty($result['redirect_url'])) {
        return redirect()->away($result['redirect_url']);
    }

    // Consultant offline fallback
    return redirect()->route('funnel.book-consultation', [
        'offline' => '1',
        'notice' => $result['message'] ?? 'Consultants are currently offline.',
    ]);
})->name('live-call.connect');

// Referrer Portal & Dashboard (replaces rl-referral-program)
Route::get('referrer-portal', function () {
    return view('pages.referrer-portal');
})->name('referrer.portal');

Route::get('referrer-register', function () {
    return view('pages.referrer-register');
})->name('referrer.register');

// Legacy URL compatibility: rl-referral-program served login/signup as tabs of a single
// "/referral-dashboard/" page (default tab: signup; "?tab=login" or "?logged_out"/"?action=login"
// switched to login/dashboard). Preserved here so old bookmarks/emails/backlinks to that URL
// keep working without a redirect rule.
Route::get('referral-dashboard', function (Request $request) {
    $isLoginTab = $request->query('tab') === 'login'
        || $request->has('logged_out')
        || $request->query('action') === 'login';

    return $isLoginTab ? view('pages.referrer-portal') : view('pages.referrer-register');
})->name('referrer.dashboard.legacy');

// Email Signature Generator (replaces rl-social-kit)
Route::get('tools/signature-generator', function () {
    return view('pages.signature-generator');
})->name('tools.signature-generator');
