<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where this lead's Slack alert lives, so everything that happens to them afterwards can reply
 * to it instead of starting a new message.
 *
 * Without this, one person generates a scatter of unrelated cards in the channel — the partial,
 * the booking, whatever a salesperson did about it — and reconstructing the order means reading
 * timestamps. With it the channel holds one message per lead and the whole story hangs off it.
 *
 * The channel id is stored alongside the timestamp rather than re-read from config at reply
 * time: `SLACK_CHANNEL` can change, and a `thread_ts` is only meaningful in the channel that
 * produced it. Replying into the new channel with the old channel's timestamp does not fail
 * loudly — it posts a top-level message that merely looks wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            // Slack's `ts` is a string ("1726502400.001200"); it is an identifier, not a number,
            // and float-casting it loses the microsecond suffix that makes it unique.
            $table->string('slack_message_ts', 32)->nullable()->after('hubspot_contact_id');
            $table->string('slack_channel_id', 32)->nullable()->after('slack_message_ts');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn(['slack_message_ts', 'slack_channel_id']);
        });
    }
};
