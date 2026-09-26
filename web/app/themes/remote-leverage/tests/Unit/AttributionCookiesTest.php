<?php

declare(strict_types=1);

use App\Domains\Lead\Services\AttributionCollector;
use App\Infrastructure\WordPress\Hooks\TrackingHooks;

/*
 * The script that replaced the HandL UTM Grabber, and the visitor-cookie regex next to it.
 *
 * Asserted on the rendered output because both bugs this guards against lived in the rendering:
 * a heredoc that ate one level of backslashes, and a parameter list that could drift from the
 * collector that reads the cookies back.
 */
function renderAttributionScript(callable $render): string
{
    ob_start();
    $render(new TrackingHooks);

    return (string) ob_get_clean();
}

test('the attribution script remembers exactly the parameters the collector reads back', function () {
    $out = renderAttributionScript(fn (TrackingHooks $h) => $h->injectAttributionCookies());

    expect($out)->toContain('var LAST = '.json_encode(AttributionCollector::LAST_TOUCH))
        ->and($out)->toContain('FIRST = '.json_encode(AttributionCollector::FIRST_TOUCH))
        ->and($out)->toContain("'rl_' + k")
        ->and($out)->toContain("'rl_ft_' + k")
        ->and($out)->toContain("'".AttributionCollector::FBCLID_TIME_COOKIE."'")
        ->and($out)->toContain("'rl_landing_page'")
        ->and($out)->toContain("'rl_original_ref'")
        // Each own cookie the collector looks for is one the script writes.
        ->and(AttributionCollector::ownCookieFor('fbclid'))->toBe('rl_fbclid')
        ->and(AttributionCollector::ownCookieFor('first_utm_source'))->toBe('rl_ft_utm_source');
});

test('a new touch replaces the whole last-touch set rather than merging into it', function () {
    $out = renderAttributionScript(fn (TrackingHooks $h) => $h->injectAttributionCookies());

    // A click carrying only an fbclid must drop the previous campaign's UTMs.
    expect($out)->toContain("v ? set('rl_' + k, v, ".TrackingHooks::LAST_TOUCH_DAYS.") : drop('rl_' + k)")
        // First touch is written once.
        ->and($out)->toContain("if (!get('rl_ft_ts'))");
});

test('both cookie readers hand the RegExp a real \\s, not a literal s', function () {
    /*
     * The visitor cookie shipped with `'(^|;\s*)'` in the JS source, which a JS string turns
     * into `(^|;s*)`: rl_vid was only found as the first cookie, so anyone with older cookies
     * got a new id on every page. The JS source needs two backslashes.
     */
    $visitor = renderAttributionScript(fn (TrackingHooks $h) => $h->injectVisitorCookie());
    $attribution = renderAttributionScript(fn (TrackingHooks $h) => $h->injectAttributionCookies());

    expect($visitor)->toContain("new RegExp('(^|;\\\\s*)' + name")
        ->and($attribution)->toContain("new RegExp('(^|;\\\\s*)' + n");
});
