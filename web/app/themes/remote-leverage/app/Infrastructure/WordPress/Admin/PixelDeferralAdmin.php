<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Support\PixelDeferral;

/**
 * Settings → Marketing Pixels.
 *
 * Chooses which pixels hold their SDK fetch until the `rlDefer` flush. It replaced the
 * `PIXEL_DEFER_VENDORS` environment variable on 2026-09-23. See `PixelDeferral` for why, and
 * for the precedence between this screen and `config/pixels.php`.
 *
 * The trade each checkbox makes is ad-platform signal against mobile LCP. Deferring moves the
 * SDK off the critical path, and a visitor who leaves before the flush then never sends the
 * browser PageView. For Meta that costs a landing page view, which is what the ad platform
 * optimises on.
 */
class PixelDeferralAdmin
{
    private const SETTINGS_GROUP = 'rl_pixel_deferral_settings_group';

    private const MENU_SLUG = 'rl-marketing-pixels';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            'Marketing Pixels',
            'Marketing Pixels',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
        );
    }

    public function registerSettings(): void
    {
        // An empty selection posts nothing at all, so the sanitizer has to turn a missing field
        // into [] or unticking every box would never save.
        register_setting(self::SETTINGS_GROUP, PixelDeferral::VENDORS_OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitizeVendors'],
        ]);

        register_setting(self::SETTINGS_GROUP, PixelDeferral::TIMEOUT_OPTION, [
            'type' => 'integer',
            'sanitize_callback' => [self::class, 'sanitizeTimeout'],
        ]);
    }

    /**
     * @return list<string>
     */
    public static function sanitizeVendors(mixed $value): array
    {
        $submitted = array_map(static fn ($v): string => (string) $v, (array) ($value ?? []));

        return array_values(array_intersect(array_keys(PixelDeferral::DEFERRABLE), $submitted));
    }

    public static function sanitizeTimeout(mixed $value): int
    {
        return is_numeric($value)
            ? max(0, min(30000, (int) $value))
            : PixelDeferral::DEFAULT_TIMEOUT_MS;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'remote-leverage'));
        }

        $deferred = PixelDeferral::vendors();
        $timeout = PixelDeferral::timeoutMs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Marketing Pixels', 'remote-leverage'); ?></h1>

            <p>
                <?php esc_html_e('A deferred pixel still records every event immediately; only its SDK download waits until the visitor interacts, the page finishes loading, the browser goes idle, or the timeout below, whichever comes first. That keeps it off the mobile critical path, at the cost of losing the browser page view from anyone who leaves before then.', 'remote-leverage'); ?>
            </p>
            <p>
                <?php esc_html_e('Changes reach visitors once the page cache turns over, usually within a couple of minutes.', 'remote-leverage'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Defer these pixels', 'remote-leverage'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach (PixelDeferral::DEFERRABLE as $vendor => $label) { ?>
                                    <label style="display:block;margin-bottom:6px">
                                        <input
                                            type="checkbox"
                                            name="<?php echo esc_attr(PixelDeferral::VENDORS_OPTION); ?>[]"
                                            value="<?php echo esc_attr($vendor); ?>"
                                            <?php checked(in_array($vendor, $deferred, true)); ?>
                                        />
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php } ?>
                            </fieldset>
                            <p class="description">
                                <?php esc_html_e('Meta counts landing page views from the browser page view, so deferring it lowers the landing page views reported in Ads Manager. Pixels that are switched off in code are unaffected by this setting.', 'remote-leverage'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(PixelDeferral::TIMEOUT_OPTION); ?>"><?php esc_html_e('Maximum wait (ms)', 'remote-leverage'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                min="0"
                                max="30000"
                                step="100"
                                id="<?php echo esc_attr(PixelDeferral::TIMEOUT_OPTION); ?>"
                                name="<?php echo esc_attr(PixelDeferral::TIMEOUT_OPTION); ?>"
                                value="<?php echo esc_attr((string) $timeout); ?>"
                                class="small-text"
                            />
                            <p class="description">
                                <?php esc_html_e('Upper bound before deferred pixels load regardless. 6000 clears the hero on a slow mobile connection.', 'remote-leverage'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
