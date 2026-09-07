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
        Schema::create('rl_leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('phone_country', 5)->nullable();
            $table->string('company')->nullable();
            $table->string('role_needed')->nullable();
            $table->string('weekly_hours')->nullable();
            $table->string('start_date')->nullable();
            $table->text('notes')->nullable();

            // Attribution stamped by AttributionEngine
            $table->enum('source_type', ['ad', 'organic', 'referral_hub', 'partnership'])->default('organic')->index();
            $table->string('source_id')->nullable()->index();

            // Lead lifecycle status
            $table->enum('status', ['captured', 'booking_pending', 'booked', 'abandoned', 'canceled'])->default('captured')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_leads');
    }
};
