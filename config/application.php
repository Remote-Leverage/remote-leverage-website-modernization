<?php

/**
 * Your base production configuration goes in this file. Environment-specific
 * overrides go in their respective config/environments/{{WP_ENV}}.php file.
 *
 * A good default policy is to deviate from the production config as little as
 * possible. Try to define as much of your configuration in this file as you
 * can.
 */

use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Roots\WPConfig\Config;

use function Env\env;

// CONVERT_* + STRIP_QUOTES + LOCAL_FIRST
Env\Env::$options
    = Env\Env::CONVERT_BOOL
    | Env\Env::CONVERT_NULL
    | Env\Env::CONVERT_INT
    | Env\Env::STRIP_QUOTES
    | Env\Env::LOCAL_FIRST;

/**
 * Directory containing all of the site's files
 *
 * @var string
 */
$root_dir = dirname(__DIR__);

/**
 * Document Root
 *
 * @var non-falsy-string
 */
$webroot_dir = $root_dir.'/web';

/**
 * Use Dotenv to set required environment variables and load .env file in root
 * .env.local will override .env if it exists
 */
if (file_exists($root_dir.'/.env')) {
    $env_files = file_exists($root_dir.'/.env.local')
        ? ['.env', '.env.local']
        : ['.env'];

    $repository = RepositoryBuilder::createWithNoAdapters()
        ->addAdapter(EnvConstAdapter::class)
        ->addAdapter(PutenvAdapter::class)
        ->immutable()
        ->make();

    $dotenv = Dotenv\Dotenv::create($repository, $root_dir, $env_files, false);
    $dotenv->load();

    $dotenv->required(['WP_HOME', 'WP_SITEURL']);
    if (! env('DATABASE_URL')) {
        $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD']);
    }
}

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define('WP_ENV', env('WP_ENV') ?: 'production');

/**
 * Set WP_ENVIRONMENT_TYPE if not already defined
 */
if (! defined('WP_ENVIRONMENT_TYPE')) {
    $wp_environment_type = env('WP_ENVIRONMENT_TYPE');

    if ($wp_environment_type) {
        Config::define('WP_ENVIRONMENT_TYPE', $wp_environment_type);
    } elseif (in_array(WP_ENV, ['production', 'staging', 'development', 'local'], true)) {
        Config::define('WP_ENVIRONMENT_TYPE', WP_ENV);
    }
}

/**
 * Set WP_DEVELOPMENT_MODE if explicitly configured
 */
if (! defined('WP_DEVELOPMENT_MODE')) {
    $wp_development_mode = env('WP_DEVELOPMENT_MODE');

    if ($wp_development_mode) {
        Config::define('WP_DEVELOPMENT_MODE', $wp_development_mode);
    }
}

/**
 * URLs
 */
Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

/**
 * Custom Content Directory
 */
Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir.Config::get('CONTENT_DIR'));
Config::define('WP_CONTENT_URL', Config::get('WP_HOME').Config::get('CONTENT_DIR'));

/**
 * DB settings
 */
