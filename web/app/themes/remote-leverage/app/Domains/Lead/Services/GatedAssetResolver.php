<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use App\Support\MediaLibrary;

/**
 * Turns the slug a gated form posts into the file that form is gating.
 *
 * SECURITY: this is the only thing standing between the endpoint and an open
 * file proxy. The browser posts `asset=impact-report-2026`, never a URL or a
 * path, and anything not named in config/gated-assets.php resolves to null so
 * the caller can fail the request. Mirrors the posture of
 * PaymentGatewayBlockResolver, where the amount is likewise never taken from
 * the client.
 *
 * The URL itself is looked up by filename through MediaLibrary because the
 * uploads folder and attachment ID are environment-specific — the PDF is under
 * 2026/07 on production and under whatever month it was imported locally.
 */
class GatedAssetResolver
{
    /**
     * @return array{slug: string, title: string, url: string}|null
     *                                                              Null when the slug is not registered, or is registered but the file is
     *                                                              not in this install's media library.
     */
    public function resolve(string $slug): ?array
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $asset = config('gated-assets.'.$slug);

        if (! is_array($asset)) {
            return null;
        }

        $url = $this->url($asset);

        if ($url === '') {
            return null;
        }

        return [
            'slug' => $slug,
            'title' => (string) ($asset['title'] ?? $slug),
            'url' => $url,
        ];
    }

    /**
     * @return list<string>
     */
    public function registeredSlugs(): array
    {
        return array_keys((array) config('gated-assets', []));
    }

    /**
     * @param  array<string, mixed>  $asset
     */
    protected function url(array $asset): string
    {
        $override = trim((string) ($asset['url'] ?? ''));

        if ($override !== '') {
            return $override;
        }

        $filename = trim((string) ($asset['filename'] ?? ''));

        // MediaLibrary talks to $wpdb directly, which is absent outside a booted
        // WordPress (tests, WP-CLI before load). Those callers pin `url` instead.
        if ($filename === '' || ! isset($GLOBALS['wpdb']) || ! function_exists('wp_get_attachment_url')) {
            return '';
        }

        return MediaLibrary::url($filename);
    }
}
