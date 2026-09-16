<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

/**
 * 16px marks for the lead timeline, so a row's source is identifiable without reading it.
 *
 * Rendered as one SVG sprite of `<symbol>`s printed once per page, with each row emitting a
 * seven-element `<use>`. Inlining the artwork per row instead would add roughly 4KB per entry to
 * a timeline that routinely runs to fifty of them.
 *
 * The marks are simplified brand glyphs, not reproductions: at sixteen pixels the colour and
 * silhouette carry all the recognition, and the point here is to tell Calendly from HubSpot at a
 * glance rather than to present anybody's logo.
 */
class IntegrationIcons
{
    /**
     * Activity-log actor domains and integration names, mapped to a symbol.
     *
     * Both vocabularies resolve here because the timeline interleaves two sources: activity log
     * entries keyed by `actor_domain`, and integration calls keyed by `integration`.
     */
    public const MAP = [
        // Activity log actor domains.
        'Lead' => 'rl',
        'Referral' => 'rl',
        'Scheduling' => 'calendly',
        'Tracking' => 'customerio',
        'HubSpot' => 'hubspot',
        'Slack' => 'slack',
        'OutgoingWebhook' => 'webhook',
        'EmailNotification' => 'email',

        // Integration call names.
        'calendly' => 'calendly',
        'hubspot' => 'hubspot',
        'slack' => 'slack',
        'stripe' => 'stripe',
        'google' => 'google',
        'customerio' => 'customerio',
        'posthog' => 'posthog',
        'zerobounce' => 'zerobounce',
        'webhook' => 'webhook',
        'other' => 'webhook',
    ];

    /**
     * The symbol for an actor domain or integration name.
     */
    public static function symbolFor(string $key): string
    {
        return self::MAP[$key] ?? self::MAP[strtolower($key)] ?? 'rl';
    }

    /**
     * A 16px icon referencing the sprite.
     */
    public static function icon(string $key, string $title = ''): string
    {
        $symbol = self::symbolFor($key);
        $label = $title !== '' ? $title : $key;

        return sprintf(
            '<svg class="rl-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" '
            .'focusable="false" style="flex:0 0 16px;vertical-align:-3px;"><title>%s</title>'
            .'<use href="#rl-ico-%s"></use></svg>',
            self::escape($label),
            self::escape($symbol)
        );
    }

    /**
     * Escape an attribute value, with or without WordPress loaded.
     */
    protected static function escape(string $value): string
    {
        return function_exists('esc_attr')
            ? (string) \esc_attr($value)
            : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * The sprite. Print once per page, before any call to icon().
     */
    public static function sprite(): string
    {
        $symbols = implode('', [
            self::rl(),
            self::calendly(),
            self::hubspot(),
            self::slack(),
            self::stripe(),
            self::google(),
            self::customerio(),
            self::posthog(),
            self::zerobounce(),
            self::webhook(),
            self::email(),
        ]);

        return '<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">'.$symbols.'</svg>';
    }

    /**
     * The real Remote Leverage mark, read from the brand asset so it cannot drift from it.
     *
     * Falls back to a monogram if the file is missing — an admin screen should not break because
     * an image moved.
     */
    protected static function rl(): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $path = self::brandMarkPath();
        $viewBox = '0 0 125 125';
        $inner = '';

        if (is_readable($path)) {
            $svg = (string) file_get_contents($path);

            if (preg_match('/viewBox="([^"]+)"/i', $svg, $m)) {
                $viewBox = $m[1];
            }

            // Everything between the outer <svg> tags; the wrapper carries sizing we replace.
            if (preg_match('/<svg[^>]*>(.*)<\/svg>/is', $svg, $m)) {
                $inner = $m[1];
            }
        }

        if (trim($inner) === '') {
            $viewBox = '0 0 16 16';
            $inner = '<rect width="16" height="16" rx="4" fill="#111"/>'
                .'<text x="8" y="11.5" font-size="8" font-family="system-ui,sans-serif" '
                .'font-weight="700" fill="#fff" text-anchor="middle">R</text>';
        }

        return $cached = '<symbol id="rl-ico-rl" viewBox="'.self::escape($viewBox).'">'.$inner.'</symbol>';
    }

    /**
     * Where the brand mark lives.
     *
     * Resolved through WordPress when it is loaded and from this file's own location otherwise,
     * so the sprite is usable outside a request — in tests, and in anything that renders a
     * timeline from the console.
     */
    protected static function brandMarkPath(): string
    {
        $relative = 'resources/images/logo-icon-black.svg';

        if (function_exists('get_theme_file_path')) {
            return (string) \get_theme_file_path($relative);
        }

        return dirname(__DIR__, 4).'/'.$relative;
    }

    protected static function calendly(): string
    {
        return '<symbol id="rl-ico-calendly" viewBox="0 0 16 16">'
            .'<circle cx="8" cy="8" r="8" fill="#006BFF"/>'
            .'<path d="M11.2 5.6a3.6 3.6 0 1 0 0 4.8" fill="none" stroke="#fff" '
            .'stroke-width="1.7" stroke-linecap="round"/>'
            .'</symbol>';
    }

    protected static function hubspot(): string
    {
        return '<symbol id="rl-ico-hubspot" viewBox="0 0 16 16">'
            .'<circle cx="11.6" cy="4.4" r="2" fill="#FF7A59"/>'
            .'<circle cx="5.6" cy="11" r="3.1" fill="none" stroke="#FF7A59" stroke-width="1.6"/>'
            .'<path d="M10.6 5.9 6.8 8.6" stroke="#FF7A59" stroke-width="1.6" stroke-linecap="round"/>'
            .'<path d="M5.6 7.9V5.2" stroke="#FF7A59" stroke-width="1.6" stroke-linecap="round"/>'
            .'</symbol>';
    }

