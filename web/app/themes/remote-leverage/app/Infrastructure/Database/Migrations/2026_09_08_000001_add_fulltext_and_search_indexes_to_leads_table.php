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
            $table->index('phone');
            $table->index('company');
            $table->index('created_at');

            if (DB::getDriverName() === 'mysql') {
                $table->fullText(['name', 'email', 'company', 'phone'], 'rl_leads_fulltext_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['company']);
            $table->dropIndex(['created_at']);

            if (DB::getDriverName() === 'mysql') {
                $table->dropFullText('rl_leads_fulltext_idx');
            }
        });
    }
};
