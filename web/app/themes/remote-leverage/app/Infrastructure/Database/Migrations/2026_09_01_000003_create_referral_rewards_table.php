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
        Schema::create('rl_referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->unsignedBigInteger('referral_id')->default(0)->index();
            $table->string('reward_type', 50)->default('cash');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 20)->default('USD');
            $table->string('status', 50)->default('due')->index();
            $table->string('description', 255)->default('');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_referral_rewards');
    }
};
