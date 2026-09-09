<?php

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
        Schema::table('rl_referrals', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_id')->nullable()->index()->after('id');
            $table->unsignedBigInteger('referrer_user_id')->nullable()->change();
        });

        Schema::table('rl_referral_clicks', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_id')->nullable()->index()->after('id');
            $table->unsignedBigInteger('referrer_user_id')->nullable()->change();
        });

        Schema::table('rl_referral_rewards', function (Blueprint $table) {
            $table->unsignedBigInteger('referrer_id')->nullable()->index()->after('id');
            $table->unsignedBigInteger('referrer_user_id')->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rl_referrals', function (Blueprint $table) {
            $table->dropColumn('referrer_id');
        });

        Schema::table('rl_referral_clicks', function (Blueprint $table) {
            $table->dropColumn('referrer_id');
        });

        Schema::table('rl_referral_rewards', function (Blueprint $table) {
            $table->dropColumn('referrer_id');
        });
    }
};
