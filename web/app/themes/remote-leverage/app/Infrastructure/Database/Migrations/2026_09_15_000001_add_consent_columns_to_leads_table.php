<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the SMS/phone/email consent the booking wizard collects.
     *
     * The checkbox existed but was bound to nothing, so no lead captured before
     * this migration has a consent record — `consent_at` is null for all of them,
     * which is the honest representation. Do not backfill it to true: the box
     * shipped pre-ticked, so a tick carried no information.
     *
     * `consent_at` doubles as the flag and the proof. A timestamp answers "did
     * they agree, and when", which a bare boolean cannot.
     */
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('rl_leads', 'consent_at')) {
                $table->timestamp('consent_at')->nullable()->after('referrer_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            if (Schema::hasColumn('rl_leads', 'consent_at')) {
                $table->dropColumn('consent_at');
            }
        });
    }
};
