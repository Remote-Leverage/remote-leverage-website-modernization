<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the vendor-supplied click-id and campaign columns.
 *
 * These hold values we do not author and cannot bound: an ad platform decides how long its
 * click id is, and Meta's has grown well past what `fbclid varchar(150)` could take. Because
 * MySQL runs with `STRICT_TRANS_TABLES`, an overlong value is not truncated — it raises
 * SQLSTATE[22001] and kills the whole INSERT. That is not a lost tracking parameter, it is a
 * lost lead: `CaptureLeadAction` never creates the row, so `capturePartialLead()` records
 * nothing either and the visitor sees "An error occurred processing your consultation".
 *
 * Observed in production traffic on 2026-09-17: a 212-character `fbclid` from a Facebook ad
 * click. Every visitor arriving from paid social was being dropped, silently — the partial
 * capture swallows its exception into a `Log::warning`, and nothing else fires because the
 * lead does not exist to fire on.
 *
 * 512 is not a measured ceiling, because there is no published one. It is simply far enough
 * ahead of the 212 seen that the next growth spurt is absorbed, and `CaptureLeadAction` now
 * clamps to these widths as well so an even longer value degrades to a truncated click id
 * rather than a missing customer.
 *
 * `change()` restates `nullable()` on purpose: since Laravel 11 a modifier left out of a
 * change is dropped, and these columns must stay nullable. Indexes are untouched by `change()`
 * and survive; `fbclid` and `gclid` keep theirs, and utf8mb4 at 512 is 2048 bytes, inside
 * InnoDB's 3072-byte key limit.
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private const WIDENED = [
        'fbclid' => 512,
        'gclid' => 512,
        'fbc' => 512,
        'li_fat_id' => 255,
        'utm_source' => 255,
        'utm_medium' => 255,
        'utm_campaign' => 255,
        'utm_term' => 255,
        'utm_content' => 255,
        'utm_id' => 255,
        'oppref' => 255,
        'partner' => 255,
    ];

    /** @var array<string, int> */
    private const ORIGINAL = [
        'fbclid' => 150,
        'gclid' => 150,
        'fbc' => 255,
        'li_fat_id' => 150,
        'utm_source' => 100,
        'utm_medium' => 100,
        'utm_campaign' => 150,
        'utm_term' => 150,
        'utm_content' => 150,
        'utm_id' => 150,
        'oppref' => 150,
        'partner' => 150,
    ];

    public function up(): void
    {
        Schema::table('rl_leads', function (Blueprint $table) {
            foreach (self::WIDENED as $column => $length) {
                if (Schema::hasColumn('rl_leads', $column)) {
                    $table->string($column, $length)->nullable()->change();
                }
            }
        });
    }

    /**
     * Narrowing again would fail on any row already holding a longer value, so the data is
     * trimmed to fit first. The reverse of this migration is lossy by nature.
     */
    public function down(): void
    {
        foreach (self::ORIGINAL as $column => $length) {
            if (Schema::hasColumn('rl_leads', $column)) {
                DB::table('rl_leads')
                    ->whereNotNull($column)
                    ->update([$column => DB::raw("LEFT(`{$column}`, {$length})")]);
            }
        }

        Schema::table('rl_leads', function (Blueprint $table) {
            foreach (self::ORIGINAL as $column => $length) {
                if (Schema::hasColumn('rl_leads', $column)) {
                    $table->string($column, $length)->nullable()->change();
                }
            }
        });
    }
};
