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
