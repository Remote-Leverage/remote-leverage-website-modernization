<?php

declare(strict_types=1);

/**
 * Guards the core column styles the theme has to ship itself.
 *
 * app/setup.php dequeues `wp-block-library`, so every core block style the patterns rely on
 * has to be re-implemented by hand in app.css. The failure mode of getting that wrong is
 * silent: the markup is valid, the class is present, the rule simply resolves to nothing, and
 * the page looks fine at the width whoever built it was working at.
 *
 * It has bitten twice. `has-text-align-center` was a no-op across 7 pattern files until
 * 2026-09-15. Then core's mobile column stacking turned out to be missing, so all 43
 * `wp:columns` across 21 pattern files stayed side by side on phones — a 60/40 split still
 * rendered 60/40 at 414px, with headings squeezed into a 190px column.
 *
 * Both rules below are load-bearing and neither is obvious from reading app.css, so they are
 * pinned here rather than left to the next screenshot review.
 */
$theme = dirname(__DIR__, 2);

/** app.css with comments stripped and whitespace flattened, so formatting cannot fail a match. */
$stylesheet = static function (string $path): string {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path)) ?? '';

    return (string) preg_replace('/\s+/', ' ', $css);
};

it('still dequeues wp-block-library, which is what makes the rules below necessary', function () use ($theme) {
    $setup = (string) file_get_contents($theme.'/app/setup.php');

    expect($setup)->toContain("wp_dequeue_style('wp-block-library')");
})->note('If core block styles are ever enqueued again, the hand-written copies below become redundant rather than wrong — but this test is why they exist, so it should fail loudly and be re-read.');

it('re-implements core\'s mobile column stacking', function () use ($theme, $stylesheet) {
    $css = $stylesheet($theme.'/resources/css/app.css');

    // Core: @media (max-width:781px){.wp-block-columns:not(.is-not-stacked-on-mobile)>.wp-block-column{flex-basis:100%!important}}
    expect($css)->toMatch(
        '/@media \( ?max-width: ?781px ?\) \{ [^{}]*'
        .'\.wp-block-columns:not\(\.is-not-stacked-on-mobile\) ?> ?\.wp-block-column \{ ?'
        .'flex-basis: ?100% ?!important/'
    );
});

it('keeps !important on the column flex-wrap declarations', function () use ($theme, $stylesheet) {
    $css = $stylesheet($theme.'/resources/css/app.css');

    // WordPress's layout engine prints an inline `.wp-container-core-columns-is-layout-*{flex-wrap:nowrap}`
    // for every wp:columns. It has the same specificity as `.wp-block-columns` and lands later in the
    // document, so an unflagged `flex-wrap: wrap` here loses and the columns never wrap onto their own
    // rows — which is exactly how the stacking rule above can be present and still do nothing.
    preg_match_all('/\.wp-block-columns \{ ([^}]*) \}/', $css, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $declarations) {
        if (! str_contains($declarations, 'flex-wrap')) {
            continue;
        }

        expect($declarations)->toMatch('/flex-wrap: ?(wrap|nowrap) ?!important/');
    }
});

it('leaves the is-not-stacked-on-mobile opt-out working', function () use ($theme, $stylesheet) {
    $css = $stylesheet($theme.'/resources/css/app.css');

    // Nothing opts out today, but the escape hatch has to survive: without it the only way to keep
    // two columns side by side on a phone is to stop using wp:columns, which forks the pattern.
    expect($css)->toMatch('/\.wp-block-columns\.is-not-stacked-on-mobile \{ ?flex-wrap: ?nowrap ?!important/');
});
