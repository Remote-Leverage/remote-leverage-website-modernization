<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Tools\Services\OpenAiProxyGuard;
use App\Domains\Tools\Settings\ToolSettings;

/**
 * Tools → Legacy Tools.
 *
 * Configures the OpenAI proxy behind the Elementor-era browser tools — see
 * `docs/domains/tools.md` and `config/job-widget.php`.
 *
 * Under the WordPress **Tools** menu rather than Settings, and owning its own option rather
 * than renting space in the Lead settings blob: this configures the tool pages and nothing
 * else, and `LeadSettingsService` had already become the place every admin-set credential
 * landed regardless of which subsystem used it.
 *
 * The screen states which source is actually in effect. The whole reason this exists is that a
 * key can be set in an environment and still not arrive — so a form that shows an empty box
 * while the environment is supplying a key, or shows a saved key that the environment is
 * overriding, would answer the one question it is here to answer incorrectly.
 */
class ToolsAdmin
{
    private const SETTINGS_GROUP = 'rl_tools_settings_group';

    private const MENU_SLUG = 'rl-tools';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_management_page(
            'Legacy Tools',
            'Legacy Tools',
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::SETTINGS_GROUP, ToolSettings::OPENAI_KEY_OPTION, [
            'type' => 'string',
            /*
             * trim, not sanitize_text_field. An API key is opaque and must survive intact; the
             * stock sanitiser strips tags and would silently mangle a future key format rather
             * than fail, which presents as a valid-looking key that authenticates as nobody.
             */
            'sanitize_callback' => static fn ($value): string => trim((string) $value),
            'default' => '',
        ]);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'remote-leverage'));
        }

        $stored = ToolSettings::openAiApiKey();
        $fromEnvironment = trim((string) config('job-widget.api_key', ''));
        $enabled = (new OpenAiProxyGuard)->enabled();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Legacy Tools', 'remote-leverage'); ?></h1>

            <p>
                <?php esc_html_e('The Elementor-era tool pages call OpenAI through this site. They need a key, and without one their REST routes are never registered and every widget reports that no route was found.', 'remote-leverage'); ?>
            </p>

            <?php if ($enabled) { ?>
                <div class="notice notice-success inline">
                    <p>
                        <strong><?php esc_html_e('The tools are live.', 'remote-leverage'); ?></strong>
                        <?php if ($fromEnvironment !== '') { ?>
                            <?php esc_html_e('The key is coming from the OPENAI_API_KEY environment variable, which takes precedence over anything saved here.', 'remote-leverage'); ?>
                        <?php } else { ?>
                            <?php esc_html_e('The key is coming from this screen.', 'remote-leverage'); ?>
                        <?php } ?>
                    </p>
                </div>
            <?php } else { ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('The tools are off.', 'remote-leverage'); ?></strong>
                        <?php esc_html_e('No key is set in the environment or on this screen, so the routes are not registered.', 'remote-leverage'); ?>
                    </p>
                </div>
            <?php } ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(ToolSettings::OPENAI_KEY_OPTION); ?>">
                                <?php esc_html_e('OpenAI API key', 'remote-leverage'); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="<?php echo esc_attr(ToolSettings::OPENAI_KEY_OPTION); ?>"
                                name="<?php echo esc_attr(ToolSettings::OPENAI_KEY_OPTION); ?>"
                                value="<?php echo esc_attr($stored); ?>"
                                class="regular-text"
                                autocomplete="off"
                                placeholder="sk-..."
                            />
                            <p class="description">
                                <?php if ($fromEnvironment !== '') { ?>
                                    <strong><?php esc_html_e('OPENAI_API_KEY is set in this environment and wins.', 'remote-leverage'); ?></strong>
                                    <?php esc_html_e('A key saved here is kept as a fallback but is not what the tools are using.', 'remote-leverage'); ?>
                                    <br />
                                <?php } ?>
                                <?php esc_html_e('Requests are capped per visitor and restricted to a small model allowlist — see config/job-widget.php. Stored in the database, so treat it as you would any other credential on this screen.', 'remote-leverage'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('What this powers', 'remote-leverage'); ?></h2>
            <p>
                <?php esc_html_e('The job description generator, the resume and text optimisers, the job posting template generator, and the tools page.', 'remote-leverage'); ?>
                <?php esc_html_e('One widget on the tools page talks to a Cloudflare Worker instead and is not affected by this key.', 'remote-leverage'); ?>
            </p>
        </div>
        <?php
    }
}
