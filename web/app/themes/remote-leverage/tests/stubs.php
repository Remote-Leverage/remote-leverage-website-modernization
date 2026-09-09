<?php

declare(strict_types=1);

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

// Ensure test schema exists
if (! Capsule::schema()->hasTable('rl_partners')) {
    Capsule::schema()->create('rl_partners', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email');
        $table->string('referral_code');
        $table->string('company')->nullable();
        $table->string('stripe_account_id')->nullable();
        $table->string('status')->default('pending');
        $table->text('metadata')->nullable();
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('rl_payouts')) {
    Capsule::schema()->create('rl_payouts', function ($table) {
        $table->increments('id');
        $table->integer('partner_id');
        $table->decimal('amount', 10, 2);
        $table->string('currency')->default('USD');
        $table->string('status')->default('pending');
        $table->string('stripe_transfer_id')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();
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
        $table->string('referral_code', 100)->nullable();
        $table->string('landing_url', 500)->nullable();
        $table->string('referrer_url', 500)->nullable();
        $table->string('session_id', 100)->nullable();
        $table->string('source_type')->default('organic')->index();
        $table->string('source_id')->nullable()->index();
        $table->string('status')->default('captured')->index();
        $table->timestamps();
        $table->softDeletes();
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
        return new DateTimeImmutable;
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

if (! function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://remoteleverage.com/wp-admin/'.ltrim($path, '/');
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
