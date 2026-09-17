<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the HubSpot contact lifecycle stage onto the lead.
 *
 * HubSpot is the system of record for where a deal stands; this is a local cache of it so the
 * referrer portal can answer "what is happening with my referral" and "has it gone quiet"
 * without an API call per page view.
 *
 * `hubspot_lifecycle_changed_at` is the staleness clock and is deliberately **not**
 * `updated_at`: it moves only when the stage value itself changes, so re-syncing an unchanged
 * lead every hour does not reset it. `hubspot_lifecycle_synced_at` records the last time we
 * successfully looked, which is what distinguishes "nothing has happened for 5 days" from
 * "the sync has been broken for 5 days" — those look identical otherwise and only one of them
 * is the referrer's problem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('hubspot_lifecycle_stage', 100)->nullable()->index()->after('hubspot_contact_id');
            $table->timestamp('hubspot_lifecycle_changed_at')->nullable()->after('hubspot_lifecycle_stage');
            $table->timestamp('hubspot_lifecycle_synced_at')->nullable()->after('hubspot_lifecycle_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropIndex(['hubspot_lifecycle_stage']);
            $table->dropColumn([
                'hubspot_lifecycle_stage',
                'hubspot_lifecycle_changed_at',
                'hubspot_lifecycle_synced_at',
            ]);
        });
    }
};
