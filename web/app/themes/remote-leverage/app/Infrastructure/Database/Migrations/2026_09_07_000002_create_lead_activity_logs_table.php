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
        Schema::create('rl_lead_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('rl_leads')->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('actor_domain', 64)->index();
            $table->enum('stage', ['dispatch', 'consumption'])->default('consumption')->index();
            $table->enum('outcome', ['succeeded', 'failed', 'skipped'])->default('succeeded')->index();
            $table->string('description', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_lead_activity_logs');
    }
};
