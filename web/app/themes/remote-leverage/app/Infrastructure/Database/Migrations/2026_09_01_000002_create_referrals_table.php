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
        Schema::create('rl_referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_user_id')->index();
            $table->string('lead_name', 191);
            $table->string('lead_email', 191)->default('')->index();
            $table->string('lead_phone', 50)->default('');
            $table->text('landing_page')->nullable();
            $table->string('source', 50)->default('manual_submission');
            $table->string('status', 50)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_referrals');
    }
};
