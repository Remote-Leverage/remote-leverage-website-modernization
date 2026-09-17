<?php

declare(strict_types=1);

namespace App\Domains\Lead\Export;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadSearch;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everything the export modal can ask for, sanitised once.
 *
 * It also owns the query, because the row count shown on the slider and the rows actually
 * written must come from the same filters. Built separately they drift the moment a filter is
 * added, and the symptom is a progress bar that stops at 94% — or never stops — rather than an
 * error anyone can act on.
 *
 * The options survive in wp_options between batches (see {@see LeadExportJobStore}), so this is
 * also the serialisation format: `toArray()` and `fromArray()` are each other's inverse and
 * nothing else may be stashed on a running job.
 */
readonly class LeadExportOptions
{
    /** Rows above which the modal's slider stops offering exact numbers and means "all". */
    public const NO_LIMIT = 0;

    /**
     * @param  array<int, string>  $groups  Column groups to write, beyond the mandatory ones.
     * @param  int  $limit  Most recent N leads, or NO_LIMIT for every match.
     */
    public function __construct(
        public array $groups = [],
        public string $status = '',
        public string $sourceType = '',
        public string $from = '',
        public string $to = '',
        public bool $includeDeleted = false,
        public int $limit = self::NO_LIMIT,
        public string $search = '',
    ) {}

    /**
     * Build from raw request input, discarding anything not recognised.
     *
     * Every field is filtered against a known set rather than trusted: this runs behind
     * `manage_options`, but a status of `'; DROP` reaching a query builder is not something to
     * leave to the next reader's care.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input): self
    {
        $groups = $input['groups'] ?? [];
        $groups = is_array($groups) ? $groups : explode(',', (string) $groups);
        $groups = array_values(array_intersect(
            array_map(static fn ($g) => (string) $g, $groups),
            LeadExportColumns::groupSlugs(),
        ));

        return new self(
            groups: $groups,
            status: self::oneOf((string) ($input['status'] ?? ''), [
                'captured', 'qualified', 'booked', 'partial', 'abandoned', 'canceled',
            ]),
            sourceType: self::slug((string) ($input['source_type'] ?? '')),
            from: self::date((string) ($input['from'] ?? '')),
            to: self::date((string) ($input['to'] ?? '')),
            includeDeleted: ! empty($input['include_deleted']) && $input['include_deleted'] !== 'false',
            limit: max(0, (int) ($input['limit'] ?? 0)),
            search: trim((string) ($input['search'] ?? '')),
        );
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return self::fromRequest($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'groups' => $this->groups,
            'status' => $this->status,
            'source_type' => $this->sourceType,
            'from' => $this->from,
            'to' => $this->to,
            'include_deleted' => $this->includeDeleted,
            'limit' => $this->limit,
            'search' => $this->search,
        ];
    }

    /**
     * The leads this export covers, newest first.
     *
     * Ordered by id rather than created_at: the batches walk it with a cursor, and two leads
     * captured in the same second would otherwise be able to swap places between batches —
     * exporting one twice and the other not at all.
     */
    public function query(): Builder
    {
        $query = Lead::query()->orderByDesc('id');

        if ($this->includeDeleted) {
            $query->withTrashed();
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->sourceType !== '') {
            $query->where('source_type', $this->sourceType);
        }

        if ($this->from !== '') {
            $query->where('created_at', '>=', $this->from.' 00:00:00');
        }

        if ($this->to !== '') {
            $query->where('created_at', '<=', $this->to.' 23:59:59');
        }

        if ($this->search !== '') {
            LeadSearch::apply($query, $this->search);
        }

        return $query;
    }

    /**
     * How many rows this export will write.
     *
     * The limit is applied here too, so the progress bar counts down to what is actually coming
     * rather than to the size of the whole table. A ceiling confines the count to the snapshot
     * the export is walking, so the total cannot include a lead captured after it started.
     */
    public function total(int $ceiling = 0): int
    {
        $query = $this->query();

        if ($ceiling > 0) {
            $query->where('id', '<=', $ceiling);
        }

        $matching = $query->count();

        return $this->limit === self::NO_LIMIT
            ? $matching
            : min($matching, $this->limit);
    }

    /** The groups written to the file, mandatory ones included. */
    public function effectiveGroups(): array
    {
        return array_values(array_unique(array_merge(
            LeadExportColumns::mandatoryGroups(),
            $this->groups,
        )));
    }

    /** A human sentence describing what is being exported, for the job list and the modal. */
    public function describe(): string
    {
        $parts = [];

        $parts[] = $this->limit === self::NO_LIMIT
            ? 'All matching leads'
            : 'Newest '.number_format($this->limit).' leads';

        if ($this->status !== '') {
            $parts[] = 'status '.$this->status;
        }

        if ($this->sourceType !== '') {
            $parts[] = 'source '.$this->sourceType;
        }

        if ($this->from !== '' || $this->to !== '') {
            $parts[] = trim(($this->from !== '' ? 'from '.$this->from.' ' : '').($this->to !== '' ? 'to '.$this->to : ''));
        }

        if ($this->search !== '') {
            $parts[] = 'matching "'.$this->search.'"';
        }

        if ($this->includeDeleted) {
            $parts[] = 'including deleted';
        }

        return implode(', ', $parts);
    }

    /** @param array<int, string> $allowed */
    private static function oneOf(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private static function slug(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($value)));
    }

    /** A Y-m-d date, or an empty string. Anything else is not a date range, it is a typo. */
    private static function date(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }
}
