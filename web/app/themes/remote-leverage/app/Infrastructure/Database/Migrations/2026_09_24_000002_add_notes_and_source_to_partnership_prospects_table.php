<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the partnerships team adds to a prospect by hand: their notes, and whether the row came
 * from the form at all.
 *
 * `notes` is one text column on the row, the way `rl_referrals.notes` is, rather than a table of
 * timestamped entries. A prospect is a handful of conversations worked by a small team, not a
 * lead with a pipeline of machines writing to it; `rl_lead_activity_logs` exists because a lead's
 * history is written by HubSpot, Calendly and the queue, and nothing writes a prospect's but a
 * person on the Prospects screen. If that stops being true, this is the column to migrate out.
 *
 * `source` separates `/become-a-partner/` submissions (`form`) from prospects entered on the
 * admin screen (`manual`) — someone who emailed the team or was met at an event. A column rather
 * than a key inside `context`, because it is shown on every row, filtered on, exported, and read
 * by the overview's source breakdown, and because a manual row has no landing page or UTMs to
 * explain its absence from the attribution columns. Existing rows are all form submissions, so
 * the default is also the backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('rl_partnership_prospects', 'source')) {
            return;
        }

        Schema::table('rl_partnership_prospects', function (Blueprint $table) {
            $table->string('source', 20)->default('form')->index()->after('status');
            $table->text('notes')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rl_partnership_prospects', 'source')) {
            return;
        }

        Schema::table('rl_partnership_prospects', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['source', 'notes']);
        });
    }
};
