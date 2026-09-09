<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            if (! Schema::hasColumn('rl_leads', 'booking_retry_count')) {
                $table->unsignedTinyInteger('booking_retry_count')->default(0)->after('status');
            }

            if (! Schema::hasColumn('rl_leads', 'booking_next_retry_at')) {
                $table->timestamp('booking_next_retry_at')->nullable()->after('booking_retry_count');
            }
        });

        // Extend the status enum with a terminal 'booking_failed' value via a raw
        // statement rather than Blueprint::change(), since the column also carries
        // an index and a NOT NULL DEFAULT that a Blueprint-driven rewrite of an enum
        // column can be error-prone to preserve correctly. Raw statements bypass
        // the schema builder's automatic table-prefixing, so the prefix is applied
        // explicitly here.
        $table = DB::getTablePrefix().'rl_leads';
        DB::statement("ALTER TABLE {$table} MODIFY status ENUM('captured','booking_pending','booked','abandoned','canceled','booking_failed') NOT NULL DEFAULT 'captured'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = DB::getTablePrefix().'rl_leads';
        DB::statement("ALTER TABLE {$table} MODIFY status ENUM('captured','booking_pending','booked','abandoned','canceled') NOT NULL DEFAULT 'captured'");

        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropColumn(['booking_retry_count', 'booking_next_retry_at']);
        });
    }
};
