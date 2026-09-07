<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->string('utm_source', 100)->nullable()->index()->after('notes');
            $table->string('utm_medium', 100)->nullable()->after('utm_source');
            $table->string('utm_campaign', 150)->nullable()->index()->after('utm_medium');
            $table->string('utm_term', 150)->nullable()->after('utm_campaign');
            $table->string('utm_content', 150)->nullable()->after('utm_term');
            $table->string('gclid', 150)->nullable()->index()->after('utm_content');
            $table->string('fbclid', 150)->nullable()->index()->after('gclid');
            $table->string('referral_code', 100)->nullable()->index()->after('fbclid');
            $table->text('landing_url')->nullable()->after('referral_code');
            $table->text('referrer_url')->nullable()->after('landing_url');
            $table->string('session_id', 100)->nullable()->index()->after('referrer_url');
            $table->string('monthly_revenue', 100)->nullable()->after('weekly_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'gclid',
                'fbclid',
                'referral_code',
                'landing_url',
                'referrer_url',
                'session_id',
                'monthly_revenue',
            ]);
        });
    }
};
