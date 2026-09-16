<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin\Seo;

/**
 * The CSS override layer that makes Yoast look like the rest of this admin.
 *
 * Why an override layer rather than a rebuild of the screens: Yoast's inner views
 * (Settings, General, Academy, Integrations, Support) are React apps that mount into
 * bare divs — `#yoast-seo-settings`, `#yoast-seo-general`. Their markup carries the
 * `yst-` Tailwind utility classes and almost no semantic class hooks, so *all* layout
 * lives in `tailwind-2850.css`. Dequeuing that sheet does not leave restyleable
 * semantic HTML behind; it leaves unstyled div soup with no flex, grid or spacing.
 * So the structural sheets stay and this layer repaints on top of them. Only the
 * decorative sheets are dequeued outright — see SeoAdminSkin::DEQUEUED_STYLES.
 *
 * Two things make that repaint tractable:
 *
 * 1. Yoast ships a semantic BEM component layer next to the utilities —
 *    `yst-button--primary`, `yst-card__header`, `yst-paper__header`, `yst-table--default`,
 *    `yst-toggle--checked`, `yst-alert--*`, `yst-title--1..5`. Those are stable across
 *    releases and are what most of this file targets, rather than the 925 utilities.
 * 2. The brand colour reaches the UI through a small, closed set of `primary-*`
 *    utilities — 23 selectors, all enumerated in the "kill the magenta" section. Repaint
 *    those and Yoast's magenta is gone everywhere at once, including screens this theme
 *    has never seen.
 *
 * Every Yoast utility is emitted with `!important`, so overrides need both `!important`
 * and higher specificity. That is the only reason the selectors here are prefixed with
 * `body` — it buys specificity (0,1,1) against Yoast's (0,1,0). It is not defensive
 * over-qualification and should not be "cleaned up".
 *
 * Tokens are the ones AdminDesignSystem already ships, so a Yoast screen and a Leads
 * screen read as the same product.
 */
