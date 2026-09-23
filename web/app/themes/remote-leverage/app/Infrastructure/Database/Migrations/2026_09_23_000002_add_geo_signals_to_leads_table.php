<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a lead is, inferred rather than asked. See `LeadGeoSignals`.
 *
 * `phone_country` already exists and is not this: it is the dialling code, and a US number is
 * routinely held by someone outside the US.
 *
 * No backfill. `ip_address` is on older rows, but the country comes from the edge's lookup at
 * request time and there is no GeoIP database in this stack to replay it against.
 */
return new class extends Migration
{
    private const COLUMNS = ['ip_country', 'browser_timezone', 'browser_language', 'country', 'country_source'];

    public function up(): void
    {
        if (Schema::hasColumn('rl_leads', 'country')) {
            return;
        }

        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('ip_country', 2)->nullable()->after('ip_address');
            $table->string('browser_timezone', 64)->nullable()->after('ip_country');
            $table->string('browser_language', 35)->nullable()->after('browser_timezone');
            // Indexed: the one of the five that is filtered and grouped on.
            $table->string('country', 2)->nullable()->index()->after('browser_language');
            $table->string('country_source', 20)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rl_leads', 'country')) {
            return;
        }

        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropIndex(['country']);
            $table->dropColumn(self::COLUMNS);
        });
    }
};
