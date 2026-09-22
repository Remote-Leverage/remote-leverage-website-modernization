<?php

declare(strict_types=1);

use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\CallableDispatcher;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

// 1. Initialize standalone container for Laravel Facades
$app = new Container;
Container::setInstance($app);
Facade::setFacadeApplication($app);

if (! function_exists('app')) {
    function app($abstract = null, array $parameters = [])
    {
        if ($abstract === null) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($abstract, $parameters);
    }
}

$app->singleton('log', function () {
    return new class
    {
        public function info($msg, array $ctx = []): void {}

        public function error($msg, array $ctx = []): void {}

        public function warning($msg, array $ctx = []): void {}

        public function debug($msg, array $ctx = []): void {}

        // The rest of PSR-3. The stub previously stopped at debug, so the first caller to
        // reach for a level it did not implement failed with "undefined method" inside a
        // facade rather than anywhere near the code under test.
        public function emergency($msg, array $ctx = []): void {}

        public function alert($msg, array $ctx = []): void {}

        public function critical($msg, array $ctx = []): void {}

        public function notice($msg, array $ctx = []): void {}

        public function log($level, $msg, array $ctx = []): void {}
    };
});

$app->singleton('events', function ($app) {
    return new Dispatcher($app);
});

$app->singleton('router', function ($app) {
    return new Router($app['events'], $app);
});

$app->singleton(
    Illuminate\Routing\Contracts\CallableDispatcher::class,
    fn ($app) => new CallableDispatcher($app)
);

$app->singleton(
    Illuminate\Routing\Contracts\ControllerDispatcher::class,
    fn ($app) => new ControllerDispatcher($app)
);

$app->singleton('validator', function ($app) {
    $translator = new Translator(
        new ArrayLoader,
        'en'
    );

    return new Factory($translator, $app);
});

/*
 * Acorn's storage path, which ErrorLogAbility resolves its log file through.
 *
 * A test writes a fixture log into a temporary directory and points this at it; without the
 * global, the ability would read this machine's real 2MB development log and the assertions would
 * be about whatever happened to be in it.
 */
if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = $GLOBALS['rl_test_storage_path'] ?? sys_get_temp_dir().'/rl-acorn-storage';

        return rtrim($base, '/').($path !== '' ? '/'.ltrim($path, '/') : '');
    }
}

$app->singleton('cache', function () {
    return new class
    {
        protected array $storage = [];

        public function get($key, $default = null)
        {
            return $this->storage[$key] ?? $default;
        }

        public function put($key, $value, $ttl = null)
        {
            $this->storage[$key] = $value;

            return true;
        }

        public function has($key)
        {
            return array_key_exists($key, $this->storage) && $this->storage[$key] !== null;
        }

        public function remember($key, $ttl, $callback)
        {
            if (! isset($this->storage[$key])) {
                $this->storage[$key] = $callback();
            }

            return $this->storage[$key];
        }

        public function forget($key)
        {
            unset($this->storage[$key]);

            return true;
        }

        public function flush()
        {
            $this->storage = [];

            return true;
        }

        /**
         * Enough of Laravel's lock contract for `MultistepBookingWizard::submitBooking()` to run.
         *
         * Its absence is why nothing had ever driven a booking end to end: the very first thing
         * that method does after building the lead data is take a `Cache::lock()`, so every test
         * that tried died on `Call to undefined method ::lock()` before reaching the booking. The
         * whole submit path — capture, the Scheduling listener, the retry ladder, the confirmation
         * — was unreachable from a test for that one reason.
         *
         * Single-process and honest about contention: a second `get()` on a name already held
         * returns false, which is what the double-submit guard is there to do.
         */
        public function lock($name, $seconds = 0, $owner = null)
        {
            return new class($this->locks, $name)
            {
                public function __construct(private array &$locks, private string $name) {}

                public function get($callback = null)
                {
                    if (! empty($this->locks[$this->name])) {
                        return false;
                    }

                    $this->locks[$this->name] = true;

                    return $callback === null ? true : $callback();
                }

                public function release()
                {
                    unset($this->locks[$this->name]);

                    return true;
                }

                public function forceRelease()
                {
                    return $this->release();
                }
            };
        }

        /** @var array<string, bool> */
        protected array $locks = [];
    };
});

