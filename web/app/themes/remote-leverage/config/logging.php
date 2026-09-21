<?php

declare(strict_types=1);

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * Where this application's log lines go.
 *
 * Acorn's own default is the `single` channel — one file under `storage_path('logs')`. On a laptop
 * that is exactly right. On ECS it means the log is written inside a container, and that has cost
 * two diagnoses in one day.
 *
 * ## What a per-task file actually costs
 *
 * The service runs more than one task. Every one of them writes its own
 * `web/app/cache/acorn/logs/laravel.log`, and every one of them is behind the load balancer, so a
 * request asking for the log reaches whichever task the ALB picks. Reading it twice in a row on
 * 2026-09-21 returned 356 bytes and then 85 — two different containers answering the same URL.
 *
 * That interacts badly with how scheduled work runs here. `docker/wp-cron.sh` fires on every task
 * each minute and `wp-cron.php` lets whichever one wins the `doing_cron` lock run the due jobs, so
 * the task that ran the hourly alert is, by design, not predictable — and with two tasks a line
 * explaining why it failed has a coin-flip chance of being on the one nobody read.
 *
 * And the file dies with the container. Three deploys went out on the afternoon of 2026-09-21 while
 * the marketing cost alert had stopped posting; by the time there was any way to read a log, every
 * task that had run the 14:00, 15:00 and 16:00 alerts had been replaced and taken its account of
 * what happened with it.
 *
 * ## Why stderr fixes both
 *
 * `docker/www.conf` sets `catch_workers_output = yes`, so php-fpm forwards what a worker writes to
 * stderr into its own error log, which is the container's stdout, which is CloudWatch. One stream
 * for the whole service, retained past the life of any container — the same route
 * `docker/wp-cron.sh` already takes deliberately when it writes to `/proc/1/fd/1`.
 *
 * The file stays as well. It is what `wp acorn rl:logs` reads over the abilities API, it needs no
 * AWS access, and it is the faster answer when the question is "what just happened on the box I am
 * talking to". Losing that to gain CloudWatch would be trading one blind spot for another.
 *
 * `LOG_STACK` still wins where it is set, so an environment that wants one or the other can say so
 * without a deploy.
 */
return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => (bool) env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single,stderr')),

            /*
             * False on purpose. A stack that swallows a broken handler is how logging silently
             * stops: if the log directory is unwritable — which it has been, on a task whose
             * volume permissions were wrong — the write should fail loudly rather than leave the
             * application believing it has a log.
             */
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        /*
         * Kept so `Log::channel('null')` and the deprecations channel above still resolve. This
         * file replaces Acorn's `channels` array wholesale rather than merging into it, so a
         * channel that is not named here does not exist.
         */
        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
