<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promote `msclkid` from the attribution blob to a first-class, indexed column.
 *
 * It has always been captured — `AttributionCollector::EXTRA` has carried it since the HandL
 * port — but into the `attribution` JSON, on the reasoning that the blob is for values that are
 * "audit trail rather than selection criteria, never used to filter or route".
 *
 * The cost alert makes it a selection criterion. `LeadPlatform` falls back to click IDs when
 * `utm_source` is missing, and without a column Microsoft is the one platform that cannot
 * participate: `gclid` and `fbclid` have columns, `msclkid` did not. The practical effect was a
 * Bing lead that lost its UTMs to a redirect staying unattributed forever while the equivalent
 * Google lead resolved, which quietly biases cost-per-booking against Google.
 *
 * Backfill is deliberately not attempted here. The historical values are in the JSON and could
 * be extracted, but JSON_EXTRACT is written differently on MySQL and on the SQLite the test
 * suite runs, and a migration that behaves differently in the two places is worse than one that
 * only goes forward. Old rows keep resolving by `utm_source` exactly as they do today.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rl_leads', 'msclkid')) {
            return;
        }

        Schema::table('rl_leads', function (Blueprint $table) {
            // 150 to match `gclid` and `fbclid`, widened by the 2026_09_17 migration for the
            // same reason those were: Microsoft's click IDs are long and get longer.
            $table->string('msclkid', 150)->nullable()->after('fbclid');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rl_leads', 'msclkid')) {
            return;
        }

        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn('msclkid');
        });
    }
};