// 2. Set up in-memory SQLite database for Eloquent models
$capsule = new Capsule($app);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Bind the manager as "db" so the DB facade resolves here the same way it does
// under Acorn — production code uses DB::table(), not Capsule directly.
$app->instance('db', $capsule->getDatabaseManager());
$app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());

// ReferralServiceProvider never boots here, so the domain's repository interface has to be
// bound by hand — anything resolving it through the container fails outright otherwise.
$app->bind(
    ReferrerRepositoryInterface::class,
    EloquentReferrerRepository::class
);

// Ensure test schema exists
if (! Capsule::schema()->hasTable('rl_referrers')) {
    Capsule::schema()->create('rl_referrers', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email');
        $table->string('referral_code');
        $table->string('company')->nullable();
        $table->string('password')->nullable();
        $table->string('stripe_account_id')->nullable();
        $table->string('status')->default('pending');
        $table->text('metadata')->nullable();
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('rl_payouts')) {
    Capsule::schema()->create('rl_payouts', function ($table) {
        $table->increments('id');
        $table->integer('referrer_id');
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('USD');
        $table->string('status')->default('pending');
        $table->string('stripe_transfer_id')->nullable();
        $table->text('referral_ids')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('rl_referrals')) {
    Capsule::schema()->create('rl_referrals', function ($table) {
        $table->increments('id');
        $table->integer('referrer_id')->nullable()->index();
        $table->integer('referrer_user_id')->nullable()->index();
        // The real foreign key to rl_leads, added 2026-09-17. Referrals used to be joined to
        // leads by email string only; see ReferralLeadMatcher.
        $table->unsignedInteger('lead_id')->nullable()->index();
        $table->string('lead_name');
        $table->string('lead_email')->default('')->index();
        $table->string('lead_phone')->default('');
        $table->text('landing_page')->nullable();
        $table->string('source')->default('manual_submission');
        $table->string('status')->default('pending')->index();
        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('rl_referral_clicks')) {
    Capsule::schema()->create('rl_referral_clicks', function ($table) {
        $table->increments('id');
        $table->integer('referrer_id')->nullable()->index();
        $table->integer('referrer_user_id')->nullable()->index();
        $table->string('referrer_slug')->index();
        $table->text('landing_page')->nullable();
        $table->string('ip_address')->nullable()->index();
        $table->text('user_agent')->nullable();
        $table->text('referer_url')->nullable();
        $table->timestamp('created_at')->nullable()->index();
    });
}

if (! Capsule::schema()->hasTable('rl_referral_rewards')) {
    Capsule::schema()->create('rl_referral_rewards', function ($table) {
        $table->increments('id');
        $table->integer('referrer_id')->nullable()->index();
        $table->integer('referrer_user_id')->nullable()->default(0)->index();
        $table->integer('referral_id')->default(0)->index();
        $table->string('reward_type')->default('cash');
        $table->decimal('amount', 10, 2)->default(0.00);
        $table->string('currency')->default('USD');
        $table->string('status')->default('due')->index();
        $table->string('description')->default('');
        $table->timestamp('issued_at')->nullable();
        $table->timestamp('created_at')->nullable()->index();
    });
}

if (! Capsule::schema()->hasTable('rl_leads')) {
    Capsule::schema()->create('rl_leads', function ($table) {
        $table->increments('id');
        $table->string('uuid')->unique();
        $table->string('name');
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email')->index();
        $table->string('phone')->nullable();
        $table->string('phone_country', 5)->nullable();
        $table->string('company')->nullable();
        $table->string('role_needed')->nullable();
        $table->string('weekly_hours')->nullable();
        $table->string('monthly_revenue', 100)->nullable();
        $table->string('start_date')->nullable();
        $table->text('notes')->nullable();
        $table->string('utm_source', 100)->nullable();
        $table->string('utm_medium', 100)->nullable();
        $table->string('utm_campaign', 100)->nullable();
        $table->string('utm_term', 100)->nullable();
        $table->string('utm_content', 100)->nullable();
        $table->string('gclid', 150)->nullable();
        $table->string('fbclid', 150)->nullable();
        $table->string('msclkid', 150)->nullable();
        $table->string('referral_code', 100)->nullable();
        $table->string('landing_url', 500)->nullable();
        $table->string('referrer_url', 500)->nullable();
        $table->timestamp('consent_at')->nullable();
        $table->string('session_id', 100)->nullable();

        // Attribution and identity added 2026-09-16. Kept in step with the migrations under
        // app/Infrastructure/Database/Migrations — a column missing here fails only in the
        // tests that write real rows, which is a confusing place to discover it.
        $table->string('utm_id', 150)->nullable();
        $table->string('li_fat_id', 150)->nullable();
        $table->string('fbc', 255)->nullable();
        $table->string('oppref', 150)->nullable();
        $table->string('partner', 150)->nullable();
        $table->string('data_source', 100)->nullable();
        $table->string('intake_form', 50)->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('scheduler_link')->nullable();
        $table->text('landing_page_base')->nullable();
        $table->string('timezone', 64)->nullable();
        $table->string('submission_type', 50)->nullable();
        $table->text('attribution')->nullable();
        $table->string('posthog_session_id', 100)->nullable();
        $table->string('device_id', 64)->nullable();
        $table->string('hubspot_contact_id', 50)->nullable();
        $table->string('hubspot_lifecycle_stage', 100)->nullable()->index();
        $table->timestamp('hubspot_lifecycle_changed_at')->nullable();
        $table->timestamp('hubspot_lifecycle_synced_at')->nullable();
        $table->string('slack_message_ts', 32)->nullable();
        $table->string('slack_channel_id', 32)->nullable();
        $table->text('slack_announced')->nullable();
        $table->unsignedInteger('profile_id')->nullable()->index();
        $table->boolean('is_blocked')->default(false)->index();

        $table->string('source_type')->default('organic')->index();
        $table->string('source_id')->nullable()->index();
        $table->string('status')->default('captured')->index();

        // 2026_09_09_000004_add_booking_retry_columns_to_leads_table.
        $table->unsignedTinyInteger('booking_retry_count')->default(0);
        $table->timestamp('booking_next_retry_at')->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}

if (! Capsule::schema()->hasTable('rl_lead_profiles')) {
    Capsule::schema()->create('rl_lead_profiles', function ($table) {
        $table->increments('id');
        $table->string('uuid')->unique();
        $table->string('status')->default('active')->index();
        $table->timestamp('blocked_at')->nullable();
        $table->string('blocked_by', 100)->nullable();
        $table->text('block_reason')->nullable();
        $table->unsignedInteger('merged_into_id')->nullable()->index();
        $table->timestamp('merged_at')->nullable();
        $table->unsignedInteger('lead_count')->default(0);
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('rl_lead_identifiers')) {
    Capsule::schema()->create('rl_lead_identifiers', function ($table) {
        $table->increments('id');
        $table->unsignedInteger('profile_id')->index();
        $table->string('type')->index();
        $table->string('value_hash', 64);
        $table->string('value_preview', 60)->nullable();
        $table->string('strength')->default('strong')->index();
        $table->timestamp('first_seen_at')->nullable();
        $table->timestamp('last_seen_at')->nullable();
        $table->unsignedInteger('seen_count')->default(1);
        $table->timestamps();
        $table->unique(['type', 'value_hash']);
    });
}

if (! Capsule::schema()->hasTable('rl_lead_activity_logs')) {
    Capsule::schema()->create('rl_lead_activity_logs', function ($table) {
        $table->increments('id');
        $table->integer('lead_id')->index();
        $table->string('event_type')->index();
        $table->string('actor_domain', 64)->index();
        $table->string('stage')->default('consumption')->index();
        $table->string('outcome')->default('succeeded')->index();
        $table->string('description', 500)->nullable();
        $table->text('payload')->nullable();
        $table->timestamp('created_at')->nullable();
    });
}

if (! Capsule::schema()->hasTable('rl_integration_calls')) {
    Capsule::schema()->create('rl_integration_calls', function ($table) {
        $table->increments('id');
        $table->integer('lead_id')->nullable()->index();
        $table->string('integration', 32)->index();
        $table->string('operation', 160)->nullable();
        $table->string('method', 10);
        $table->text('url');
        $table->string('credential_label', 190)->nullable()->index();
        $table->text('request_headers')->nullable();
        $table->text('request_body')->nullable();
        $table->integer('status_code')->nullable()->index();
        $table->text('response_headers')->nullable();
        $table->text('response_body')->nullable();
        $table->integer('duration_ms')->nullable();
        $table->string('outcome')->default('succeeded')->index();
        $table->text('error_message')->nullable();
        $table->timestamp('created_at')->nullable();
    });
}

// WordPress core tables the environment-sync exporter reads. Only the columns
// the sync actually touches — this is a shape for querying against, not a
// faithful reproduction of WordPress's schema.
if (! Capsule::schema()->hasTable('posts')) {
    Capsule::schema()->create('posts', function ($table) {
        $table->increments('ID');
        $table->integer('post_author')->default(0);
        $table->string('post_title')->default('');
        $table->text('post_content')->nullable();
        $table->string('post_status', 20)->default('publish');
        $table->string('post_type', 20)->default('post')->index();
        $table->string('post_name')->default('');
        $table->string('guid')->default('');
        $table->integer('post_parent')->default(0);
        $table->text('post_excerpt')->nullable();
        $table->string('comment_status', 20)->default('open');
        $table->string('ping_status', 20)->default('open');
        $table->string('post_password')->default('');
        $table->text('to_ping')->nullable();
        $table->text('pinged')->nullable();
        $table->text('post_content_filtered')->nullable();
        $table->integer('menu_order')->default(0);
        $table->string('post_mime_type')->default('');
        $table->integer('comment_count')->default(0);
        $table->string('post_date')->nullable();
        $table->string('post_date_gmt')->nullable();
        $table->string('post_modified')->nullable();
        $table->string('post_modified_gmt')->nullable();
    });
}

if (! Capsule::schema()->hasTable('postmeta')) {
    Capsule::schema()->create('postmeta', function ($table) {
        $table->increments('meta_id');
        $table->integer('post_id')->default(0)->index();
        $table->string('meta_key')->nullable();
        $table->text('meta_value')->nullable();
    });
}

// 3. Response helper stub for standalone CLI execution
if (! function_exists('response')) {
    function response()
    {
        return new class
        {
            public function json($data = [], int $status = 200, array $headers = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }
}

// 4. Global WordPress function stubs for isolated unit testing
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return is_scalar($str) ? trim(strip_tags((string) $str)) : '';
    }
}

if (! function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename)
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $filename);

        return trim((string) $filename, '.-_');
    }
}

if (! function_exists('absint')) {
    function absint($maybeint)
    {
        return abs((int) $maybeint);
    }
}

if (! function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        return $GLOBALS['_wp_mock_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option($option, $value)
    {
        $GLOBALS['_wp_mock_options'][$option] = $value;

        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option($option)
    {
        unset($GLOBALS['_wp_mock_options'][$option]);

        return true;
    }
}

if (! function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xFFFF), random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFFF)
        );
    }
}

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir()
    {
        $base = sys_get_temp_dir().'/rl-sync-tests';

        return ['basedir' => $base, 'baseurl' => 'https://remoteleverage.com/app/uploads'];
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target)
    {
        return is_dir($target) || mkdir($target, 0777, true);
    }
}

if (! function_exists('home_url')) {
    function home_url($path = '')
    {
        return 'https://remoteleverage.com'.($path ? '/'.ltrim($path, '/') : '');
    }
}

if (! function_exists('current_time')) {
    function current_time($type = 'mysql')
    {
        return date('Y-m-d H:i:s');
    }
}

if (! function_exists('now')) {
    function now()
    {
        // CarbonImmutable extends DateTimeImmutable, so this is a drop-in
        // replacement for any existing usage, but also supports subMinutes(),
        // addDays(), etc. — needed by app code (e.g. LeadActivityLogger)
        // that expects Laravel's real now() helper.
        return CarbonImmutable::now();
    }
}

if (! isset($GLOBALS['_test_session'])) {
    $GLOBALS['_test_session'] = [];
}

/*
 * Counts reads, not just values. ConversionHooks must not touch the session on a page with no
 * conversion to decorate: opening one sets a cookie, and docker/nginx.conf treats a Set-Cookie
 * as "never cache this", so a stray read from wp_footer would make the whole site uncacheable.
 * That is invisible in the rendered output, so it needs a counter to be testable at all.
 */
if (! isset($GLOBALS['_test_session_reads'])) {
    $GLOBALS['_test_session_reads'] = 0;
}

if (! function_exists('session')) {
    function session($key = null, $default = null)
    {
        if ($key === null) {
            return new class
            {
                public function forget($key)
                {
                    unset($GLOBALS['_test_session'][$key]);
                }

                public function get($key, $default = null)
                {
                    $GLOBALS['_test_session_reads']++;

                    return $GLOBALS['_test_session'][$key] ?? $default;
                }

                public function put($key, $value = null)
                {
                    $GLOBALS['_test_session'][$key] = $value;
                }
            };
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $GLOBALS['_test_session'][$k] = $v;
            }

            return null;
        }

        $GLOBALS['_test_session_reads']++;

        return $GLOBALS['_test_session'][$key] ?? $default;
    }
}

if (! function_exists('request')) {
    function request()
    {
        return new class
        {
            public function ip()
            {
                return $GLOBALS['_test_request_ip'] ?? '127.0.0.1';
            }

            public function query($key = null, $default = null)
            {
                return $GLOBALS['_test_request_query'][$key] ?? $default;
            }

            public function cookie($key = null, $default = null)
            {
                return $GLOBALS['_test_request_cookie'][$key] ?? $default;
            }
        };
    }
}

if (! isset($GLOBALS['_app_config'])) {
    $GLOBALS['_app_config'] = [];
}

if (! function_exists('config')) {
    function config($key = null, $default = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                Arr::set($GLOBALS['_app_config'], $k, $v);
            }

            return;
        }

        if ($key === null) {
            return $GLOBALS['_app_config'] ?? [];
        }

        return Arr::get($GLOBALS['_app_config'] ?? [], $key, $default);
    }
}

