<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HubSpot contact id, so a lead can be opened in the CRM from anywhere we show it.
 *
 * `HubSpotGateway::syncContact()` has always returned this id and `LeadServiceProvider` has
 * always logged it into the activity trail — but nowhere queryable. Recovering it meant reading
 * a log line, which is why nothing linked to HubSpot despite the value being in hand every time
 * a lead synced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('hubspot_contact_id', 50)->nullable()->index()->after('posthog_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn('hubspot_contact_id');
        });
    }
};