    protected static function slack(): string
    {
        // The four-colour pinwheel, reduced to its four bars.
        return '<symbol id="rl-ico-slack" viewBox="0 0 16 16">'
            .'<rect x="6.4" y="1" width="3.2" height="7.4" rx="1.6" fill="#2EB67D"/>'
            .'<rect x="6.4" y="7.6" width="3.2" height="7.4" rx="1.6" fill="#ECB22E"/>'
            .'<rect x="1" y="6.4" width="7.4" height="3.2" rx="1.6" fill="#36C5F0"/>'
            .'<rect x="7.6" y="6.4" width="7.4" height="3.2" rx="1.6" fill="#E01E5A"/>'
            .'</symbol>';
    }

    protected static function stripe(): string
    {
        return '<symbol id="rl-ico-stripe" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" rx="3.4" fill="#635BFF"/>'
            .'<path d="M7.6 6.2c0-.5.45-.7 1.1-.7.95 0 2.15.3 3.1.8V3.9a8 8 0 0 0-3.1-.6c-2.55 0-4.25 1.35-4.25 3.6 0 3.5 4.75 2.9 4.75 4.4 0 .6-.5.8-1.2.8-1.05 0-2.4-.45-3.45-1.05v2.45c1.15.5 2.35.7 3.45.7 2.6 0 4.4-1.15 4.4-3.45 0-3.75-4.8-3.05-4.8-4.55Z" fill="#fff"/>'
            .'</symbol>';
    }

    protected static function google(): string
    {
        return '<symbol id="rl-ico-google" viewBox="0 0 16 16">'
            .'<path d="M15.7 8.18c0-.57-.05-1.11-.15-1.64H8v3.1h4.32a3.7 3.7 0 0 1-1.6 2.43v2.02h2.59c1.51-1.4 2.39-3.45 2.39-5.91Z" fill="#4285F4"/>'
            .'<path d="M8 16c2.16 0 3.97-.72 5.3-1.94l-2.59-2.01c-.72.48-1.64.77-2.71.77-2.08 0-3.85-1.41-4.48-3.3H.85v2.07A8 8 0 0 0 8 16Z" fill="#34A853"/>'
            .'<path d="M3.52 9.52a4.8 4.8 0 0 1 0-3.04V4.41H.85a8 8 0 0 0 0 7.18l2.67-2.07Z" fill="#FBBC05"/>'
            .'<path d="M8 3.18c1.18 0 2.23.4 3.06 1.2l2.29-2.29C11.96.79 10.16 0 8 0A8 8 0 0 0 .85 4.41l2.67 2.07C4.15 4.59 5.92 3.18 8 3.18Z" fill="#EA4335"/>'
            .'</symbol>';
    }

    protected static function customerio(): string
    {
        return '<symbol id="rl-ico-customerio" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" rx="3.4" fill="#7C3AED"/>'
            .'<path d="M3 9.4c1.3 0 1.6-3.2 2.9-3.2S7.4 11 8.7 11s1.6-4.8 2.9-4.8c.9 0 1.2 1.6 1.7 2.4" '
            .'fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            .'</symbol>';
    }

    protected static function posthog(): string
    {
        return '<symbol id="rl-ico-posthog" viewBox="0 0 16 16">'
            .'<rect width="16" height="16" rx="3.4" fill="#F54E00"/>'
            .'<path d="M3.4 12.2V8l4.2 4.2H3.4Zm0-5.8V3.6l8.6 8.6H8.8L3.4 6.4Z" fill="#fff"/>'
            .'</symbol>';
    }

    protected static function zerobounce(): string
    {
        return '<symbol id="rl-ico-zerobounce" viewBox="0 0 16 16">'
            .'<circle cx="8" cy="8" r="7.2" fill="none" stroke="#00B2E3" stroke-width="1.6"/>'
            .'<path d="M5 8.2 7.1 10.3 11 6" fill="none" stroke="#00B2E3" stroke-width="1.7" '
            .'stroke-linecap="round" stroke-linejoin="round"/>'
            .'</symbol>';
    }

    protected static function webhook(): string
    {
        // Generic share/node glyph: three connected points.
        return '<symbol id="rl-ico-webhook" viewBox="0 0 16 16">'
            .'<circle cx="12.2" cy="3.4" r="2.2" fill="none" stroke="#71717a" stroke-width="1.5"/>'
            .'<circle cx="12.2" cy="12.6" r="2.2" fill="none" stroke="#71717a" stroke-width="1.5"/>'
            .'<circle cx="3.6" cy="8" r="2.2" fill="none" stroke="#71717a" stroke-width="1.5"/>'
            .'<path d="m5.6 7 4.7-2.4M5.6 9l4.7 2.4" stroke="#71717a" stroke-width="1.5" stroke-linecap="round"/>'
            .'</symbol>';
    }

    protected static function email(): string
    {
        return '<symbol id="rl-ico-email" viewBox="0 0 16 16">'
            .'<rect x="1.2" y="3.2" width="13.6" height="9.6" rx="1.8" fill="none" '
            .'stroke="#71717a" stroke-width="1.5"/>'
            .'<path d="m2 4.6 6 4.2 6-4.2" fill="none" stroke="#71717a" stroke-width="1.5" '
            .'stroke-linecap="round" stroke-linejoin="round"/>'
            .'</symbol>';
    }
}