/*
 * WordPress' HTTP API, enough of it to exercise the outgoing webhook for real.
 *
 * Previously absent, which meant the webhook listener took its "no transport" branch and the
 * test asserting a successful dispatch was asserting a code path that never sends anything.
 * Tests set $GLOBALS['_wp_remote_post_response'] to steer the outcome.
 */
if (! class_exists('WP_Error')) {
    class WP_Error
    {
        /*
         * The third argument is not decoration: every WP_Error the theme returns from a REST
         * callback carries `['status' => n]` there, and that is what WordPress turns into the
         * HTTP status. Dropping it — as this stub did until 2026-09-21 — makes a test that
         * asserts a 429 or a 413 impossible to write.
         */
        public function __construct(
            protected string $code = '',
            protected string $message = '',
            protected mixed $data = '',
        ) {}

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_data($code = '')
        {
            return $this->data === '' ? null : $this->data;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = [])
    {
        $GLOBALS['_wp_remote_post_calls'][] = ['url' => $url, 'args' => $args];

        return $GLOBALS['_wp_remote_post_response']
            ?? ['response' => ['code' => 200], 'body' => '{"ok":true}', 'headers' => []];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return is_array($response) ? ($response['response']['code'] ?? 0) : 0;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return is_array($response) ? ($response['body'] ?? '') : '';
    }
}

if (! function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

/*
 * False by default: the bare test container renders no post, and `PageRobots` opens with this
 * check, so anything asserting a *forced* posture needs the unforced path to be reachable.
 */
if (! function_exists('is_singular')) {
    function is_singular($post_types = '')
    {
        return $GLOBALS['wp_is_singular'] ?? false;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, (int) $options, (int) $depth);
    }
}

if (! function_exists('esc_js')) {
    function esc_js($text)
    {
        // WordPress escapes for a single-quoted JS string literal and collapses newlines.
        return str_replace(
            ["\r", "\n"],
            ['', '\\n'],
            addcslashes((string) $text, "\\'\""),
        );
    }
}

/*
 * The environment WordPress believes it is running in.
 *
 * Settable, because it gates whether the GTM containers load at all
 * (App\Infrastructure\WordPress\Hooks\SiteKitHooks) and a test that cannot flip it can only
 * assert one side of that. Defaults to 'development' so anything production-gated stays off
 * unless a test asks for it.
 */
if (! function_exists('get_theme_file_path')) {
    /*
     * Where the theme is on disk.
     *
     * Needed by anything that reads a file it ships with — BigQueryClient loads the data team's
     * query from resources/sql this way. Without it that read throws, the client catches, and the
     * happy-path tests pass against a null they never asked for: green, and testing nothing.
     *
     * Guarded and settable, because two test files defined their own copy before this existed.
     */
    function get_theme_file_path($file = '')
    {
        $root = $GLOBALS['rl_theme_dir'] ?? dirname(__DIR__);

        return $file === '' ? $root : rtrim($root, '/').'/'.ltrim((string) $file, '/');
    }
}

if (! function_exists('wp_get_environment_type')) {
    function wp_get_environment_type()
    {
        return $GLOBALS['wp_environment_type'] ?? 'development';
    }
}

/*
 * The logged-in user, for SentryReporting::identifyUser(). Defaults to logged-out so every other
 * test suite — none of which sets these — keeps exercising the guest path unchanged.
 */
if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return (bool) ($GLOBALS['wp_current_user_logged_in'] ?? false);
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return (int) ($GLOBALS['wp_current_user_id'] ?? 0);
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        $user = new stdClass;
        $user->user_email = $GLOBALS['wp_current_user_email'] ?? '';

        return $user;
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
    }
}

