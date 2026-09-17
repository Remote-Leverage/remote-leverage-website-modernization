<?php

declare(strict_types=1);

namespace App\Domains\Lead\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * How a search term is turned into a lead query.
 *
 * Extracted from the admin dashboard so the CSV export resolves the toolbar's search term the
 * same way the screen does. Two implementations of "which leads match this text" means an export
 * started from a filtered list can hand back a different set of rows than the list showed, and
 * nothing about the file would reveal it.
 *
 * The routing is a performance decision, not a feature: each branch below picks the index that
 * can actually answer the shape of term that was typed.
 */
class LeadSearch
{
    /**
     * Narrow a lead query to a search term. An empty term is a no-op, not an empty result.
     *
     * @param  Builder  $query
     */
    public static function apply($query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        // 1. Email Lookup: user typed an email or prefix (contains '@')
        // Uses the indexed `wp_rl_leads_email_index` B-tree index
        if (str_contains($search, '@')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', $search)
                    ->orWhere('email', 'LIKE', $search.'%');
            });

            return;
        }

        // 2. Phone Lookup: search contains only digits, +, -, (, ) and spaces
        // Uses `wp_rl_leads_phone_index` B-tree index
        if (preg_match('/^[+0-9\s\-()]{4,}$/', $search)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $search);
            $query->where(function ($q) use ($search, $cleanPhone) {
                $q->where('phone', $search)
                    ->orWhere('phone', 'LIKE', '%'.$cleanPhone.'%');
            });

            return;
        }

        // 3. MySQL FULLTEXT Inverted Index Search
        // Sub-millisecond inverted-index lookup across (name, email, company, phone)
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'mysql' && mb_strlen($search) >= 3) {
            // Strip MySQL boolean operators to sanitize input
            $sanitized = preg_replace('/[+\-><()~*\"@]+/', ' ', $search);
            $tokens = array_filter(explode(' ', trim((string) $sanitized)));

            if (! empty($tokens)) {
                // Suffix wildcard for each word: e.g. "+acme* +john*"
                $booleanExpr = implode(' ', array_map(fn ($token) => '+'.$token.'*', $tokens));

                $query->where(function ($q) use ($booleanExpr, $search) {
                    $q->whereRaw(
                        'MATCH(name, email, company, phone) AGAINST(? IN BOOLEAN MODE)',
                        [$booleanExpr],
                    )
                        ->orWhere('utm_campaign', 'LIKE', $search.'%')
                        ->orWhere('referral_code', 'LIKE', $search.'%');
                });

                return;
            }
        }

        // 4. Fallback for SQLite / short terms: standard substring query
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('email', 'LIKE', "%{$search}%")
                ->orWhere('phone', 'LIKE', "%{$search}%")
                ->orWhere('company', 'LIKE', "%{$search}%")
                ->orWhere('utm_campaign', 'LIKE', "%{$search}%")
                ->orWhere('referral_code', 'LIKE', "%{$search}%");
        });
    }
}
