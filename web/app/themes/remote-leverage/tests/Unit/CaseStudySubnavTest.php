<?php

declare(strict_types=1);

use App\Support\CaseStudySubnav;

/**
 * Covers the shape of the "CASE STUDIES / TALENT PROFILES / REVIEWS" sub-nav.
 *
 * Scope note: the surface matching in activeTab() runs on WordPress conditional tags
 * (is_singular / is_post_type_archive / is_page), which this suite does not boot and
 * which are deliberately NOT stubbed here — defining them globally would leak into
 * every other test in the process. What is covered is everything that can go wrong
 * silently without WordPress: the tab set, their order, their labels, and the fact
 * that exactly one tab is ever marked active.
 *
 * The failure this is really guarding against: the bar renders on every page on the
 * site. `tabs()` always returns all three tabs regardless of the active one, so a
 * caller that gates rendering on `tabs()` being non-empty (rather than on
 * `activeTab()` being non-null) puts the bar on the homepage. That happened once
 * during the original build.
 */
it('exposes the three tabs in production order with production labels', function () {
    $tabs = CaseStudySubnav::tabs(CaseStudySubnav::TAB_CASE_STUDIES);

    expect($tabs)->toHaveCount(3);
    expect(array_column($tabs, 'id'))->toBe([
        CaseStudySubnav::TAB_CASE_STUDIES,
        CaseStudySubnav::TAB_TALENT_PROFILES,
        CaseStudySubnav::TAB_REVIEWS,
    ]);

    // Production renders the copy already uppercase rather than via text-transform,
    // so the casing here is content, not styling.
    expect(array_column($tabs, 'label'))->toBe([
        'CASE STUDIES',
        'TALENT PROFILES',
        'REVIEWS',
    ]);
});

it('marks exactly one tab active, and only the requested one', function (string $active) {
    $tabs = CaseStudySubnav::tabs($active);

    $activeIds = array_column(array_filter($tabs, fn (array $t): bool => $t['active']), 'id');

    expect($activeIds)->toBe([$active]);
})->with([
    CaseStudySubnav::TAB_CASE_STUDIES,
    CaseStudySubnav::TAB_TALENT_PROFILES,
    CaseStudySubnav::TAB_REVIEWS,
]);

it('marks no tab active when the page is not one of the bar surfaces', function () {
    $tabs = CaseStudySubnav::tabs(null);

    expect(array_filter($tabs, fn (array $t): bool => $t['active']))->toBe([]);

    // Still returns all three — which is why rendering must be gated on activeTab(),
    // not on tabs() being non-empty.
    expect($tabs)->toHaveCount(3);
});

it('points every tab at a real destination', function () {
    $tabs = CaseStudySubnav::tabs(CaseStudySubnav::TAB_REVIEWS);
    $urls = array_combine(array_column($tabs, 'id'), array_column($tabs, 'url'));

    foreach ($urls as $id => $url) {
        expect($url)->toBeString()->not->toBe('', "tab {$id} resolved to an empty URL");
    }

    // TALENT PROFILES resolving to /samples/ is the decision that unblocked this bar
    // (page 1000005, patterns/samples-content.php). If it ever silently falls back to
    // something else, the tab is a dead end.
    expect($urls[CaseStudySubnav::TAB_TALENT_PROFILES])->toContain('/samples');
    expect($urls[CaseStudySubnav::TAB_REVIEWS])->toContain('/reviews');
    expect($urls[CaseStudySubnav::TAB_CASE_STUDIES])->toContain('/case-study');
});

it('renders nothing when there is no active tab', function () {
    // activeTab() must degrade to null (not throw) when WordPress is absent, which is
    // also what keeps the bar off every non-surface page.
    expect(CaseStudySubnav::activeTab())->toBeNull();
    expect(CaseStudySubnav::shouldRender())->toBeFalse();
});
