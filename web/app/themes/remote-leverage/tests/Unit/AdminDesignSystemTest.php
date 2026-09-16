<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\WordPress\Admin\AdminDesignSystem;
use App\Infrastructure\WordPress\Admin\WordPressAdminTheme;

describe('AdminDesignSystem', function () {
    it('keeps every component scoped so it cannot restyle the rest of wp-admin', function () {
        $css = AdminDesignSystem::css();

        // A bare `.rl-card {` is fine — the prefix is ours. What must never appear is an
        // unscoped element selector that would reach wp-admin's own markup.
        expect($css)->not->toMatch('/^\s*(body|table|input|button|h[1-6])\s*\{/m');
    });

    it('makes anchors styled as buttons survive the global link neutralization', function () {
        $global = (new WordPressAdminTheme)->getGlobalAdminCss();
        $tokens = AdminDesignSystem::css();

        // WordPressAdminTheme repaints links across the content area. If that rule exists,
        // it is `#wpbody-content a` — specificity (1,0,1) with !important — which outranks
        // any single-class rule. Anchors used as buttons therefore need an ID in the selector
        // or they render as dark underlined text on a dark button.
        expect($global)->toContain('#wpbody-content a');

        foreach (['a.rl-btn-primary', 'a.rl-btn-outline', 'a.rl-btn-destructive', 'a.rl-tab'] as $control) {
            expect($tokens)->toContain('#wpbody-content .rl-admin-wrap '.$control);
        }
    });

    it('gives the primary button a light label on its dark ground', function () {
        $css = AdminDesignSystem::css();

        // Both spellings have to agree, or a link-button and a real button look different.
        expect($css)->toContain('.rl-btn-primary {')
            ->toContain('color: #fafafa !important')
            ->toContain('#wpbody-content .rl-admin-wrap a.rl-btn-primary { color: #fafafa !important; }');
    });
});
