<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A device identifier we own, so returning visits can be recognised.
 *
 * Neither `uuid` nor `session_id` can do this — both are minted fresh per row and per component
 * mount. The alternatives were a vendor's cookie: PostHog's distinct id disappears whenever
 * PostHog is blocked, which is disproportionately the traffic worth recognising, and HandL's
 * belongs to a plugin that does not survive the cutover.
 *
 * Recorded as a **weak** identifier: it is evidence, and never merges two profiles on its own.
 * A shared browser, an office machine or a cleared cookie would otherwise fuse unrelated people,
 * and because a ban lives on the profile, a false merge is a false ban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('device_id', 64)->nullable()->index()->after('posthog_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