if (env('DB_SSL')) {
    Config::define('MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL);
}

Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

if (env('DATABASE_URL')) {
    $dsn = (object) parse_url(env('DATABASE_URL'));

    Config::define('DB_NAME', substr($dsn->path, 1));
    Config::define('DB_USER', $dsn->user);
    Config::define('DB_PASSWORD', isset($dsn->pass) ? $dsn->pass : null);
    Config::define('DB_HOST', isset($dsn->port) ? "{$dsn->host}:{$dsn->port}" : $dsn->host);
}

/**
 * Authentication Unique Keys and Salts
 */
Config::define('AUTH_KEY', env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY', env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY', env('NONCE_KEY'));
Config::define('AUTH_SALT', env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT', env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT', env('NONCE_SALT'));
Config::define('APP_KEY', env('APP_KEY'));

if (env('ACF_PRO_KEY')) {
    Config::define('ACF_PRO_LICENSE', env('ACF_PRO_KEY'));
}

/**
 * Redis object cache (Till Krüss Redis Object Cache drop-in).
 * Enabled when WP_REDIS_HOST is set; the drop-in is copied into web/app/object-cache.php at image build.
 */
if (env('WP_REDIS_HOST')) {
    Config::define('WP_REDIS_HOST', env('WP_REDIS_HOST'));
    Config::define('WP_REDIS_PORT', env('WP_REDIS_PORT') ?: 6379);
    Config::define('WP_REDIS_CLIENT', env('WP_REDIS_CLIENT') ?: 'phpredis');
    Config::define('WP_REDIS_PREFIX', env('WP_REDIS_PREFIX') ?: 'rl:');
    Config::define('WP_REDIS_DATABASE', env('WP_REDIS_DATABASE') ?: 0);
    Config::define('WP_REDIS_TIMEOUT', 1);
    Config::define('WP_REDIS_READ_TIMEOUT', 1);
    Config::define('WP_REDIS_MAXTTL', 86400);
    Config::define('WP_REDIS_GRACEFUL', true);

    if (env('WP_REDIS_PASSWORD')) {
        Config::define('WP_REDIS_PASSWORD', env('WP_REDIS_PASSWORD'));
    }

    if (env('WP_REDIS_SCHEME')) {
        Config::define('WP_REDIS_SCHEME', env('WP_REDIS_SCHEME'));
    }

    if ((env('WP_REDIS_SCHEME') ?: 'tcp') === 'tls') {
        Config::define('WP_REDIS_SSL_CONTEXT', [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]);
    }
}

/**
 * Custom Settings
 */
Config::define('AUTOMATIC_UPDATER_DISABLED', true);
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?: false);

// Disable the plugin and theme file editor in the admin
Config::define('DISALLOW_FILE_EDIT', true);

// Disable plugin and theme updates and installation from the admin
Config::define('DISALLOW_FILE_MODS', true);

/**
 * MCP Adapter autoloading.
 *
 * The plugin is installed from VCS and relocated here by composer/installers,
 * so it has no nested vendor/ of its own — its Autoloader looks for
 * WP_MCP_DIR/vendor/autoload_packages.php, never finds it, and bails before
 * Plugin::instance() ever runs. That is silent except for an admin notice,
 * and it takes the whole MCP server down with it.
 *
 * Bedrock's root autoloader already maps everything the plugin needs
 * (WP\MCP\ -> web/app/plugins/mcp-adapter/includes and WP\McpSchema\ ->
 * vendor/wordpress/php-mcp-schema/src), so this constant — the plugin's own
 * documented escape hatch — tells it to trust the ambient autoloader instead
 * of hunting for its own.
 */
Config::define('WP_MCP_AUTOLOAD', false);

// Limit the number of post revisions
Config::define('WP_POST_REVISIONS', env('WP_POST_REVISIONS') ?? true);

// Disable script concatenation
Config::define('CONCATENATE_SCRIPTS', false);

/**
 * WordFence
 *
 * `WFWAF_STORAGE_ENGINE` is the setting that makes WordFence viable on this stack. Its default
 * keeps firewall state — rules, blocked IPs, rate-limit counters, attack log — in flat files
 * under `wp-content/wflogs/`. That directory lives inside the application container, which is
 * immutable and rebuilt on every deploy, so the firewall would silently reset its learned state
 * and block list on each release.
 *
 * `mysqli` moves that state into the database, where it persists across rebuilds and is shared
 * by every ECS task rather than each one keeping its own partial view. This is not a workaround:
 * it is what WordFence selects for itself on WP Engine and Flywheel, which have the same
 * ephemeral-filesystem property (see `waf/bootstrap.php`).
 *
 * Read by `waf/bootstrap.php`, which the plugin loads itself (`wordfence.php:136`), so this
 * applies whether or not PHP's `auto_prepend_file` is pointed at the WAF. Without the prepend
 * WordFence runs in basic mode — after WordPress loads rather than before it — which is the
 * expected state here; extended protection would need the Docker image to set `auto_prepend_file`
 * and would not survive a rebuild on its own.
 *
 * Everything else about WordFence is configured in `config/wordfence.php` in the theme and
 * applied on deploy by `rl:wordfence`.
 */
Config::define('WFWAF_STORAGE_ENGINE', 'mysqli');

/**
 * Debugging Settings
 */
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', false);
Config::define('SCRIPT_DEBUG', false);
ini_set('display_errors', '0');

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__.'/environments/'.WP_ENV.'.php';

if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress
 */
if (! defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir.'/wp/');
}
