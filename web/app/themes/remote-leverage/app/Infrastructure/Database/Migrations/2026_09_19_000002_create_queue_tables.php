<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The backing tables for a real job queue.
 *
 * ## Why there is no queue today
 *
 * `QUEUE_CONNECTION` has never been set, so Acorn's default of `sync` applies and anything
 * dispatched runs inline. Nothing in the theme implements `ShouldQueue`; the deferred
 * integrations — Slack, the lead webhook, notification mail, HubSpot, Meta CAPI, PostHog —
 * use `dispatch(...)->afterResponse()`, which is a *terminating callback in the same PHP
 * process*, not a queued job. It has no retries, no record of a failure, and it dies with the
 * php-fpm child. When one of those calls fails, nothing anywhere says so.
 *
 * That pattern was itself invisible until 2026-09-18, when it turned out Acorn never terminates
 * on WordPress-rendered paths and every deferred job was being discarded silently — see
 * `ThemeServiceProvider::ensureApplicationTerminates()`, which forces it.
 *
 * ## Creating the tables does not switch anything on
 *
 * This migration is deliberately inert. `QUEUE_CONNECTION` stays unset, so the connection stays
 * `sync` and these tables stay empty until an environment opts in. That makes this safe to
 * deploy on its own and trivial to roll back — the switch is one environment variable, not a
 * code change.
 *
 * ## Names
 *
 * `jobs` and `failed_jobs` rather than `rl_jobs`/`rl_failed_jobs`, which would match the
 * application's own tables. These are framework infrastructure rather than domain tables, and
 * the stock names are what `queue:work`, `queue:failed` and `queue:retry` expect from Acorn's
 * `config/queue.php` with no configuration at all. The `wp_` prefix is still applied — Acorn
 * sets `'prefix' => $GLOBALS['wpdb']->prefix` — so these land as `wp_jobs` and `wp_failed_jobs`
 * beside `wp_rl_leads`. Switching to the `rl_` convention later is `DB_QUEUE_TABLE` plus
 * `queue.failed.table`, not a migration.
 *
 * The column shapes are Laravel's stock schema and must stay that way: `DatabaseQueue` and
 * `DatabaseFailedJobProvider` write these columns by name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');

                /*
                 * Indexed because the worker's hot query is "the next available job on this
                 * queue". Everything currently planned rides the `default` queue, but the index
                 * is what keeps that true cheaply once anything is split onto its own.
                 */
                $table->string('queue')->index();

                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');

                /*
                 * Unix timestamps, not `timestamp` columns, and not nullable-by-convention:
                 * `reserved_at` being null is how the driver tells an unclaimed job from one a
                 * worker is holding, and the reaper compares these numerically against
                 * `retry_after`.
                 */
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();

                /*
                 * The `database-uuids` driver Acorn defaults to keys retries on this rather than
                 * on the auto-increment id, so `queue:retry <uuid>` stays stable.
                 */
                $table->string('uuid')->unique();

                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};
