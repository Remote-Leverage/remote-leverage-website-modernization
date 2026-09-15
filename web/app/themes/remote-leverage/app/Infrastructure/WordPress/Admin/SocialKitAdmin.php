<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Support\SocialKit;

/**
 * Settings → RL Social Kit.
 *
 * Ported from the legacy `rl-social-kit` plugin's options screen, which was the one part of
 * that plugin left behind in the 2026-09-15 migration: the option names and defaults came
 * across, but values could only be changed with `wp option update`.
 *
 * Every field writes the same option name the plugin used, so an existing row keeps working
 * and nothing needs migrating. `SocialKit::OPTION_DEFAULTS` is the single source of truth for
 * the defaults — this screen never repeats them.
 */
class SocialKitAdmin
{
    private const SETTINGS_GROUP = 'rl_social_kit_settings_group';

    private const MENU_SLUG = 'rl-social-kit';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            'RL Social Media Kit Settings',
            'RL Social Kit',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
        );
    }

    public function registerSettings(): void
    {
        // company_logo is not in OPTION_DEFAULTS — SocialKit::companyLogo() resolves it at
        // read time, falling back to the bundled mark — but it is still a saved option, so it
        // has to be registered here or the field would render and silently never save.
        $options = array_unique([
            ...array_keys(SocialKit::OPTION_DEFAULTS),
            'rl_social_kit_company_logo',
        ]);

        foreach ($options as $option) {
            // The checkbox needs an explicit sanitizer: an unchecked box is simply absent from
            // the POST body, so without this it would never save as '0'.
            $args = $option === 'rl_social_kit_require_login'
                ? ['sanitize_callback' => static fn ($value): string => $value ? '1' : '0']
                : ['sanitize_callback' => 'sanitize_text_field'];

            register_setting(self::SETTINGS_GROUP, $option, $args);
        }
    }

    /**
     * The editable text fields, in the order the screen presents them.
     *
     * @return array<string, string> option name => label
     */
    public static function textFields(): array
    {
        return [
            'rl_social_kit_company_name' => 'Company Name',
            'rl_social_kit_company_website' => 'Company Website',
            'rl_social_kit_company_address' => 'Company Physical Address',
            'rl_social_kit_company_logo' => 'Company Logo URL',
            'rl_social_kit_global_linkedin' => 'Global LinkedIn URL',
            'rl_social_kit_global_twitter' => 'Global Twitter / X URL',
            'rl_social_kit_global_facebook' => 'Global Facebook URL',
            'rl_social_kit_global_instagram' => 'Global Instagram URL',
            'rl_social_kit_global_youtube' => 'Global YouTube URL',
        ];
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'remote-leverage'));
        }

        $gated = SocialKit::requireLogin();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('RL Social Media Kit Settings', 'remote-leverage'); ?></h1>

            <p>
                <?php esc_html_e('These values fill the email-signature templates and the social icon row on', 'remote-leverage'); ?>
                <a href="<?php echo esc_url(home_url('/'.SocialKit::SLUG.'/')); ?>">/<?php echo esc_html(SocialKit::SLUG); ?>/</a>.
                <?php esc_html_e('Leave a field blank to fall back to its built-in default.', 'remote-leverage'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Require Login', 'remote-leverage'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="rl_social_kit_require_login" value="1" <?php checked($gated); ?> />
                                <?php esc_html_e('Only signed-in WordPress users can open the Social Media Kit', 'remote-leverage'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Off by default: the kit is public so nobody needs an account just to download a banner. Turning it on restores the legacy login card.', 'remote-leverage'); ?>
                            </p>
                        </td>
                    </tr>

                    <?php foreach (self::textFields() as $option => $label) { ?>
                        <tr>
                            <th scope="row">
                                <label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($label); ?></label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="<?php echo esc_attr($option); ?>"
                                    name="<?php echo esc_attr($option); ?>"
                                    value="<?php echo esc_attr((string) get_option($option, '')); ?>"
                                    class="regular-text"
                                    placeholder="<?php echo esc_attr((string) (SocialKit::OPTION_DEFAULTS[$option] ?? '')); ?>"
                                />
                            </td>
                        </tr>
                    <?php } ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
