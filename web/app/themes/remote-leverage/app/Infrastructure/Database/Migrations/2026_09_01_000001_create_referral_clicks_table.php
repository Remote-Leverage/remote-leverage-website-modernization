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
        Schema::create('rl_referral_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->string('referrer_slug', 100)->index();
            $table->text('landing_page')->nullable();
            $table->string('ip_address', 45)->default('')->index();
            $table->text('user_agent')->nullable();
            $table->text('referer_url')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_referral_clicks');
    }
};
