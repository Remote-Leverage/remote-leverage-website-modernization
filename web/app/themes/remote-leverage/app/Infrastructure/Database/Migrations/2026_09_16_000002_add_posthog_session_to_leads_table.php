<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostHog's own session id, so a lead's timeline can link to its session replay.
 *
 * Distinct from `session_id`, which is a UUID this application mints for its own correlation.
 * PostHog's replay URL is keyed by PostHog's id and nothing else, so the two cannot be merged —
 * and using ours would produce a link that always 404s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('posthog_session_id', 100)->nullable()->index()->after('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn('posthog_session_id');
        });
    }
};
