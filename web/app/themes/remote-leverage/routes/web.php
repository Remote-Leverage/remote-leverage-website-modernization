<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CostAlertSendController;
use App\Domains\Marketing\Support\CostAlertSendLink;
use App\Domains\Scheduling\Actions\RouteInstantCallAction;
use App\Support\PageRobots;
use App\Support\SocialKit;
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

/*
 * Live-transfer booking form — the internal tool a BDR fills in after a live call, for a lead
 * who agreed to a meeting but never went through the online funnel.
 *
 * Recovered from production page 51147 (an Elementor html widget) on 2026-09-17; see
 * `docs/recovered/live-transfer-contact-creation/` for the original and what changed.
 *
 * **Public but never indexed**, which is a deliberate pair rather than an oversight. It stays
 * public because reps reach it by URL with no login, exactly as on production. It is noindexed
 * because production's copy was not — it sits in `page-sitemap.xml` today and serves the full
 * working form to anyone anonymous — and an internal tool that creates CRM contacts has no
 * business in search results.
 *
 * `forceNoindex()` runs before the view, and therefore before `wp_head()` emits the robots tag.
 */
Route::get('live-transfer-contact-creation', function () {
    PageRobots::forceNoindex();

    return view('pages.live-transfer-contact-creation', [
        'webhookUrl' => (string) config('live-transfer.webhook_url', ''),
        'timezone' => (string) config('live-transfer.timezone', 'America/New_York'),
    ]);
})->name('tools.live-transfer');

/*
 * Kickoff & Workforce Planning Meeting — the post-sale booking page.
 *
 * A revenue router: the visitor picks a monthly-revenue band and the matching Calendly event
 * embeds inline (T0 / T10 / T50). Recovered from production page 51128, which was created on
 * 2026-09-15 — after the migration audit froze its scope off `page-sitemap.xml`, which is why
 * it reached cutover with neither a v2 page nor a 301 and served a hard 404.
 *
 * A route rather than a WordPress page because the whole thing is one self-contained markup
 * and script block with no editable content, exactly like `tools.live-transfer` next to it.
 *
 * No forceNoindex() here, unlike that route: production does not noindex this page, and the
 * point of this port is to match it.
 */
Route::get('kickoff-workforce-planning-meeting', function () {
    return view('pages.kickoff-workforce-planning-meeting');
})->name('funnel.kickoff-workforce-planning');

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

/*
 * Internal: the sales team records a referral while the referrer is on the phone (WR-126).
 *
 * Route-only and deliberately not a WP page — nothing links to it, it is not in the nav, and
 * it is not indexable. `forceNoindex()` is what does that: a route has no post and no pattern,
 * so the `rl:noindex` marker PageRobots normally looks for cannot be declared anywhere, and
 * without this call the page would be silently indexable.
 *
 * Deliberately NOT added to `SiteRobotsTxt::DISALLOW`, for the reason that class documents: a
 * crawler told not to fetch a URL never reads the `noindex` on it, which is how a URL ends up
 * stranded in the index with no content. `noindex` is the mechanism that does this job, and
 * `live-transfer-contact-creation` above is noindexed the same way and for the same reason.
 *
 * Unindexed is not access control, and the form does not rely on it being one — see
 * SalesReferralForm for what actually bounds an unauthenticated submission.
 */
Route::get('sales-referral', function () {
    PageRobots::forceNoindex();

    return view('pages.sales-referral');
})->name('sales.referral');

// Legacy URL compatibility: rl-referral-program served login/signup as tabs of a single
// "/referral-dashboard/" page. Preserved here so old bookmarks/emails/backlinks to that URL
// keep working without a redirect rule.
//
// The bare URL resolves to LOGIN. It used to default to signup, which left the login form
// reachable only through "?tab=login" — a URL nobody types — so anyone who opened
// /referral-dashboard/ had no way to sign in; the only link out was in the site footer.
// Signup is now one click away on the switcher rendered by both Livewire components
// (partials/referrer-auth-tabs), and ReferralProgramHeroBlock's "?tab=sign-up" CTA still
// lands on the application form. "?logged_out" and "?action=login" no longer need special
// casing — they fall through to the login default.
Route::get('referral-dashboard', function (Request $request) {
    $isSignUpTab = in_array($request->query('tab'), ['sign-up', 'signup', 'register'], true);

    return $isSignUpTab ? view('pages.referrer-register') : view('pages.referrer-portal');
})->name('referrer.dashboard.legacy');

// The partner directory's CTAs point here. There is no separate partner portal — partners
// and referrers share one portal, registered as `referrer.portal` / `referrer.register`.
Route::get('partner-dashboard', function () {
    return redirect()->route('referrer.portal', [], 301);
});

Route::get('partners', function () {
    return view('archive-rl_partner');
});

// Social Media Kit — the ported rl-social-kit dashboard (signatures + brand asset library).
// A route rather than a WordPress page: it is a tool, not editorial content, so it belongs in
// git like the other utility routes above and survives a database refresh.
Route::get(SocialKit::SLUG, function () {
    return view('pages.social-media-kit');
})->name('social-media-kit');

/*
 * Where the cost alert card's "Send new alert" button lands. The GET is a page and posts nothing;
 * the page POSTs back to `api.marketing.cost-alert.send`, which sends. See CostAlertSendController.
 */
Route::get(CostAlertSendLink::PATH, [CostAlertSendController::class, 'show'])->name('marketing.cost-alert.send');