if (! function_exists('is_email')) {
    function is_email($email)
    {
        return filter_var(trim((string) $email), FILTER_VALIDATE_EMAIL) ? trim((string) $email) : false;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        if ($extra_special_chars) {
            $chars .= '-_ []{}<>~`+=,.;:/?|';
        }

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return (int) ($GLOBALS['_wp_mock_current_user_id'] ?? 1);
    }
}

if (! function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return is_scalar($str) ? trim(strip_tags((string) $str)) : '';
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key));
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1)
    {
        return 'nonce-'.$action;
    }
}

if (! function_exists('wp_verify_nonce')) {
    /*
     * Actually verifies, rather than always returning true.
     *
     * It used to return true unconditionally, which is fine for the call sites that only need the
     * function to exist — but it makes a test of a CSRF guard assert nothing. The OAuth callback
     * arrives from Google with no referer to check, so `state` is the only guard it has, and a
     * test that cannot fail is worse than no test there.
     */
    function wp_verify_nonce($nonce, $action = -1)
    {
        return $nonce === wp_create_nonce($action);
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can($capability, ...$args)
    {
        /*
         * Defaults to true, which is what the admin-screen tests assume. Steerable so the
         * unprivileged path can be exercised too: set $GLOBALS['_wp_mock_capabilities'] to the
         * list of capabilities the current user has ([] for none). The referrer portal's demo
         * toggle is gated on `manage_options` and its guard is only meaningful if a test can
         * actually be someone without it.
         */
        $capabilities = $GLOBALS['_wp_mock_capabilities'] ?? null;

        return $capabilities === null || in_array($capability, (array) $capabilities, true);
    }
}

if (! function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (! function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://remoteleverage.com/wp-admin/'.ltrim($path, '/');
    }
}

if (! function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (! function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        $result = '<input type="hidden" name="'.$name.'" value="mock_nonce" />';
        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (! function_exists('wp_nonce_url')) {
    function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce')
    {
        return $actionurl.'&'.$name.'=mock_nonce';
    }
}

if (! function_exists('get_post_meta')) {
    function get_post_meta($postId, $key = '', $single = false)
    {
        return $GLOBALS['_wp_mock_post_meta'][$postId][$key] ?? ($single ? '' : []);
    }
}

if (! function_exists('update_post_meta')) {
    function update_post_meta($postId, $key, $value)
    {
        $GLOBALS['_wp_mock_post_meta'][$postId][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_post_meta')) {
    function delete_post_meta($postId, $key)
    {
        unset($GLOBALS['_wp_mock_post_meta'][$postId][$key]);

        return true;
    }
}

if (! function_exists('wp_update_post')) {
    function wp_update_post($postArr)
    {
        $postId = $postArr['ID'] ?? 0;
        $GLOBALS['_wp_mock_posts'][$postId] = array_merge($GLOBALS['_wp_mock_posts'][$postId] ?? [], $postArr);

        return $postId;
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
    {
        $GLOBALS['_wp_mock_mail_sent'][] = compact('to', 'subject', 'message');

        return true;
    }
}

if (! function_exists('remove_meta_box')) {
    function remove_meta_box($id, $page, $context)
    {
        $GLOBALS['rl_removed_meta_boxes'][] = $id;

        return true;
    }
}

if (! function_exists('wp_add_dashboard_widget')) {
    function wp_add_dashboard_widget($widget_id, $widget_name, $callback, $control_callback = null, $callback_args = null, $context = 'normal', $priority = 'core')
    {
        $GLOBALS['rl_added_dashboard_widgets'][] = $widget_id;

        return true;
    }
}

if (! class_exists('WP_Admin_Bar')) {
    class WP_Admin_Bar
    {
        public array $nodes = [];

        public function add_node(array $args): void
        {
            $this->nodes[$args['id']] = $args;
        }

        public function remove_node(string $id): void
        {
            unset($this->nodes[$id]);
        }

        public function get_node(string $id): ?array
        {
            return $this->nodes[$id] ?? null;
        }
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false)
    {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
        $text = strip_tags($text);

        return $remove_breaks ? trim(preg_replace('/[\r\n\t ]+/', ' ', $text)) : $text;
    }
}

if (! function_exists('strip_shortcodes')) {
    function strip_shortcodes($content)
    {
        return preg_replace('/\[\/?[^\]]+\]/', '', (string) $content);
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title($title)
    {
        $title = strtolower(trim((string) $title));
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/[^a-z0-9\s-]/', '', $title);
        $title = preg_replace('/[\s-]+/', '-', $title);

        return trim($title, '-');
    }
}

/*
|--------------------------------------------------------------------------
| Gutenberg block parsing
|--------------------------------------------------------------------------
|
| PageSectionEditor rewrites real block markup, so these load WordPress's own
| parser out of web/wp rather than approximating it. A hand-rolled parser here
| would only ever prove the tests agree with themselves — the whole value of
| the round-trip assertions is that they run through the same code the site
| does. The four wrappers below are copies of the WordPress implementations
| (wp-includes/blocks.php), which depend on nothing but the parser class.
|
*/

if (! class_exists('WP_Block_Parser')) {
    $wpIncludes = dirname(__DIR__, 4).'/wp/wp-includes/';

    require_once $wpIncludes.'class-wp-block-parser-block.php';
    require_once $wpIncludes.'class-wp-block-parser-frame.php';
    require_once $wpIncludes.'class-wp-block-parser.php';
}

if (! function_exists('parse_blocks')) {
    function parse_blocks($content)
    {
        return (new WP_Block_Parser)->parse($content);
    }
}

if (! function_exists('serialize_block_attributes')) {
    function serialize_block_attributes($block_attributes)
    {
        $encoded = json_encode($block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return strtr($encoded, [
            '\\\\' => '\\u005c',
            '--' => '\\u002d\\u002d',
            '<' => '\\u003c',
            '>' => '\\u003e',
            '&' => '\\u0026',
            '\\"' => '\\u0022',
        ]);
    }
}

if (! function_exists('serialize_block')) {
    function serialize_block($block)
    {
        $content = '';
        $index = 0;

        foreach ($block['innerContent'] as $chunk) {
            $content .= is_string($chunk) ? $chunk : serialize_block($block['innerBlocks'][$index++]);
        }

        if (! is_array($block['attrs'])) {
            $block['attrs'] = [];
        }

        if ($block['blockName'] === null) {
            return $content;
        }

        $name = str_starts_with($block['blockName'], 'core/')
            ? substr($block['blockName'], 5)
            : $block['blockName'];

        $attrs = empty($block['attrs']) ? '' : serialize_block_attributes($block['attrs']).' ';

        if (empty($content)) {
            return sprintf('<!-- wp:%s %s/-->', $name, $attrs);
        }

        return sprintf('<!-- wp:%s %s-->%s<!-- /wp:%s -->', $name, $attrs, $content, $name);
    }
}

if (! function_exists('serialize_blocks')) {
    function serialize_blocks($blocks)
    {
        return implode('', array_map('serialize_block', $blocks));
    }
}

if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public $ID = 0;

        public $post_title = '';

        public $post_name = '';

        public $post_status = 'publish';

        public $post_type = 'post';

        public function __construct(array $attributes = [])
        {
            foreach ($attributes as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}

if (! function_exists('get_field')) {
    /**
     * ACF reader. Backed by the same post-meta store as get_post_meta() so a
     * test can seed an ACF-authored value with update_post_meta().
     */
    function get_field($key, $postId = false, $formatValue = true)
    {
        return $GLOBALS['_wp_mock_post_meta'][$postId][$key] ?? null;
    }
}

/*
|--------------------------------------------------------------------------
| Asset pipeline, screens and escaping
|--------------------------------------------------------------------------
|
| Added for the SEO admin surfaces (App\Infrastructure\WordPress\Admin\Seo).
| The enqueue stubs record into globals so a test can assert what a screen
| registered, dequeued, or hung inline CSS off.
|
*/

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        $GLOBALS['rl_added_filters'][$hook][] = $callback;

        return true;
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args)
    {
        return $value;
    }
}

if (! function_exists('remove_all_actions')) {
    function remove_all_actions($hook, $priority = false)
    {
        $GLOBALS['rl_cleared_actions'][] = $hook;
        unset($GLOBALS['rl_added_actions'][$hook]);

        return true;
    }
}

if (! function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all')
    {
        $GLOBALS['rl_registered_styles'][$handle] = $deps;

        return true;
    }
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
    {
        $GLOBALS['rl_enqueued_styles'][] = $handle;

        return true;
    }
}

if (! function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data)
    {
        $GLOBALS['rl_inline_styles'][$handle][] = $data;

        return true;
    }
}

if (! function_exists('wp_dequeue_style')) {
    function wp_dequeue_style($handle)
    {
        $GLOBALS['rl_dequeued_styles'][] = $handle;
    }
}

if (! function_exists('wp_dequeue_script')) {
    function wp_dequeue_script($handle)
    {
        $GLOBALS['rl_dequeued_scripts'][] = $handle;
    }
}

if (! function_exists('wp_style_is')) {
    /**
     * Registered-ness is seeded by a test through $GLOBALS['rl_known_styles'].
     */
    function wp_style_is($handle, $list = 'enqueued')
    {
        return in_array($handle, $GLOBALS['rl_known_styles'] ?? [], true);
    }
}

if (! class_exists('WP_Screen')) {
    class WP_Screen
    {
        public string $id = '';

        public string $base = '';

        public function __construct(string $id = '', string $base = '')
        {
            $this->id = $id;
            $this->base = $base;
        }
    }
}

if (! function_exists('get_current_screen')) {
    function get_current_screen()
    {
        return $GLOBALS['rl_current_screen'] ?? null;
    }
}

if (! function_exists('get_edit_post_link')) {
    function get_edit_post_link($post = 0, $context = 'display')
    {
        return 'http://example.test/wp/wp-admin/post.php?post='.(int) $post.'&action=edit';
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = [])
    {
        throw new RuntimeException('wp_die: '.(is_string($message) ? $message : ''));
    }
}

if (! function_exists('get_post_types')) {
    function get_post_types($args = [], $output = 'names', $operator = 'and')
    {
        return $GLOBALS['rl_post_types'] ?? ['post' => 'post', 'page' => 'page'];
    }
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

/*
 * In-memory transients. No expiry clock: every caller here wants "is it cached", and a test
 * that needs a cold cache clears $GLOBALS['_wp_mock_transients'] rather than waiting.
 */
if (! function_exists('get_transient')) {
    function get_transient($key)
    {
        return $GLOBALS['_wp_mock_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0)
    {
        $GLOBALS['_wp_mock_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient($key)
    {
        unset($GLOBALS['_wp_mock_transients'][$key]);

        return true;
    }
}

/*
 * There is no users table in the unit suite, so "no such user" is the only
 * honest answer. It is enough to exercise the paths that branch on absence —
 * SyncCredentialProvisioner::revoke() returning early, for one. A test that
 * needs a real user should stub WP_User and override this, not lean on it.
 */
if (! function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        return false;
    }
}
