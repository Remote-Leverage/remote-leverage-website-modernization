<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give a referral a real foreign key to the lead it refers to.
 *
 * Until now the only link between `rl_referrals` and `rl_leads` was string equality on
 * `lead_email`, which fails in two ways that both lose a referrer their commission:
 *
 *  - A phone-only direct submission stores `lead_email = ''` on the referral while the Lead
 *    gets a synthesised `lead_<code>_<time>@remoteleverage.internal` address. Neither matches,
 *    so the booking listener's updateOrCreate inserted a **second** referral row instead of
 *    qualifying the first.
 *  - A prospect who books under a different address than they were referred under is never
 *    attributed at all.
 *
 * The id is also what makes a referrer-facing timeline possible: `rl_lead_activity_logs` is
 * keyed on `lead_id`, so without this column there is no path from a referrer to their lead's
 * history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('lead_id')->nullable()->index()->after('referrer_id');
        });

        $this->backfillFromNotes();
        $this->backfillFromEmail();
    }

    /**
     * Direct portal submissions recorded the lead id in prose — `(lead #12, uuid ...)` — because
     * there was no column to put it in. That text is the most reliable signal available, so it
     * is read first and wins over the email match below.
     */
    protected function backfillFromNotes(): void
    {
        DB::table('rl_referrals')
            ->whereNull('lead_id')
            ->where('notes', 'like', '%(lead #%')
            ->orderBy('id')
            ->each(function (object $referral): void {
                if (preg_match('/\(lead #(\d+)/', (string) $referral->notes, $matches) !== 1) {
                    return;
                }

                $leadId = (int) $matches[1];

                if (! DB::table('rl_leads')->where('id', $leadId)->exists()) {
                    return;
                }

                DB::table('rl_referrals')->where('id', $referral->id)->update(['lead_id' => $leadId]);
            });
    }

    /**
     * Everything else falls back to the email match this table has always used implicitly.
     * Deliberately the *earliest* matching lead: a referral is created at the moment of
     * referral, so the first lead carrying that address is the one it describes. Blank and
     * synthesised internal addresses are skipped — they are exactly the rows the email match
     * was never able to resolve, and guessing at them would attribute the wrong person.
     */
    protected function backfillFromEmail(): void
    {
        DB::table('rl_referrals')
            ->whereNull('lead_id')
            ->where('lead_email', '<>', '')
            ->where('lead_email', 'not like', '%@remoteleverage.internal')
            ->orderBy('id')
            ->each(function (object $referral): void {
                $leadId = DB::table('rl_leads')
                    ->whereRaw('LOWER(email) = ?', [strtolower((string) $referral->lead_email)])
                    ->orderBy('id')
                    ->value('id');

                if ($leadId === null) {
                    return;
                }

                DB::table('rl_referrals')->where('id', $referral->id)->update(['lead_id' => $leadId]);
            });
    }

    public function down(): void
    {
        Schema::table('rl_referrals', function (Blueprint $table) {
            $table->dropIndex(['lead_id']);
            $table->dropColumn('lead_id');
        });
    }
};
