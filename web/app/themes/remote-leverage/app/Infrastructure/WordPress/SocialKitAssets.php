<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress;

use App\Support\SocialKit;
use Illuminate\Support\Facades\Vite;

/**
 * WordPress wiring for the ported `rl-social-kit` dashboard.
 *
 * The plugin hung its front-end off `wp_enqueue_scripts` + a `[rl_social_kit]` shortcode. Here
 * the kit is a Laravel route (`routes/web.php`) rendering
 * `resources/views/pages/social-media-kit.blade.php`, so there is no post to inspect and the
 * enqueue gate is the request path instead.
 *
 * The AJAX action names, the nonce action and the response shapes are unchanged, because
 * `resources/js/social-kit.js` is the plugin's file verbatim.
 */
class SocialKitAssets
{
    /** Script handle the localized `rl_social_kit_vars` is attached to. */
    private const SCRIPT_HANDLE = 'rl-social-kit-script';

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        add_action('wp_ajax_rl_social_kit_login', [$this, 'ajaxLogin']);
        add_action('wp_ajax_nopriv_rl_social_kit_login', [$this, 'ajaxLogin']);
        add_action('wp_ajax_rl_social_kit_upload_avatar', [$this, 'ajaxUploadAvatar']);

        // Vite builds ES modules. The plugin's script is plain jQuery with no imports, but
        // shipping it as a module is what the manifest describes, and module defer still runs
        // after the inline `rl_social_kit_vars` that `wp_localize_script` prints ahead of it.
        add_filter('script_loader_tag', [$this, 'moduleScriptTag'], 10, 3);
    }

    /**
     * Whether the current request is the Social Media Kit route.
     *
     * Matched on the request path rather than a WordPress query flag: the kit is a
     * route, so there is no queried object, and this fires correctly regardless of
     * whether `wp_enqueue_scripts` runs before or after the route resolves.
     */
    private function isSocialKitPage(): bool
    {
        return function_exists('request') && request()->is(SocialKit::SLUG);
    }

    /**
     * Enqueue the plugin's stylesheet and script, and localize `rl_social_kit_vars`.
     */
    public function enqueueAssets(): void
    {
        if (! $this->isSocialKitPage()) {
            return;
        }

        // The avatar picker prefers the WordPress media modal and only falls back to the AJAX
        // uploader when `wp.media` is absent — same as the plugin.
        if (is_user_logged_in()) {
            wp_enqueue_media();
        }

        wp_enqueue_style(
            'rl-social-kit-style',
            Vite::asset('resources/css/social-kit.css'),
            [],
            null
        );

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            Vite::asset('resources/js/social-kit.js'),
            ['jquery'],
            null,
            true
        );

        wp_localize_script(self::SCRIPT_HANDLE, 'rl_social_kit_vars', SocialKit::localized());
    }

    /**
     * Mark our built bundle as a module.
     */
    public function moduleScriptTag(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::SCRIPT_HANDLE || str_contains($tag, 'type="module"')) {
            return $tag;
        }

        return str_replace('<script ', '<script type="module" ', $tag);
    }

    /**
     * AJAX: log a member in from the kit's own login card.
     */
    public function ajaxLogin(): void
    {
        check_ajax_referer(SocialKit::NONCE_ACTION, 'security');

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';

        if ($username === '' || $password === '') {
            wp_send_json_error(['message' => 'Username and password are required fields.']);
        }

        $user = wp_signon([
            'user_login' => $username,
            'user_password' => $password,
            'remember' => true,
        ], is_ssl());

        if (is_wp_error($user)) {
            wp_send_json_error(['message' => 'Invalid username or password. Please try again.']);
        }

        wp_send_json_success(['message' => 'Successfully authenticated! Loading dashboard...']);
    }

    /**
     * AJAX: fallback avatar uploader for roles without media-library access (subscribers).
     *
     * Returns `data.url`, which script.js drops straight into `#sig-avatar-url`.
     */
    public function ajaxUploadAvatar(): void
    {
        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'Unauthorized user.']);
        }

        check_ajax_referer(SocialKit::NONCE_ACTION, 'security');

        if (empty($_FILES['avatar'])) {
            wp_send_json_error(['message' => 'No image file uploaded.']);
        }

        $file = $_FILES['avatar'];

        if (! function_exists('wp_handle_upload')) {
            require_once ABSPATH.'wp-admin/includes/file.php';
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if (! in_array($extension, SocialKit::AVATAR_EXTENSIONS, true)) {
            wp_send_json_error(['message' => 'Invalid file format. Only JPG, PNG, GIF, and WEBP are supported.']);
        }

        $movefile = wp_handle_upload($file, ['test_form' => false]);

        if ($movefile && ! isset($movefile['error'])) {
            wp_send_json_success([
                'url' => $movefile['url'],
                'message' => 'Image successfully uploaded!',
            ]);
        }

        wp_send_json_error(['message' => $movefile['error'] ?? 'Upload failed.']);
    }
}