class SeoSkinStyles
{
    /**
     * Shared tokens.
     *
     * Declared on :root so the editor sidebar, the list tables and the dashboard widget
     * all resolve them without each scope restating the palette.
     */
    public static function tokens(): string
    {
        return <<<'CSS'
        :root {
            --rl-fg: #09090b;
            --rl-fg-muted: #71717a;
            --rl-fg-subtle: #a1a1aa;
            --rl-border: #e4e4e7;
            --rl-border-strong: #d4d4d8;
            --rl-muted: #f4f4f5;
            --rl-subtle: #fafafa;
            --rl-surface: #ffffff;
            --rl-primary: #18181b;
            --rl-primary-hover: #27272a;
            --rl-primary-fg: #fafafa;
            --rl-ring: rgba(24, 24, 27, .08);
            --rl-radius-sm: 6px;
            --rl-radius: 8px;
            --rl-radius-lg: 12px;
            --rl-shadow-sm: 0 1px 2px rgba(0, 0, 0, .05);
            --rl-shadow: 0 1px 3px rgba(0, 0, 0, .06);
            --rl-ok: #15803d;
            --rl-ok-bg: #f0fdf4;
            --rl-ok-border: #bbf7d0;
            --rl-warn: #a16207;
            --rl-warn-bg: #fefce8;
            --rl-warn-border: #fef08a;
            --rl-bad: #b91c1c;
            --rl-bad-bg: #fef2f2;
            --rl-bad-border: #fecaca;
            --rl-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        CSS;
    }

    /**
     * The Yoast React screens: Settings, General, Academy, Integrations, Support, Workouts.
     */
    public static function appCss(): string
    {
        return implode("\n", [
            self::killMagenta(),
            self::components(),
            self::pageChrome(),
            self::suppressPromos(),
        ]);
    }

    /**
     * The `yst-` component layer, shared by every surface that renders it.
     *
     * Deliberately not scoped to a page or to `.yst-root`. Yoast 28.5 runs two component sets
     * side by side: the settings screens are all `yst-`, and the editor metabox mixes `yst-`
     * components (its toggles, buttons and cards — 242 distinct classes in editor-modules.js)
     * into the older `wpseo-`/`yoast-` markup. Scoping these to the settings pages is what left
     * the metabox half-painted, with our tabs above a Yoast-magenta toggle.
     */
    private static function components(): string
    {
        return implode("\n", [
            self::typography(),
            self::buttons(),
            self::surfaces(),
            self::formControls(),
            self::feedback(),
            self::overlays(),
        ]);
    }

    /**
     * Repaint every `primary-*` utility from Yoast magenta to our near-black.
     *
     * This is the complete set as of Yoast 28.5 — `grep -oE '\.(hover|focus|...)?yst-[a-z-]*primary-[0-9]+'`
     * over css/dist/tailwind-*.css returns exactly these 23. Yoast's primary-500 is
     * rgb(166 30 105). The light tints (200/300) carry no brand weight, so they map onto
     * our border greys rather than onto a tint of the near-black, which would read as mud.
     */
    private static function killMagenta(): string
    {
        return <<<'CSS'
        body .yst-bg-primary-500,
        body .group-hover\:yst-bg-primary-500:is(.yst-group:hover *),
        body .yst-from-primary-500 {
            background-color: var(--rl-primary) !important;
            --tw-gradient-from: var(--rl-primary) !important;
        }
        body .yst-bg-primary-600,
        body .hover\:yst-bg-primary-600:hover,
        body .focus\:yst-bg-primary-600:focus {
            background-color: var(--rl-primary-hover) !important;
        }
        body .yst-bg-primary-200,
        body .group-hover\:yst-bg-primary-200:is(.yst-group:hover *) {
            background-color: var(--rl-muted) !important;
        }
        body .yst-text-primary-500,
        body .hover\:yst-text-primary-500:hover,
        body .focus\:yst-text-primary-500:focus,
        body .group-hover\:yst-text-primary-800:is(.yst-group:hover *) {
            color: var(--rl-fg) !important;
        }
        body .yst-text-primary-300 {
            color: var(--rl-fg-subtle) !important;
        }
        body .yst-fill-primary-500 {
            fill: var(--rl-primary) !important;
        }
        body .yst-border-primary-500,
        body .focus\:yst-border-primary-500:focus,
        body .focus-within\:yst-border-primary-500:focus-within {
            border-color: var(--rl-border-strong) !important;
        }
        body .yst-border-primary-300 { border-color: var(--rl-border-strong) !important; }
        body .yst-border-primary-200 { border-color: var(--rl-border) !important; }

        /* Focus rings: shadcn uses a soft neutral halo, not a saturated brand ring. */
        body .yst-ring-primary-500,
        body .focus\:yst-ring-primary-500:focus,
        body .focus-within\:yst-ring-primary-500:focus-within {
            --tw-ring-color: var(--rl-ring) !important;
        }
        body .yst-ring-offset-primary-500,
        body .focus\:yst-ring-offset-primary-500:focus {
            --tw-ring-offset-color: var(--rl-surface) !important;
        }
        body .focus\:yst-outline-primary-500:focus {
            outline-color: var(--rl-border-strong) !important;
        }
        CSS;
    }

    /**
     * Page shell: background, the Yoast header bar, and the settings sidebar nav.
     */
    private static function pageChrome(): string
    {
        return <<<'CSS'
        body[class*="page_wpseo"] {
            background-color: var(--rl-subtle) !important;
        }
        body[class*="page_wpseo"] #wpcontent,
        body[class*="page_wpseo"] .yst-root {
            font-family: var(--rl-font) !important;
            color: var(--rl-fg) !important;
            -webkit-font-smoothing: antialiased;
        }

        /* Yoast's own header strip carries the logo lockup and a white band. Flatten it
           into the page so the screen starts at its title, the way our screens do. */
        body[class*="page_wpseo"] .yst-root header.yst-bg-white,
        body[class*="page_wpseo"] .yst-root > div > header {
            background: transparent !important;
            border-bottom: 1px solid var(--rl-border) !important;
            box-shadow: none !important;
        }
        body[class*="page_wpseo"] .yoast-logo,
        body[class*="page_wpseo"] svg.yoast-logo,
        /* The settings sidebar's logo link. Its <svg> carries only a sizing utility, so the
           only stable handle is the anchor's id. */
        body[class*="page_wpseo"] #link-yoast-logo,
        body[class*="page_wpseo"] .yst-root img[src*="Yoast_SEO_Icon"],
        body[class*="page_wpseo"] .yst-root img[alt*="Yoast"] {
            display: none !important;
        }

        /* Settings sidebar nav — make the active item a neutral pill. */
        body[class*="page_wpseo"] .yst-sidebar-navigation__item--active,
        body[class*="page_wpseo"] .yst-sidebar-navigation a[aria-current="page"] {
            background-color: var(--rl-muted) !important;
            color: var(--rl-fg) !important;
            border-radius: var(--rl-radius-sm) !important;
            font-weight: 600 !important;
        }

        /* WordPress notices leak into these screens above the React root. */
        body[class*="page_wpseo"] .notice,
        body[class*="page_wpseo"] .updated,
        body[class*="page_wpseo"] .error {
            border-radius: var(--rl-radius) !important;
            border: 1px solid var(--rl-border) !important;
            border-left-width: 3px !important;
            box-shadow: none !important;
        }
        CSS;
    }

    private static function typography(): string
    {
        return <<<'CSS'
        body .yst-title,
        body .yst-root h1, body .yst-root h2, body .yst-root h3 {
            font-family: var(--rl-font) !important;
            color: var(--rl-fg) !important;
            letter-spacing: -0.02em !important;
        }
        body .yst-title--1 { font-size: 24px !important; font-weight: 700 !important; line-height: 1.25 !important; }
        body .yst-title--2 { font-size: 18px !important; font-weight: 600 !important; letter-spacing: -0.015em !important; }
        body .yst-title--3 { font-size: 15px !important; font-weight: 600 !important; letter-spacing: -0.01em !important; }
        body .yst-title--4,
        body .yst-title--5 { font-size: 13px !important; font-weight: 600 !important; letter-spacing: 0 !important; }
        body .yst-root p { color: var(--rl-fg-muted) !important; font-size: 13px !important; line-height: 1.6 !important; }
        body .yst-link,
        body .yst-link--primary {
            color: var(--rl-fg) !important;
            text-decoration-color: var(--rl-border-strong) !important;
            text-underline-offset: 2px !important;
        }
        body .yst-link:hover { text-decoration-color: var(--rl-fg) !important; }
        body .yst-link--error { color: #dc2626 !important; }
        body .yst-root code, body .yst-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace !important;
            font-size: 11px !important;
            background: var(--rl-muted) !important;
            border-radius: 4px !important;
            padding: 1px 5px !important;
            color: var(--rl-fg-muted) !important;
        }
        CSS;
    }

    private static function buttons(): string
    {
        return <<<'CSS'
        body .yst-button {
            font-family: var(--rl-font) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            border-radius: var(--rl-radius-sm) !important;
            box-shadow: var(--rl-shadow-sm) !important;
            border: 1px solid transparent !important;
            transition: all .15s ease !important;
            text-shadow: none !important;
            letter-spacing: 0 !important;
        }
        body .yst-button--primary {
            background-color: var(--rl-primary) !important;
            border-color: var(--rl-primary) !important;
            color: var(--rl-primary-fg) !important;
        }
        body .yst-button--primary:hover:not(.yst-button--disabled) {
            background-color: var(--rl-primary-hover) !important;
            border-color: var(--rl-primary-hover) !important;
        }
        body .yst-button--secondary,
        body .yst-button--tertiary {
            background-color: var(--rl-surface) !important;
            border-color: var(--rl-border) !important;
            color: var(--rl-fg) !important;
        }
        body .yst-button--secondary:hover:not(.yst-button--disabled),
        body .yst-button--tertiary:hover:not(.yst-button--disabled) {
            background-color: var(--rl-muted) !important;
            border-color: var(--rl-border-strong) !important;
        }
        body .yst-button--error {
            background-color: var(--rl-surface) !important;
            border-color: var(--rl-bad-border) !important;
            color: #ef4444 !important;
        }
        body .yst-button--error:hover:not(.yst-button--disabled) {
            background-color: var(--rl-bad-bg) !important;
            border-color: #f87171 !important;
            color: #dc2626 !important;
        }
        body .yst-button--small { padding: 5px 10px !important; font-size: 12px !important; }
        body .yst-button--large,
        body .yst-button--extra-large { padding: 9px 18px !important; font-size: 13px !important; }
        body .yst-button--disabled { opacity: .5 !important; box-shadow: none !important; }
        CSS;
    }

    /** Cards, papers and tables — the boxes Yoast lays its screens out in. */
    private static function surfaces(): string
    {
        return <<<'CSS'
        body .yst-card,
        body .yst-paper {
            background: var(--rl-surface) !important;
            border: 1px solid var(--rl-border) !important;
            border-radius: var(--rl-radius-lg) !important;
            box-shadow: var(--rl-shadow) !important;
        }
        body .yst-card__header,
        body .yst-paper__header {
            border-bottom: 1px solid var(--rl-muted) !important;
            background: transparent !important;
            padding: 16px 20px !important;
        }
        body .yst-card__content,
        body .yst-paper__content { padding: 18px 20px !important; }
        body .yst-card__footer {
            border-top: 1px solid var(--rl-muted) !important;
            background: var(--rl-subtle) !important;
            padding: 12px 20px !important;
        }

        body .yst-table {
            font-size: 13px !important;
            border-collapse: collapse !important;
        }
        body .yst-table thead,
        body .yst-table--default thead { background: var(--rl-subtle) !important; }
        body .yst-table th {
            font-size: 11px !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            color: var(--rl-fg-muted) !important;
            border-bottom: 1px solid var(--rl-border) !important;
            padding: 10px 16px !important;
            text-align: left !important;
        }
        body .yst-table td {
            padding: 12px 16px !important;
            border-bottom: 1px solid var(--rl-muted) !important;
            color: var(--rl-fg) !important;
        }
        body .yst-table tr:last-child td { border-bottom: 0 !important; }

        body .yst-pagination__button {
            border-radius: var(--rl-radius-sm) !important;
            border-color: var(--rl-border) !important;
            color: var(--rl-fg) !important;
        }
        body .yst-pagination__button--active {
            background: var(--rl-primary) !important;
            color: var(--rl-primary-fg) !important;
            border-color: var(--rl-primary) !important;
        }
        CSS;
    }

    /** Inputs, selects, autocompletes, toggles, checkboxes, radios. */
    private static function formControls(): string
    {
        return <<<'CSS'
        body .yst-root input[type="text"],
        body .yst-root input[type="url"],
        body .yst-root input[type="email"],
        body .yst-root input[type="number"],
        body .yst-root input[type="search"],
        body .yst-root textarea,
        body .yst-select__button,
        body .yst-autocomplete__input {
            border: 1px solid var(--rl-border) !important;
            border-radius: var(--rl-radius-sm) !important;
            background: var(--rl-surface) !important;
            color: var(--rl-fg) !important;
            font-size: 13px !important;
            font-family: var(--rl-font) !important;
            padding: 7px 11px !important;
            min-height: 36px !important;
            box-shadow: none !important;
            line-height: 1.4 !important;
        }
        body .yst-root input:focus,
        body .yst-root textarea:focus,
        body .yst-select__button:focus,
        body .yst-autocomplete__input:focus {
            border-color: var(--rl-border-strong) !important;
            outline: 2px solid var(--rl-ring) !important;
            outline-offset: 0 !important;
            box-shadow: none !important;
        }
        body .yst-root input::placeholder,
        body .yst-root textarea::placeholder { color: var(--rl-fg-subtle) !important; }
        body .yst-label,
        body .yst-select__label,
        body .yst-checkbox__label,
        body .yst-root label {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: var(--rl-fg) !important;
        }

        body .yst-select__options,
        body .yst-autocomplete__options {
            border: 1px solid var(--rl-border) !important;
            border-radius: var(--rl-radius) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08) !important;
            background: var(--rl-surface) !important;
            padding: 4px !important;
        }
        body .yst-select__option,
        body .yst-autocomplete__option {
            border-radius: var(--rl-radius-sm) !important;
            font-size: 13px !important;
            color: var(--rl-fg) !important;
            padding: 7px 10px !important;
        }
        body .yst-select__option--active,
        body .yst-autocomplete__option--active {
            background: var(--rl-muted) !important;
            color: var(--rl-fg) !important;
        }
        body .yst-select__option--selected,
        body .yst-autocomplete__option--selected { font-weight: 600 !important; }

        /* Toggle: shadcn switch — neutral track, white handle, no brand colour. */
        body .yst-toggle {
            background-color: var(--rl-border-strong) !important;
            border-radius: 9999px !important;
        }
        body .yst-toggle--checked { background-color: var(--rl-primary) !important; }
        body .yst-toggle--disabled { opacity: .5 !important; }
        body .yst-toggle__handle {
            background: var(--rl-surface) !important;
            box-shadow: var(--rl-shadow-sm) !important;
        }
        /* The tick/cross inside the handle is Yoast-specific ornament; shadcn switches are bare. */
        body .yst-toggle__icon { display: none !important; }

        body .yst-checkbox__input,
        body .yst-root input[type="checkbox"],
        body .yst-root input[type="radio"] {
            border: 1px solid var(--rl-border-strong) !important;
            border-radius: 4px !important;
            box-shadow: none !important;
        }
        body .yst-root input[type="radio"] { border-radius: 9999px !important; }
        body .yst-checkbox__input:checked,
        body .yst-root input[type="checkbox"]:checked,
        body .yst-root input[type="radio"]:checked {
            background-color: var(--rl-primary) !important;
            border-color: var(--rl-primary) !important;
        }
        CSS;
    }

