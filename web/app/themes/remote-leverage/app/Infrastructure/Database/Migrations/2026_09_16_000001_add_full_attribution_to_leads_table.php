<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring the Lead domain up to what the legacy Gravity Form actually collected.
 *
 * The GF "Lead Routing Form v2 - VA" (form 20) carries ~45 attribution fields; v2 captured 11
 * of them. This adds the rest in two tiers, which is the whole design decision:
 *
 *  - **First-class columns** for the fields HubSpot has a property for. Those are queried,
 *    indexed and synced, so they earn a column.
 *  - **One `attribution` JSON column** for everything else — the HandL first-touch and
 *    metadata set — *plus any query parameter the code does not know about*. A new ad platform's
 *    click id lands there on the day it first appears, with no deploy, and can be promoted to a
 *    column and a HubSpot property later. Without it, an unrecognised parameter is simply lost,
 *    and nobody finds out until someone asks why a campaign has no attribution.
 *
 * ~35 separate columns for the HandL set was the alternative. It would be mostly-null, painful
 * to migrate every time marketing adds a parameter, and no more queryable in practice — the
 * fields it holds are audit trail, not selection criteria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            // Mapped to HubSpot properties — see HubSpotGateway::propertiesFor().
            $table->string('utm_id', 150)->nullable()->index()->after('utm_content');
            $table->string('li_fat_id', 150)->nullable()->after('fbclid');
            $table->string('fbc', 255)->nullable()->after('li_fat_id');
            $table->string('oppref', 150)->nullable()->after('fbc');
            $table->string('partner', 150)->nullable()->index()->after('oppref');
            $table->string('data_source', 100)->nullable()->after('partner');
            $table->string('intake_form', 50)->nullable()->after('data_source');
            $table->string('ip_address', 45)->nullable()->after('intake_form');
            $table->text('scheduler_link')->nullable()->after('ip_address');
            $table->text('landing_page_base')->nullable()->after('landing_url');

            // Captured and shown, not currently synced.
            $table->string('timezone', 64)->nullable()->after('session_id');
            $table->string('submission_type', 50)->nullable()->after('timezone');

            /*
             * Everything else the form carried, and anything the URL carries that this schema
             * does not name. Nullable rather than defaulted to '{}' so "never populated" and
             * "populated with nothing" stay distinguishable — the first means a lead predates
             * this migration, the second means the visitor genuinely arrived clean.
             */
            $table->json('attribution')->nullable()->after('submission_type');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn([
                'utm_id',
                'li_fat_id',
                'fbc',
                'oppref',
                'partner',
                'data_source',
                'intake_form',
                'ip_address',
                'scheduler_link',
                'landing_page_base',
                'timezone',
                'submission_type',
                'attribution',
            ]);
        });
    }
};
