<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The "CASE STUDIES / TALENT PROFILES / REVIEWS" sub-navigation bar.
 *
 * Production renders this as an Elementor library template (element 521f30f0, template
 * 34011) injected directly under the site header and above the case-study hero. Measured
 * off `/case-study/chick-fil-a/` at 1440px and 400px on 2026-09-15:
 *
 *   band     full-bleed #330034, min-height 60px, 30px side padding
 *   inner    1240px max-width, centred, flex row, align-items:center, gap 40px
 *            (below Elementor's 767px breakpoint it stacks: column, gap 10px, 20px block padding)
 *   label    Inter 16px / 24px line-height, no letter-spacing, copy already uppercase
 *   idle     #FFFFFF, weight 300, no underline
 *   active   #3DC53D, weight 500, underlined
 *
 * WHERE PRODUCTION SHOWS IT (verified 2026-09-15, not what production-cutover.md
 * originally claimed): only on **single** case_study posts. The string "TALENT PROFILES"
 * does not appear in the served HTML of `/case-study/` (the archive) or `/reviews/`, and
 * a Playwright probe finds no bar on either. `docs/production-cutover.md` described the
 * bar as "shared site-wide with /reviews/"; that was wrong.
 *
 * v2 briefly extended the bar onto the `case_study` archive and `/reviews/`, on the
 * reasoning that a tab group whose tabs lead to pages that drop the bar is broken
 * wayfinding. That extension was **reverted on 2026-09-15** in favour of strict
 * production parity: `surfaces()` now matches single case_study posts and nothing else.
 * The consequence is accepted and deliberate — every tab except CASE STUDIES leads to a
 * page that does not carry the bar, exactly as on production.
 */
class CaseStudySubnav
{
    public const TAB_CASE_STUDIES = 'case-studies';

    public const TAB_TALENT_PROFILES = 'talent-profiles';

    public const TAB_REVIEWS = 'reviews';

    /**
     * Slug of the page each tab points at, in production's own order.
     *
     * Kept as slugs rather than hardcoded paths so the links survive a permalink change
     * or a database refresh that renumbers posts.
     *
     * @var array<string, array{label: string, slug: string, fallback: string}>
     */
    private const TABS = [
        self::TAB_CASE_STUDIES => [
            'label' => 'CASE STUDIES',
            'slug' => '',            // resolved from the case_study archive link
            'fallback' => '/case-study/',
        ],
        self::TAB_TALENT_PROFILES => [
            'label' => 'TALENT PROFILES',
            'slug' => 'samples',     // page 1000005, patterns/samples-content.php
            'fallback' => '/samples/',
        ],
        self::TAB_REVIEWS => [
            'label' => 'REVIEWS',
            'slug' => 'reviews',     // page 292, patterns/reviews-full.php
            'fallback' => '/reviews/',
        ],
    ];

    /**
     * Which tab lights up on which surface.
     *
     * Each entry is a conditional-tag check run against the current main query. The first
     * one that matches wins, so order is significant only if two could ever match at once.
     *
     * Strict production parity: single case_study posts are the ONLY surface. The
     * `case_study` archive, `/reviews/` and `/samples/` are all deliberately absent —
     * production does not show the bar on any of them.
     *
     * @return array<string, callable(): bool>
     */
    private static function surfaces(): array
    {
        return [
            // Production parity — the bar exists here on remoteleverage.com, and only here.
            self::TAB_CASE_STUDIES => static fn (): bool => is_singular('case_study'),
        ];
    }

    /**
     * Container classes that put the bar's left edge on the same vertical line as the
     * content of the page beneath it — which is what production does (its bar and its
     * case-study hero both sit in the same 1240px container, both starting at x=100).
     *
     * Measured at 1440px on 2026-09-15:
     *
     *   case_study archive + single  1260px + px-8  → content starts at x=122
     *   /reviews/                    1380px, no side padding at this width → x=30
     *
     * Since the 2026-09-15 parity revert only the case-study row is reachable — the
     * `/reviews/` row is kept as the recorded measurement in case that surface is ever
     * restored. A surface with no entry falls back to the case-study container, which is
     * the one production actually ships the bar against.
     */
    private const CONTAINERS = [
        self::TAB_CASE_STUDIES => 'max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8',
        self::TAB_REVIEWS => 'max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-0',
    ];

    /**
     * Container classes for the bar's inner row on the current surface.
     */
    public static function containerClass(?string $activeTab = null): string
    {
        $activeTab ??= self::activeTab();

        return self::CONTAINERS[$activeTab] ?? self::CONTAINERS[self::TAB_CASE_STUDIES];
    }

    /**
     * The tab that represents the page being viewed, or null when this page is not one
     * of the bar's surfaces (in which case the bar is not rendered at all).
     */
    public static function activeTab(): ?string
    {
        if (! function_exists('is_singular')) {
            return null;
        }

        foreach (self::surfaces() as $tab => $matches) {
            if ($matches()) {
                return $tab;
            }
        }

        return null;
    }

    /**
     * Whether the current request should render the bar.
     */
    public static function shouldRender(): bool
    {
        return self::activeTab() !== null;
    }

    /**
     * The three tabs, each with its resolved URL and whether it is the current page.
     *
     * @return array<int, array{id: string, label: string, url: string, active: bool}>
     */
    public static function tabs(?string $activeTab = null): array
    {
        $activeTab ??= self::activeTab();
        $tabs = [];

        foreach (self::TABS as $id => $tab) {
            $tabs[] = [
                'id' => $id,
                'label' => $tab['label'],
                'url' => self::resolveUrl($id, $tab),
                'active' => $id === $activeTab,
            ];
        }

        return $tabs;
    }

    /**
     * Resolve a tab's destination, preferring what WordPress actually knows over the
     * hardcoded path, so a renamed page or a changed permalink structure still links.
     *
     * @param  array{label: string, slug: string, fallback: string}  $tab
     */
    private static function resolveUrl(string $id, array $tab): string
    {
        $url = null;

        if ($id === self::TAB_CASE_STUDIES && function_exists('get_post_type_archive_link')) {
            $url = get_post_type_archive_link('case_study');
        } elseif ($tab['slug'] !== '' && function_exists('get_page_by_path')) {
            $page = get_page_by_path($tab['slug']);

            if ($page) {
                $url = get_permalink($page);
            }
        }

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return function_exists('home_url') ? home_url($tab['fallback']) : $tab['fallback'];
    }
}
