<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The full request and response of every outbound integration call.
 *
 * `rl_lead_activity_logs` records what we *decided* to do — "dispatched the booking webhook",
 * "synced to HubSpot" — and its payload is written by hand at each call site, before the call
 * returns. That is the wrong shape for diagnosis: when HubSpot rejects a contact, the summary
 * still says the sync was attempted, and the property it objected to is nowhere. This table
 * records what actually crossed the wire, in both directions.
 *
 * Separate table rather than a fatter payload on the activity log, for three reasons: the rows
 * are an order of magnitude larger, they are pruned on their own schedule because they carry
 * full lead PII, and a call with no lead attached (fetching availability, refreshing a token)
 * has nowhere to live on a log keyed by `lead_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rl_integration_calls', function (Blueprint $table) {
            $table->id();

            /*
             * Nullable: plenty of integration traffic is not attributable to one lead.
             * Cascades on delete so erasing a lead erases the copies of their data that
             * these request bodies necessarily contain.
             */
            $table->foreignId('lead_id')->nullable()->constrained('rl_leads')->cascadeOnDelete();

            $table->string('integration', 32)->index();
            $table->string('operation', 160)->nullable();
            $table->string('method', 10);
            $table->text('url');

            /*
             * Which account the call authenticated as — the Calendly pool's token belongs to a
             * person, and a rate limit is only actionable once you know which one. Indexed
             * because "show me everything that went out on this account" is the question asked
             * of it.
             */
            $table->string('credential_label', 190)->nullable()->index();

            $table->json('request_headers')->nullable();
            $table->longText('request_body')->nullable();

            $table->unsignedSmallInteger('status_code')->nullable()->index();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();

            // `error` is a transport failure (DNS, timeout, TLS) where no response exists at all,
            // which is a different diagnosis from `failed` (we got an answer, it was a 4xx/5xx).
            $table->enum('outcome', ['succeeded', 'failed', 'error'])->default('succeeded')->index();
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // The two ways this table is actually read: one lead's history, and one
            // integration's recent traffic.
            $table->index(['integration', 'created_at']);
            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rl_integration_calls');
    }
};
