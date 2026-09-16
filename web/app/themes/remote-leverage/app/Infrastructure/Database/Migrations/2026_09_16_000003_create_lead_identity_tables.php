<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The identity graph — one "passport" per person, across every identifier they have used.
 *
 * `rl_leads.uuid` cannot do this job: it is `Str::uuid()` minted per row and declared unique, so
 * a returning visitor gets a brand new one every time. It is a surrogate key, not a person.
 * Consolidation needs identifiers that persist across visits, held separately from the leads
 * that observed them.
 *
 * Two tables, because the graph outlives any individual lead:
 *
 *  - `rl_lead_profiles` is the person. A ban lives here, which is what makes it propagate to
 *    every identifier at once rather than needing to be applied to each.
 *  - `rl_lead_identifiers` is the edge list: one row per (type, value), unique, pointing at a
 *    profile. Seeing a known phone with a new email attaches the new email to the same profile,
 *    with no special case for that scenario or any other.
 *
 * **Identifier values are stored hashed, never in the clear.** Lookups still work — the incoming
 * value is hashed the same way — but a ban survives a data-deletion request without retaining
 * personal data. Given the 30-day retention floor in `LeadSettingsService` and the erasure
 * obligations behind it, a blocklist that must be deleted along with the lead is not a
 *
 * blocklist. `value_preview` keeps just enough (`j***@example.com`) for a human to recognise a
 * row in the admin without the table becoming a re-identification source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rl_lead_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            /*
             * `blocked` is a shadow ban: the form still succeeds and the lead is still stored,
             * but Slack, HubSpot and the booking are suppressed. Telling someone they are
             * blocked teaches them which identifier to change — the same reasoning that keeps
             * the email validator's rejection message generic.
             */
            $table->enum('status', ['active', 'blocked'])->default('active')->index();
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_by', 100)->nullable();
            $table->text('block_reason')->nullable();

            /*
             * Merged profiles are kept, not deleted, and point at the winner.
             *
             * A merge is a judgement that two records are one person, and judgements are
             * sometimes wrong — a mistaken merge is a mistaken ban. Keeping the losing row and
             * the pointer makes the decision auditable and reversible; deleting it would make
             * an over-merge permanent and invisible.
             */
            $table->foreignId('merged_into_id')->nullable()->index();
            $table->timestamp('merged_at')->nullable();

            $table->unsignedInteger('lead_count')->default(0);
            $table->timestamps();
        });

        Schema::create('rl_lead_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->index();

            $table->enum('type', ['email', 'phone', 'device'])->index();

            /*
             * sha256 of the normalised value with an application salt. Unique per type, which
             * is what makes "have we seen this before" a single indexed lookup and what stops
             * two concurrent submissions creating two profiles for one person — the second
             * insert collides and is resolved rather than duplicated.
             */
            $table->char('value_hash', 64);

            /** Masked remnant for admin display only — never enough to re-identify from. */
            $table->string('value_preview', 60)->nullable();

            /*
             * Strong identifiers (email, phone) may merge two profiles automatically. Weak ones
             * (device) are recorded as evidence and surfaced for a human, because a shared
             * browser, a family machine or a reused cookie would otherwise merge unrelated
             * people — and here a false merge is a false ban.
             */
            $table->enum('strength', ['strong', 'weak'])->default('strong')->index();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('seen_count')->default(1);
            $table->timestamps();

            $table->unique(['type', 'value_hash']);
        });

        Schema::table('rl_leads', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->index()->after('uuid');

            /*
             * Denormalised onto the lead so the listeners that must suppress work — Slack,
             * HubSpot, booking — can check one column instead of joining to the profile on
             * every event. Reconciled whenever the profile's status changes.
             */
            $table->boolean('is_blocked')->default(false)->index()->after('profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn(['profile_id', 'is_blocked']);
        });

        Schema::dropIfExists('rl_lead_identifiers');
        Schema::dropIfExists('rl_lead_profiles');
    }
};
