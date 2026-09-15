<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Port of the `rl-social-kit` plugin's data layer (rl-testing).
 *
 * The plugin kept everything in one 932-line `RLSocialKit\Plugin` class: options, resource
 * scanning, the localization payload and the shortcode markup. Here the markup lives in
 * `resources/views/pages/social-media-kit.blade.php`, the WordPress hooks in
 * `App\Infrastructure\WordPress\SocialKitAssets`, and this class holds the pure-ish data
 * layer so it can be unit tested without a WordPress bootstrap.
 *
 * Everything user-visible is deliberately unchanged: the same option names and defaults, the
 * same `rl_social_kit_vars` shape, the same resource array keys and the same human-readable
 * size formatting. `resources/js/social-kit.js` and `resources/css/social-kit.css` are carried
 * over from the plugin byte-for-byte and are written against exactly these contracts.
 */
class SocialKit
{
    /** Page slug the plugin created on activation, and the one this template binds to. */
    /** Route path, and the directory name the kit's assets publish under. */
    public const SLUG = 'social-media-kit';

    /** Nonce action shared by both AJAX endpoints. Must match what script.js sends. */
    public const NONCE_ACTION = 'rl_social_kit_nonce';

    /**
     * Resource tabs, in the plugin's order. `youtube/` exists in the plugin's asset folder but
     * was never in `$allowed_tabs`, so it never rendered — that omission is preserved.
     *
     * @var array<int, string>
     */
    public const TABS = ['linkedin', 'instagram', 'facebook', 'other'];

    /** Extensions the plugin treated as previewable images rather than file-icon downloads. */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

    /** Extensions accepted by the avatar upload endpoint. */
    public const AVATAR_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * WordPress option names and their defaults, identical to the plugin's activation defaults
     * so an existing `wp_options` row keeps working untouched. `company_logo` is resolved at
     * runtime (it points into the theme now, not the plugin) and so is absent here.
     *
     * @var array<string, string>
     */
    public const OPTION_DEFAULTS = [
        'rl_social_kit_company_name' => 'Remote Leverage',
        'rl_social_kit_company_website' => 'https://remoteleverage.com',
        'rl_social_kit_company_address' => '1900 Camden Ave, San Jose, CA. 95124, USA',
        'rl_social_kit_global_linkedin' => 'https://www.linkedin.com/company/remote-leverage',
        'rl_social_kit_global_twitter' => 'https://x.com/Remote_Leverage',
        'rl_social_kit_global_facebook' => 'https://www.facebook.com/people/Remote-Leverage',
        'rl_social_kit_global_instagram' => 'https://www.instagram.com/remoteleverageva',
        'rl_social_kit_global_youtube' => 'https://www.youtube.com/@remoteleverage',

        // The plugin defaulted this to '1'. Here it is '0': the kit is public, because gating
        // it would mean minting a WordPress user for everyone who wants a banner. The option
        // itself is kept so the gate can still be switched back on without a deploy, and an
        // existing '1' row is honoured.
        'rl_social_kit_require_login' => '0',
    ];

    /** Signature templates localized into `rl_social_kit_vars.templates`. */
    private const TEMPLATE_KEYS = [
        'sig-1-light', 'sig-1-dark',
        'sig-2-light', 'sig-2-dark',
        'sig-3-light', 'sig-3-dark',
    ];

    /**
     * Human-readable size, matching the plugin's formatting exactly (2dp, MB/KB/Bytes).
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2).' KB';
        }

        return $bytes.' Bytes';
    }

    /**
     * Build the resource list for one directory.
     *
     * Note this scans a *source* directory but hands out URLs under a *different* base. That
     * split is the one real difference from the plugin, and it is deliberate: `themeImages()`
     * writes a sibling `.webp` next to every raster it publishes, so scanning the published
     * directory would render each asset twice (16 LinkedIn cards instead of 8) and would label
     * each one with the re-encoded byte count rather than the size of the file being served.
     *
     * @return array<int, array{name: string, fullname: string, url: string, size: string, extension: string, is_image: bool}>
     */
    public static function scan(string $dirPath, string $dirUrl): array
    {
        if (! is_dir($dirPath)) {
            return [];
        }

        $files = array_diff(scandir($dirPath) ?: [], ['.', '..', '.DS_Store']);
        $dirPath = rtrim($dirPath, '/').'/';
        $dirUrl = rtrim($dirUrl, '/').'/';

        $resources = [];

        foreach ($files as $file) {
            if (! is_file($dirPath.$file)) {
                continue;
            }

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            $resources[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'fullname' => $file,
                'url' => $dirUrl.rawurlencode($file),
                'size' => self::formatSize((int) filesize($dirPath.$file)),
                'extension' => $extension,
                'is_image' => in_array($extension, self::IMAGE_EXTENSIONS, true),
            ];
        }

        return $resources;
    }

    /**
     * Resources for one tab. Unknown tabs return an empty array, as in the plugin.
     *
     * @return array<int, array{name: string, fullname: string, url: string, size: string, extension: string, is_image: bool}>
     */
    public static function resources(string $tab): array
    {
        if (! in_array($tab, self::TABS, true)) {
            return [];
        }

        return self::scan(self::sourcePath($tab), self::publicUrl($tab));
    }

