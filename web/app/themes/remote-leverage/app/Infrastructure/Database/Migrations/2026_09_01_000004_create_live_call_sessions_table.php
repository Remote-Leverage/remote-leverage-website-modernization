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
        Schema::create('rl_live_call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 100)->unique();
            $table->string('calendly_event_uri', 255)->default('');
            $table->string('calendly_invitee_uri', 255)->default('');
            $table->text('meeting_url')->nullable();
            $table->string('status', 50)->default('initiated')->index();
            $table->string('visitor_email', 191)->default('')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rl_live_call_sessions');
    }
};
