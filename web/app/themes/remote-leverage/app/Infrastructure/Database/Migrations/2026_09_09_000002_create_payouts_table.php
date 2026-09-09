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
        Schema::create('rl_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id')->index();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->string('currency', 20)->default('USD');
            $table->string('stripe_transfer_id', 100)->nullable();
            $table->string('status', 50)->default('pending')->index();
            $table->text('referral_ids')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_payouts');
    }
};