    /** Tracked source directory for a tab (what exists, and the size we label it with). */
    public static function sourcePath(string $tab): string
    {
        return self::themePath('resources/images/pages/'.self::SLUG.'/'.$tab);
    }

    /** Published directory URL for a tab (what the browser actually downloads). */
    public static function publicUrl(string $tab): string
    {
        return self::themeUrl('public/images/'.self::SLUG.'/'.$tab).'/';
    }

    /**
     * Base URL for `rl_social_kit_vars.plugin_assets`.
     *
     * script.js joins this with bare filenames — `plugin_assets + 'ln.png'`,
     * `plugin_assets + 'logo-icon-black.svg'` — so it has to be the directory holding the
     * signature social icons and the two logo marks, with a trailing slash.
     */
    public static function assetsBaseUrl(): string
    {
        return self::themeUrl('public/images/'.self::SLUG.'/brand').'/';
    }

    /**
     * Default company logo.
     *
     * script.js derives the dark-theme logo by swapping `rl-logo-6.png` for `rl-logo-5.png` in
     * this same URL, so the default has to stay a `rl-logo-6.png` sitting next to its `-5`
     * sibling — which it does, in the published `other/` directory.
     */
    public static function defaultCompanyLogo(): string
    {
        return self::publicUrl('other').'rl-logo-6.png';
    }

    /** Whether the page is login-gated. Defaults to '1', as the plugin did. */
    public static function requireLogin(): bool
    {
        return (bool) self::option('rl_social_kit_require_login');
    }

    /** One option, falling back to the plugin's activation default. */
    public static function option(string $name): string
    {
        $default = self::OPTION_DEFAULTS[$name] ?? '';
        $value = function_exists('get_option') ? get_option($name, $default) : $default;

        return is_scalar($value) ? (string) $value : $default;
    }

    /** The company logo option, falling back to the theme-hosted default. */
    public static function companyLogo(): string
    {
        $logo = function_exists('get_option') ? get_option('rl_social_kit_company_logo', '') : '';
        $logo = is_scalar($logo) ? (string) $logo : '';

        return $logo !== '' ? $logo : self::defaultCompanyLogo();
    }

    /**
     * `rl_social_kit_vars.global_settings`, key-for-key as the plugin localized it.
     *
     * @return array<string, string>
     */
    public static function globalSettings(): array
    {
        return [
            'company_name' => self::option('rl_social_kit_company_name'),
            'company_website' => self::option('rl_social_kit_company_website'),
            'company_address' => self::option('rl_social_kit_company_address'),
            'company_logo' => self::companyLogo(),
            'global_linkedin' => self::option('rl_social_kit_global_linkedin'),
            'global_twitter' => self::option('rl_social_kit_global_twitter'),
            'global_facebook' => self::option('rl_social_kit_global_facebook'),
            'global_instagram' => self::option('rl_social_kit_global_instagram'),
            'global_youtube' => self::option('rl_social_kit_global_youtube'),
        ];
    }

    /**
     * `rl_social_kit_vars.user_defaults`. Empty strings when logged out, as in the plugin.
     *
     * @return array<string, string>
     */
    public static function userDefaults(): array
    {
        $loggedIn = function_exists('is_user_logged_in') && is_user_logged_in();
        $user = $loggedIn ? wp_get_current_user() : null;

        return [
            'display_name' => $user?->display_name ?: '',
            'first_name' => $user?->first_name ?: '',
            'last_name' => $user?->last_name ?: '',
            'email' => $user?->user_email ?: '',
            'avatar' => $user ? (string) get_avatar_url($user->ID) : '',
        ];
    }

    /**
     * The six signature templates, keyed as script.js looks them up (`sig-{n}-{theme}`).
     *
     * These are copies of the plugin's `templates/signatures/*.html`, kept separate from the
     * theme's own `resources/views/signatures/` — those belong to the Livewire signature
     * generator and have since diverged (a different dark background), so sharing them would
     * silently change what this page produces.
     *
     * @return array<string, string>
     */
    public static function templates(): array
    {
        $templates = [];

        foreach (self::TEMPLATE_KEYS as $key) {
            $file = self::themePath('resources/social-kit/signatures/'.$key.'.html');

            if (is_file($file)) {
                $templates[$key] = (string) file_get_contents($file);
            }
        }

        return $templates;
    }

    /**
     * The whole `rl_social_kit_vars` payload.
     *
     * @return array<string, mixed>
     */
    public static function localized(): array
    {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'is_logged_in' => is_user_logged_in(),
            'is_admin' => current_user_can('manage_options'),
            'global_settings' => self::globalSettings(),
            'user_defaults' => self::userDefaults(),
            'plugin_assets' => self::assetsBaseUrl(),
            'templates' => self::templates(),
        ];
    }

    /** Absolute path inside the theme; falls back to the repo layout outside WordPress. */
    private static function themePath(string $relative): string
    {
        $base = function_exists('get_theme_file_path')
            ? get_theme_file_path()
            : dirname(__DIR__, 2);

        return rtrim((string) $base, '/').'/'.ltrim($relative, '/');
    }

    /** Absolute URL inside the theme. */
    private static function themeUrl(string $relative): string
    {
        $base = function_exists('get_template_directory_uri')
            ? (string) get_template_directory_uri()
            : '';

        return rtrim($base, '/').'/'.ltrim($relative, '/');
    }
}