    /** Alerts, badges, notifications, toasts — the status vocabulary. */
    private static function feedback(): string
    {
        return <<<'CSS'
        body .yst-alert {
            border-radius: var(--rl-radius) !important;
            border: 1px solid var(--rl-border) !important;
            font-size: 13px !important;
            box-shadow: none !important;
        }
        body .yst-alert--success { background: var(--rl-ok-bg) !important; border-color: var(--rl-ok-border) !important; color: var(--rl-ok) !important; }
        body .yst-alert--warning { background: var(--rl-warn-bg) !important; border-color: var(--rl-warn-border) !important; color: var(--rl-warn) !important; }
        body .yst-alert--error   { background: var(--rl-bad-bg) !important; border-color: var(--rl-bad-border) !important; color: var(--rl-bad) !important; }
        body .yst-alert--info    { background: var(--rl-subtle) !important; border-color: var(--rl-border) !important; color: var(--rl-fg-muted) !important; }

        body .yst-badge {
            border-radius: 9999px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            padding: 2px 9px !important;
            line-height: 1.6 !important;
            background: var(--rl-muted) !important;
            color: #3f3f46 !important;
            border: 0 !important;
        }
        body .yst-badge--success { background: var(--rl-ok-bg) !important; color: var(--rl-ok) !important; }
        body .yst-badge--error   { background: var(--rl-bad-bg) !important; color: var(--rl-bad) !important; }
        body .yst-badge--info    { background: var(--rl-subtle) !important; color: var(--rl-fg-muted) !important; }

        body .yst-notification,
        body .yst-toast {
            border-radius: var(--rl-radius-lg) !important;
            border: 1px solid var(--rl-border) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .1) !important;
            background: var(--rl-surface) !important;
        }
        CSS;
    }

    private static function overlays(): string
    {
        return <<<'CSS'
        body .yst-modal__panel,
        body .yst-modal__container {
            border-radius: var(--rl-radius-lg) !important;
            border: 1px solid var(--rl-border) !important;
            box-shadow: 0 16px 48px rgba(0, 0, 0, .16) !important;
        }
        body .yst-modal__overlay { background: rgba(9, 9, 11, .4) !important; }
        body .yst-modal__container-header { border-bottom: 1px solid var(--rl-muted) !important; }
        body .yst-modal__container-footer {
            border-top: 1px solid var(--rl-muted) !important;
            background: var(--rl-subtle) !important;
        }
        body .yst-popover {
            border-radius: var(--rl-radius) !important;
            border: 1px solid var(--rl-border) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08) !important;
        }
        body .yst-tooltip {
            background: var(--rl-primary) !important;
            color: var(--rl-primary-fg) !important;
            border-radius: var(--rl-radius-sm) !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            box-shadow: var(--rl-shadow) !important;
        }
        body .yst-tooltip--light {
            background: var(--rl-surface) !important;
            color: var(--rl-fg) !important;
            border: 1px solid var(--rl-border) !important;
        }
        CSS;
    }

    /**
     * Hide Yoast's commercial furniture.
     *
     * These are the Premium upsell cards, the sidebar "upgrade" blocks, the black-friday
     * strip and the AI-feature teasers. Their stylesheets are dequeued too, but the markup
     * is rendered by React regardless of whether its CSS loaded, so an unstyled upsell
     * would still occupy the page. Hiding is what actually removes them.
     */
    private static function suppressPromos(): string
    {
        return <<<'CSS'
        body[class*="page_wpseo"] .yst-root .yst-badge--upsell,
        body[class*="page_wpseo"] .yst-root .yst-button--upsell,
        body[class*="page_wpseo"] .yst-root [class*="yst-upsell"],
        body[class*="page_wpseo"] .yst-root [data-id*="upsell"],
        body[class*="page_wpseo"] .yoast-premium-logo-new,
        body[class*="page_wpseo"] #sidebar-container,
        body[class*="page_wpseo"] .yoast-sidebar__upsell,
        body[class*="page_wpseo"] .wpseo-premium-upsell,
        body[class*="page_wpseo"] .yst-root .yst-button--ai-primary,
        body[class*="page_wpseo"] .yst-root .yst-button--ai-secondary,
        body[class*="page_wpseo"] .yst-root .yst-badge--ai,
        body .yoast-black-friday-banner,
        body .yoast-notification-blackfriday {
            display: none !important;
        }

        /*
         * The Settings screen's fixed right rail — a "Yoast SEO Premium" pitch stacked on an
         * academy promo, roughly a third of the viewport. It is built entirely from utilities
         * with no semantic hook, so it is addressed by the three that define it: fixed
         * position, pinned to the inline end, at a 16rem width.
         */
        body[class*="page_wpseo"] div[class*="yst-fixed"][class*="yst-end-8"] {
            display: none !important;
        }
        /*
         * ...and the 17.5rem of inline-end padding the main column reserves for it, which would
         * otherwise leave a column of empty space where the pitch used to be.
         */
        body[class*="page_wpseo"] .yst-flex.yst-grow.yst-flex-wrap {
            padding-inline-end: 0 !important;
        }
        CSS;
    }

    /**
     * The post editor: metabox, Gutenberg sidebar, score icons and the list-table columns.
     *
     * The metabox is NOT built from the `yst-` UI library the settings screens use — it is
     * Yoast's older component set (`wpseo-metabox-menu`, `wpseo-meta-section-link`,
     * `yoast-collapsible__trigger`, `yoast-field-group`), rendered by
     * WPSEO_Metabox::render_tabs() and WPSEO_Metabox_Section_React::display_link(). Rules
     * scoped to `.yst-root` never reach it. Selectors here were read off
     * css/dist/metabox-*.css and the markup in admin/metabox/, not assumed.
     *
     * Yoast's metabox CSS is mostly un-flagged (`.wpseo-metabox-menu ul li a{...}` plain), but
     * WordPressAdminTheme's global admin sheet is aggressive and !important throughout, so
     * these carry !important to clear that rather than Yoast.
     *
     * The traffic-light score bullets stay colour-coded — that is information, not branding —
     * but are redrawn as flat dots on our status palette instead of Yoast's glossy circles.
     */
    public static function editorCss(): string
    {
        return implode("\n", [
            // The metabox renders yst- components too, so it needs both layers.
            self::killMagenta(),
            self::components(),
            self::editorChrome(),
        ]);
    }

    /**
     * The editor's own markup: Yoast's older `wpseo-`/`yoast-` component set and the list table.
     */
    private static function editorChrome(): string
    {
        return <<<'CSS'
        /* --- Metabox shell --- */
        #wpseo_meta.postbox,
        .wpseo-taxonomy-metabox-postbox {
            border-radius: var(--rl-radius-lg) !important;
            border: 1px solid var(--rl-border) !important;
            box-shadow: var(--rl-shadow) !important;
            overflow: visible !important;
        }
        /*
         * Widths and alignment.
         *
         * Yoast caps the menu and the panel at 600px and, inside the block editor's metabox
         * area, centres the menu with `margin: 0 auto` while the panel stays left — which is
         * what leaves the tabs and the content on different axes. Both go full width and left.
         */
        .wpseo-metabox-content,
        #wpseo_meta .wpseo-metabox-content,
        .edit-post-meta-boxes-area__container .wpseo-metabox .wpseo-metabox-content {
            max-width: none !important;
            padding: 16px 0 0 !important;
        }
        .wpseo-metabox-menu,
        .edit-post-meta-boxes-area__container .wpseo-metabox .wpseo-metabox-menu {
            max-width: none !important;
            margin: 0 !important;
        }
        .wpseo-meta-section-react,
        .wpseo-meta-section {
            max-width: none !important;
            width: 100% !important;
        }
        .wpseo-metabox,
        .wpseo-meta-section,
        .wpseo-meta-section-react {
            font-family: var(--rl-font) !important;
            color: var(--rl-fg) !important;
        }

        /* --- Tab bar: Yoast's bordered file-folder tabs become a segmented control --- */
        .wpseo-metabox-menu { margin: 0 !important; }
        .wpseo-metabox-menu ul.yoast-aria-tabs {
            display: inline-flex !important;
            align-items: center !important;
            /* Yoast sets flex-flow: wrap-reverse and align-items: flex-end to make the tabs
               sit on a baseline. A pill row wants neither. */
            flex-flow: row wrap !important;
            gap: 2px !important;
            background: var(--rl-muted) !important;
            border: 1px solid var(--rl-border) !important;
            border-radius: var(--rl-radius) !important;
            padding: 3px !important;
            margin: 0 0 16px !important;
        }
        /*
         * Yoast dresses each <li> as a file-folder tab in its own right: a grey fill, a drop
         * shadow, a fixed 32px height and -1px margins that overlap its neighbour's border.
         * All of it has to go, or the separators keep showing through the pill group above.
         */
        .wpseo-metabox-menu ul.yoast-aria-tabs li,
        .wpseo-metabox-menu ul li {
            background-color: transparent !important;
            box-shadow: none !important;
            border: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .wpseo-metabox-menu ul li a,
        .wpseo-meta-section-link {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            border: 0 !important;
            background: transparent !important;
            color: var(--rl-fg-muted) !important;
            border-radius: var(--rl-radius-sm) !important;
            padding: 6px 12px !important;
            height: auto !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            line-height: 1.4 !important;
            box-shadow: none !important;
            text-decoration: none !important;
        }
        .wpseo-metabox-menu ul li a:hover { color: var(--rl-fg) !important; }
        .wpseo-metabox-menu ul li.active a,
        .wpseo-metabox-menu ul li a[aria-selected="true"] {
            background: var(--rl-surface) !important;
            color: var(--rl-fg) !important;
            font-weight: 600 !important;
            height: auto !important;
            box-shadow: var(--rl-shadow-sm) !important;
        }

        /* --- Panels --- */
        .wpseo-meta-section,
        .wpseo-meta-section-react {
            border: 0 !important;
            padding: 0 !important;
            background: transparent !important;
        }

        /* --- Collapsibles and field groups --- */
        .yoast-collapsible__trigger,
        .yoast-collapsible-block .yoast-collapsible__trigger {
            font-size: 14px !important;
            font-weight: 600 !important;
            color: var(--rl-fg) !important;
            text-decoration: none !important;
            padding: 12px 0 !important;
        }
        .yoast-collapsible__icon, .yoast-chevron { color: var(--rl-fg-subtle) !important; }
        .yoast-field-group__title,
        .yoast-h2 {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: var(--rl-fg) !important;
            letter-spacing: -0.01em !important;
        }
        .wpseo-metabox .yoast-metabox__description,
        .wpseo-meta-section p {
            color: var(--rl-fg-muted) !important;
            font-size: 13px !important;
            line-height: 1.6 !important;
        }

        /* --- Inputs inside the metabox and the taxonomy box --- */
        .wpseo-metabox input[type="text"],
        .wpseo-metabox textarea,
        .wpseo-meta-section input[type="text"],
        .wpseo-meta-section textarea,
        .wpseo-meta-section .public-DraftEditor-content {
            border: 1px solid var(--rl-border) !important;
            border-radius: var(--rl-radius-sm) !important;
            background: var(--rl-surface) !important;
            color: var(--rl-fg) !important;
            font-size: 13px !important;
            font-family: var(--rl-font) !important;
            padding: 7px 11px !important;
            box-shadow: none !important;
        }
        .wpseo-metabox input[type="text"]:focus,
        .wpseo-metabox textarea:focus,
        .wpseo-meta-section input[type="text"]:focus,
        .wpseo-meta-section textarea:focus {
            border-color: var(--rl-border-strong) !important;
            outline: 2px solid var(--rl-ring) !important;
            box-shadow: none !important;
        }

        /* --- Buttons --- */
        .wpseo-metabox .yoast-button,
        .wpseo-meta-section .yoast-button {
            border-radius: var(--rl-radius-sm) !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            font-family: var(--rl-font) !important;
            background: var(--rl-primary) !important;
            border: 1px solid var(--rl-primary) !important;
            color: var(--rl-primary-fg) !important;
            box-shadow: var(--rl-shadow-sm) !important;
            text-decoration: none !important;
        }
        .wpseo-metabox .yoast-button:hover,
        .wpseo-meta-section .yoast-button:hover {
            background: var(--rl-primary-hover) !important;
            border-color: var(--rl-primary-hover) !important;
        }

        /* --- Gutenberg sidebar (the same component set, different mount) --- */
        .yoast-sidebar__item .yoast-collapsible__trigger,
        .components-panel__body .yoast-collapsible__trigger {
            font-size: 13px !important;
            font-weight: 600 !important;
            color: var(--rl-fg) !important;
        }

        /* --- Yoast branding and upsells inside the editor --- */
        .wpseo-metabox .yoast-logo,
        .wpseo-meta-section .yoast-logo,
        .wpseo-metabox svg.yoast-logo,
        .wpseo-metabox .yst-logo-icon,
        .wpseo-meta-section .yst-logo-icon,
        #wpseo_meta .yoast-logo,
        #wpseo_meta .yst-logo-icon,
        .wpseo-metabox img[alt*="Yoast"],
        .wpseo-buy-premium,
        .wpseo-metabox-buy-premium,
        .wpseo-metabox-sidebar,
        .yoast-data-model--upsell,
        .yoast-add-block-button,
        .wpseo-metabox .yoast-button--upsell {
            display: none !important;
        }

        /*
         * --- Yoast branding and the AI upsell inside the metabox ---
         *
         * The wordmark is `svg.yst-w-14` — utility classes only, no semantic hook, so it is
         * addressed by its width utility under the metabox root. `:has()` picks the AI button's
         * row so the "Available after 5 published posts" caption goes with it rather than being
         * left behind as an orphan line.
         */
        #wpseo-metabox-root svg[class*="yst-w-14"],
        #wpseo-metabox-root div:has(> .yst-button--ai-secondary),
        #wpseo-metabox-root div:has(> .yst-button--ai-primary),
        #wpseo_meta .yst-button--ai-secondary,
        #wpseo_meta .yst-button--ai-primary,
        #wpseo_meta .yst-badge--ai,
        #wpseo_meta .yst-ai-gradient-border {
            display: none !important;
        }

        /*
         * --- Tab score glyphs ---
         *
         * The SEO and Readability tabs carry Yoast's smiley-face score icons as inline SVG
         * (`svg.yoast-svg-icon-seo-score-{good,ok,bad}`). The colour is real information, the
         * face is not, so the glyph is replaced with the same flat dot the list-table columns
         * use. `:has()` is what lets the container read its child's state class — there is no
         * state on the container itself.
         */
        #wpseo_meta .wpseo-score-icon-container > svg.yoast-svg-icon {
            display: none !important;
        }
        #wpseo_meta .wpseo-score-icon-container {
            position: relative !important;
            display: inline-block !important;
            width: 10px !important;
            height: 10px !important;
            flex: none !important;
        }
        #wpseo_meta .wpseo-score-icon-container::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 9999px;
            /* Unknown or not-yet-analysed states land here. */
            background: var(--rl-border-strong);
        }
        #wpseo_meta .wpseo-score-icon-container:has(.yoast-svg-icon-seo-score-good)::after { background: #16a34a; }
        #wpseo_meta .wpseo-score-icon-container:has(.yoast-svg-icon-seo-score-ok)::after   { background: #d97706; }
        #wpseo_meta .wpseo-score-icon-container:has(.yoast-svg-icon-seo-score-bad)::after  { background: #dc2626; }

        /*
         * --- Tab glyphs ---
         *
         * SEO and Readability keep theirs: `.wpseo-score-icon-container` holds the live score
         * bullet, which is information, and the rules below already flatten it onto our palette.
         * Schema and Social are decoration, so they get our icon set: Yoast's schema glyph is a
         * data-URI background, swapped for lucide `layout-grid` as a mask so it takes a token
         * colour instead of shipping its own.
         */
        #wpseo_meta .wpseo-schema-icon {
            background-image: none !important;
            background-color: var(--rl-fg-muted) !important;
            -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22black%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Crect width=%227%22 height=%227%22 x=%223%22 y=%223%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%2214%22 y=%223%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%2214%22 y=%2214%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%223%22 y=%2214%22 rx=%221%22/%3E%3C/svg%3E");
            mask-image: url("data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22black%22 stroke-width=%222%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Crect width=%227%22 height=%227%22 x=%223%22 y=%223%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%2214%22 y=%223%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%2214%22 y=%2214%22 rx=%221%22/%3E%3Crect width=%227%22 height=%227%22 x=%223%22 y=%2214%22 rx=%221%22/%3E%3C/svg%3E");
            -webkit-mask-repeat: no-repeat;
            mask-repeat: no-repeat;
            -webkit-mask-size: contain;
            mask-size: contain;
            width: 16px !important;
            height: 16px !important;
        }
        #wpseo_meta .wpseo-metabox-menu .dashicons {
            color: var(--rl-fg-muted) !important;
            width: 16px !important;
            height: 16px !important;
            font-size: 16px !important;
        }
        #wpseo_meta .wpseo-metabox-menu ul li.active .wpseo-schema-icon {
            background-color: var(--rl-fg) !important;
        }
        #wpseo_meta .wpseo-metabox-menu ul li.active .dashicons {
            color: var(--rl-fg) !important;
        }

        /* --- Score bullets: keep the signal, drop the gloss --- */
        .wpseo-score-icon,
        .wpseo-score-icon-container .wpseo-score-icon {
            border-radius: 9999px !important;
            box-shadow: none !important;
            background-image: none !important;
            width: 10px !important;
            height: 10px !important;
            border: 0 !important;
        }
        .wpseo-score-icon.good    { background-color: #16a34a !important; }
        .wpseo-score-icon.ok      { background-color: #d97706 !important; }
        .wpseo-score-icon.bad     { background-color: #dc2626 !important; }
        .wpseo-score-icon.na,
        .wpseo-score-icon.noindex { background-color: var(--rl-border-strong) !important; }
        .wpseo-score-text { font-size: 13px !important; color: var(--rl-fg-muted) !important; }
        /* The schema tab's blue grid glyph is decoration, not a score. */
        .wpseo-schema-icon { filter: grayscale(1) !important; opacity: .65 !important; }

        /*
         * --- Post/page list-table SEO columns ---
         *
         * Icon-width columns. WP core sets `.widefat { table-layout: fixed }`, so a column with
         * no declared width shares the remainder equally with every other undeclared column —
         * four extra columns squeeze the title column badly without these.
         */
        .wp-list-table th.column-wpseo-score,
        .wp-list-table th.column-wpseo-score-readability,
        .wp-list-table th.column-wpseo-links,
        .wp-list-table th.column-wpseo-linked {
            width: 74px !important;
            /* The tooltip below is absolutely positioned out of the cell. */
            overflow: visible !important;
        }
        .wp-list-table td.column-wpseo-score,
        .wp-list-table td.column-wpseo-score-readability { text-align: left !important; }
        .wp-list-table td.column-wpseo-score .wpseo-score-icon,
        .wp-list-table td.column-wpseo-score-readability .wpseo-score-icon {
            display: inline-block !important;
            vertical-align: middle !important;
        }

        /* The icon headings SeoEditor substitutes for Yoast's glyph spans. */
        .wp-list-table th .rl-seo-col {
            position: relative !important;
            display: inline-flex !important;
            align-items: center !important;
            text-decoration: none !important;
            line-height: 1 !important;
        }
        .wp-list-table th .rl-seo-col-icon {
            width: 16px !important;
            height: 16px !important;
            stroke: var(--rl-fg-muted) !important;
            display: block !important;
        }
        .wp-list-table th a:hover .rl-seo-col-icon,
        .wp-list-table th.sorted .rl-seo-col-icon { stroke: var(--rl-fg) !important; }

        /*
         * Tooltip, below the icon rather than above it.
         *
         * Yoast's own tooltip pointed upward, which put it behind the table's top border and
         * the row of sort arrows. Downward clears both. It only escapes the cell because
         * WordPressAdminTheme no longer sets `overflow: hidden` on .wp-list-table — see the
         * note on the corner-radius rules there.
         */
        .wp-list-table th .rl-seo-col::after {
            content: attr(data-rl-tip);
            position: absolute;
            top: calc(100% + 9px);
            left: 50%;
            transform: translateX(-50%);
            background: var(--rl-primary);
            color: var(--rl-primary-fg);
            padding: 5px 9px;
            border-radius: var(--rl-radius-sm);
            font-size: 11px;
            font-weight: 500;
            line-height: 1.4;
            letter-spacing: 0;
            text-transform: none;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .14);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            z-index: 100;
            transition: opacity .12s ease;
        }
        /* The little caret, pointing back up at the icon. */
        .wp-list-table th .rl-seo-col::before {
            content: "";
            position: absolute;
            top: calc(100% + 4px);
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-bottom-color: var(--rl-primary);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            z-index: 100;
            transition: opacity .12s ease;
        }
        .wp-list-table th .rl-seo-col:hover::after,
        .wp-list-table th .rl-seo-col:hover::before,
        .wp-list-table th a:focus .rl-seo-col::after,
        .wp-list-table th a:focus .rl-seo-col::before {
            opacity: 1;
            visibility: visible;
        }

        CSS;
    }

    /**
     * The replacement dashboard widget.
     *
     * Scoped to #rl_seo_overview so it cannot reach the other widgets on the dashboard,
     * which WordPressAdminTheme already styles.
     */
    public static function widgetCss(): string
    {
        return <<<'CSS'
        #rl_seo_overview .inside { padding: 0 !important; }
        .rl-seo-w { font-family: var(--rl-font); color: var(--rl-fg); }

        .rl-seo-w-section { padding: 16px 18px; border-bottom: 1px solid var(--rl-muted); }
        .rl-seo-w-section:last-child { border-bottom: 0; }
        .rl-seo-w-head {
            display: flex; align-items: baseline; justify-content: space-between;
            margin-bottom: 12px; gap: 12px;
        }
        .rl-seo-w-title {
            font-size: 11px; font-weight: 600; text-transform: uppercase;
            letter-spacing: .05em; color: var(--rl-fg-muted);
        }
        .rl-seo-w-meta { font-size: 11px; color: var(--rl-fg-subtle); }

        /* Score distribution bar */
        .rl-seo-bar {
            display: flex; height: 8px; border-radius: 9999px;
            overflow: hidden; background: var(--rl-muted); margin-bottom: 14px;
        }
        .rl-seo-bar span { display: block; height: 100%; }
        .rl-seo-bar .is-good { background: #16a34a; }
        .rl-seo-bar .is-ok { background: #d97706; }
        .rl-seo-bar .is-bad { background: #dc2626; }
        .rl-seo-bar .is-na { background: var(--rl-border-strong); }

        .rl-seo-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(108px, 1fr)); gap: 10px; }
        .rl-seo-legend a, .rl-seo-legend div {
            display: block;
            border: 1px solid var(--rl-border); border-radius: var(--rl-radius);
            padding: 9px 11px; background: var(--rl-surface); transition: all .15s ease;
        }
        /* See the note in AdminDesignSystem: WordPressAdminTheme paints `#wpbody-content a` and
           `#dashboard-widgets a` with an !important colour and underline at specificity (1,0,1),
           so a link styled as a tile needs an ID in the selector to keep its own appearance. */
        #wpbody-content .rl-seo-legend a,
        #wpbody-content a.rl-seo-row {
            text-decoration: none !important;
            color: var(--rl-fg) !important;
        }
        .rl-seo-legend a:hover { border-color: var(--rl-border-strong); background: var(--rl-subtle); }
        .rl-seo-legend-top { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
        .rl-seo-dot { width: 8px; height: 8px; border-radius: 9999px; flex: none; }
        .rl-seo-dot.is-good { background: #16a34a; }
        .rl-seo-dot.is-ok { background: #d97706; }
        .rl-seo-dot.is-bad { background: #dc2626; }
        .rl-seo-dot.is-na { background: var(--rl-border-strong); }
        .rl-seo-legend-label { font-size: 11px; font-weight: 600; color: var(--rl-fg-muted); }
        .rl-seo-legend-num { font-size: 20px; font-weight: 700; line-height: 1.1; letter-spacing: -0.02em; }

        /* Gap rows: missing meta, missing title */
        .rl-seo-rows { display: flex; flex-direction: column; gap: 7px; }
        .rl-seo-row {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            border: 1px solid var(--rl-border); border-radius: var(--rl-radius);
            padding: 10px 12px; background: var(--rl-surface); transition: all .15s ease;
        }
        #wpbody-content a.rl-seo-row:hover { border-color: var(--rl-border-strong); background: var(--rl-subtle); }
        .rl-seo-row-main { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .rl-seo-row-icon { width: 15px; height: 15px; flex: none; stroke: var(--rl-fg-subtle); }
        .rl-seo-row-label { font-size: 13px; font-weight: 500; }
        .rl-seo-row-sub { font-size: 11px; color: var(--rl-fg-muted); margin-top: 1px; }
        .rl-seo-row-val { font-size: 15px; font-weight: 700; flex: none; letter-spacing: -0.01em; }
        .rl-seo-pill {
            display: inline-block; border-radius: 9999px; padding: 2px 9px;
            font-size: 11px; font-weight: 600; line-height: 1.6; flex: none;
        }
        .rl-seo-pill.is-ok { background: var(--rl-ok-bg); color: var(--rl-ok); }
        .rl-seo-pill.is-warn { background: var(--rl-warn-bg); color: var(--rl-warn); }
        .rl-seo-pill.is-bad { background: var(--rl-bad-bg); color: var(--rl-bad); }

        .rl-seo-empty { font-size: 13px; color: var(--rl-fg-muted); padding: 4px 0; }
        CSS;
    }
}
