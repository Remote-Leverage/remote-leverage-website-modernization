<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * People the booking form turned away at the email check.
     *
     * Deliberately not `rl_leads` and deliberately not `rl_lead_activity_logs`: these visitors
     * never became a lead, so there is no `lead_id` to hang an activity row off, and writing
     * them into `rl_leads` would put addresses we refused into every export, KPI and CRM sync.
     *
     * This is the only record that a blocked attempt happened at all. Before it, a rejection
     * added a form error and vanished, which is why a run of "the booking flow is broken"
     * reports could not be checked against anything.
     */
    public function up(): void
    {
        Schema::create('rl_bounced_leads', function (Blueprint $table) {
            $table->id();

            // Step-one contact details, kept so a real buyer who was wrongly refused can be
            // called back rather than merely counted.
            $table->string('name')->nullable();
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('company')->nullable();

            // `reason` is EmailValidationService's verdict reason (zerobounce_invalid,
            // blacklisted_email, blocked_domain, domain_not_allowed, malformed); `checked_by`
            // is which of the three gates produced it, so one filter separates "ZeroBounce
            // called it undeliverable" from "our own domain list refused it".
            $table->string('reason', 64)->index();
            $table->string('checked_by', 32)->index();

            $table->string('ip_address', 45)->nullable();
            $table->string('posthog_session_id')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('referral_code')->nullable();

            /*
             * Click identifiers, as columns rather than JSON for the same reason `msclkid` was
             * promoted on `rl_leads`: "which campaign is buying leads we then refuse" is a
             * query, and a value inside a JSON blob cannot be queried the same way on MySQL and
             * on the SQLite the tests run.
             *
             * Widths match `rl_leads` exactly (512 for the click ids after the 2026-09-17
             * widening, 150 for `msclkid`) so a value that fits one table fits the other.
             *
             * `fbc_synthetic` records whether the `fbc` is Meta's own cookie or a stand-in this
             * codebase built from a bare `fbclid`: an unmarked synthetic value read back later
             * is indistinguishable from a real one, and the Conversions API treats them very
             * differently. `_fbp` stays in `context`, exactly as it has no column on `rl_leads`.
             */
            $table->string('gclid', 512)->nullable();
            $table->string('fbclid', 512)->nullable();
            $table->string('msclkid', 150)->nullable();
            $table->string('fbc', 512)->nullable();
            $table->boolean('fbc_synthetic')->nullable();
            $table->text('landing_url')->nullable();

            $table->json('context')->nullable();

            /*
             * Somebody who is blocked retypes the address and is blocked again. Counting each
             * keystroke as a separate turned-away lead would overstate the problem this table
             * exists to measure, so repeats inside the collapse window bump `attempts` instead
             * of inserting. `created_at` is therefore first seen, not last.
             */
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('last_seen_at')->useCurrent();

            $table->index(['email', 'reason']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rl_bounced_leads');
    }
};
