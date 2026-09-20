<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the lead's Slack card currently says.
 *
 * A visitor who reads the revenue-band warning, goes back and picks a different band runs step
 * one a second time. That is one lead amending an answer, not two leads — but the only way to
 * *say* what they amended is to know what the channel was told the first time, and the lead row
 * itself no longer knows: the second capture overwrites it.
 *
 * So the announced values are kept beside the `ts` that identifies the message carrying them.
 * The two are written together and are meaningless apart — a snapshot with no message to
 * compare against is a message nobody can amend.
 *
 * Deliberately small: only the handful of fields the card renders. It is not an audit trail —
 * `rl_lead_activity_logs` is — and widening it to every column would make a diff of it noise
 * rather than news.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->json('slack_announced')->nullable()->after('slack_channel_id');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn('slack_announced');
        });
    }
};
