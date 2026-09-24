<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Companies asking to become a partner, from the `/become-a-partner/` form.
     *
     * Deliberately not `rl_leads` and not `rl_referrers`. A prospect is an agency, a SaaS vendor
     * or a community that may send us clients one day — not a buyer, and not yet anyone who has
     * referred anybody. Writing them into `rl_leads` would put them in the sales Slack stream,
     * HubSpot, Meta CAPI and every lead KPI; writing them into `rl_referrers` would mint a
     * referral code and a portal account for a conversation that has not happened yet.
     */
    public function up(): void
    {
        Schema::create('rl_partnership_prospects', function (Blueprint $table) {
            $table->id();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 191)->index();
            $table->string('company', 191);
            $table->string('role', 150);

            // Slugs from PartnershipProspectOptions, never labels, so an answer can be reworded
            // without rewriting history. See that class.
            $table->string('organization_type', 50)->index();
            $table->string('monthly_revenue', 50);
            $table->string('businesses_reached', 50);

            $table->text('message')->nullable();

            // new | contacted | qualified | declined | converted — see PartnershipProspect::STATUSES.
            $table->string('status', 32)->default('new')->index();

            /*
             * The partnership call, when one was booked from the form. Reported by the Calendly
             * embed in the visitor's browser, so it is the browser's word rather than Calendly's
             * — the URIs are kept so it can be checked against the API if it ever matters.
             */
            $table->timestamp('booked_at')->nullable();
            $table->string('calendly_event_uri', 255)->nullable();
            $table->string('calendly_invitee_uri', 255)->nullable();

            /*
             * Where they came from. Widths match `rl_leads` so a value that fits one fits the
             * other. The click ids and the rest of AttributionCollector's named set go in
             * `context` rather than columns: nothing filters prospects by campaign, and a
             * low-volume table does not need the lead table's index budget.
             */
            $table->text('landing_url')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rl_partnership_prospects');
    }
};
