<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Domains\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filter set shared by query-leads and lead-stats.
 *
 * Kept in one place so the row listing and the aggregate can never disagree
 * about what "leads from the Athena page last month" means — a discrepancy
 * between the two would be read as a data problem rather than a query one.
 */
final class LeadQuery
{
    /**
     * Dimensions a caller may group or filter by. A whitelist because these
     * reach a SQL GROUP BY / ORDER BY, where a column name cannot be bound as
     * a parameter.
     */
    public const DIMENSIONS = [
        'landing_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'referral_code',
        'source_type',
        'status',
    ];

    /**
     * @param  array<string, mixed>  $input
     */
    public function build(array $input): Builder
    {
        $query = Lead::query();

        if (! empty($input['since'])) {
            $query->where('created_at', '>=', $input['since']);
        }

        if (! empty($input['until'])) {
            $query->where('created_at', '<=', $input['until']);
        }

        if (! empty($input['status'])) {
            $query->where('status', $input['status']);
        }

        if (! empty($input['source_type'])) {
            $query->where('source_type', $input['source_type']);
        }

        // Matched as a substring so a caller can pass a page path
        // ("/compare-athena") without having to reproduce the querystring
        // that was on the URL when the lead was captured.
        if (! empty($input['landing_url_contains'])) {
            $query->where('landing_url', 'like', '%'.$this->escapeLike($input['landing_url_contains']).'%');
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $column) {
            if (! empty($input[$column])) {
                $query->where($column, $input[$column]);
            }
        }

        return $query;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
