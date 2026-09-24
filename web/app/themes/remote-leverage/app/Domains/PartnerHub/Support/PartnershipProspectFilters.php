<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the Prospects screen is currently showing: a search, a status tab, the three answers and
 * where the row came from.
 *
 * One object because three things have to agree on it. The list applies it, the CSV export
 * applies the same one so "Export" hands over what is on screen, and the status tabs count
 * against it minus the status, so each tab's number is what clicking it would show.
 *
 * Every value is checked against the list it belongs to and silently dropped if it is not on
 * it — they arrive from a query string, and a filter nobody can select should mean "no filter",
 * not an empty table.
 */
final readonly class PartnershipProspectFilters
{
    /** The query-string key for each column filter. The keys are the column names. */
    public const COLUMNS = ['status', 'organization_type', 'monthly_revenue', 'businesses_reached', 'source'];

    private const SEARCH_MAX = 100;

    /**
     * @param  array<string, string>  $columns  column => value, only for the filters that are set
     */
    public function __construct(
        public string $search = '',
        public array $columns = [],
    ) {}

    /**
     * Read the filters from a query string. Unslash it first; WordPress adds slashes to $_GET.
     *
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        $search = is_scalar($query['s'] ?? null) ? (string) $query['s'] : '';
        $search = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($search))), 0, self::SEARCH_MAX);

        $columns = [];

        foreach (self::COLUMNS as $column) {
            $value = is_scalar($query[$column] ?? null) ? strtolower(trim((string) $query[$column])) : '';

            if ($value !== '' && in_array($value, self::allowed($column), true)) {
                $columns[$column] = $value;
            }
        }

        return new self($search, $columns);
    }

    /**
     * The values a column filter accepts.
     *
     * @return array<int, string>
     */
    public static function allowed(string $column): array
    {
        return match ($column) {
            'status' => PartnershipProspect::STATUSES,
            'source' => array_keys(PartnershipProspect::SOURCES),
            default => PartnershipProspectOptions::slugs($column),
        };
    }

    public function get(string $column): string
    {
        return $this->columns[$column] ?? '';
    }

    /**
     * Narrow a prospect query to these filters.
     *
     * The search is split into words and every word has to match one of name, email or company,
     * so "dana northwind" finds Dana at Northwind, and a full name finds its row even though the
     * first and last names are separate columns. Bound parameters throughout; a `%` typed into
     * the box only widens the match.
     *
     * @param  Builder<PartnershipProspect>  $query
     * @return Builder<PartnershipProspect>
     */
    public function apply(Builder $query): Builder
    {
        if ($this->search !== '') {
            foreach (explode(' ', $this->search) as $word) {
                $like = '%'.$word.'%';

                $query->where(static function (Builder $q) use ($like) {
                    $q->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('company', 'like', $like);
                });
            }
        }

        foreach ($this->columns as $column => $value) {
            $query->where($column, $value);
        }

        return $query;
    }

    /**
     * The same filters without one of them — what the status tabs count against.
     */
    public function without(string $column): self
    {
        $columns = $this->columns;
        unset($columns[$column]);

        return new self($this->search, $columns);
    }

    public function with(string $column, string $value): self
    {
        return new self($this->search, [...$this->columns, $column => $value]);
    }

    /**
     * Query-string arguments that reproduce these filters, for links and redirects.
     *
     * @return array<string, string>
     */
    public function toQueryArgs(): array
    {
        return array_filter(['s' => $this->search, ...$this->columns], static fn ($v) => $v !== '');
    }

    /**
     * Whether anything beyond the status tab is narrowing the list.
     */
    public function isNarrowed(): bool
    {
        return $this->search !== '' || $this->without('status')->columns !== [];
    }
}
