<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Support;

use App\Domains\PartnerHub\Models\PartnershipProspect;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The figures behind Partners Hub → Partnership Overview.
 *
 * Cached for three minutes under one key, the way ReferralAdminDashboard::getAnalyticsMetrics()
 * caches the referral figures, and dropped on every write to a prospect — see
 * PartnershipProspect::booted(). The cached value is plain arrays and scalars, never models, so
 * whichever cache store is configured can hold it and a stale entry cannot lazy-load anything.
 *
 * Counted in SQL throughout. The two tables of sources are then folded in PHP — landing pages
 * by path, because the stored value is the full URL with its query string and every campaign
 * tag would otherwise be its own row, and manual entries into one row whatever they carry —
 * but those loops run over the grouped rows, not over prospects.
 */
final class PartnershipProspectMetrics
{
    public const CACHE_KEY = 'rl_partnership_prospect_metrics';

    public const CACHE_SECONDS = 180;

    /** How many rows the source and landing-page tables show. */
    public const TOP = 6;

    public const RECENT = 8;

    /** The Overview's label for a form submission with no `utm_source`. */
    public const UNTAGGED = 'Untagged';

    /**
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, static fn () => self::compute());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     total: int, last7: int, last30: int, awaitingContact: int, booked: int, bookedRate: float,
     *     converted: int, conversionRate: float, manual: int,
     *     funnel: array<string, array{count: int, share: float}>,
     *     breakdowns: array<string, array<int, array{slug: string, label: string, count: int, converted: int, share: float}>>,
     *     topSources: array<int, array{label: string, count: int, converted: int}>,
     *     topLandingPages: array<int, array{label: string, count: int, converted: int}>,
     *     recent: array<int, array{id: int, name: string, company: string, status: string, organization: string, source: string, booked: bool, created_at: ?int}>,
     *     computedAt: int,
     * }
     */
    public static function compute(?CarbonInterface $now = null): array
    {
        $now ??= now();

        $total = PartnershipProspect::query()->count();
        $rate = static fn (int $part): float => $total > 0 ? round($part / $total * 100, 1) : 0.0;

        $byStatus = PartnershipProspect::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $funnel = [];

        foreach (PartnershipProspect::STATUSES as $status) {
            $count = (int) ($byStatus[$status] ?? 0);
            $funnel[$status] = ['count' => $count, 'share' => $rate($count)];
        }

        $booked = PartnershipProspect::query()->whereNotNull('booked_at')->count();
        $converted = $funnel['converted']['count'];

        $breakdowns = [];

        foreach (array_keys(PartnershipProspectOptions::all()) as $field) {
            $breakdowns[$field] = self::breakdown($field, $rate);
        }

        return [
            'total' => $total,
            'last7' => PartnershipProspect::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'last30' => PartnershipProspect::query()->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'awaitingContact' => $funnel['new']['count'],
            'booked' => $booked,
            'bookedRate' => $rate($booked),
            'converted' => $converted,
            'conversionRate' => $rate($converted),
            'manual' => PartnershipProspect::query()->where('source', 'manual')->count(),
            'funnel' => $funnel,
            'breakdowns' => $breakdowns,
            'topSources' => self::topSources(),
            'topLandingPages' => self::topLandingPages(),
            'recent' => self::recent(),
            'computedAt' => $now->getTimestamp(),
        ];
    }

    /**
     * One answer's distribution, in the form's own order, with every option present.
     *
     * Zero rows are kept rather than dropped: two of the three questions are ordered bands, and
     * a missing band reads as a gap in the scale rather than as "nobody picked this". A slug no
     * longer on the list is appended at the end under its own name, as
     * PartnershipProspectOptions::label() prints it everywhere else.
     *
     * @param  callable(int): float  $rate
     * @return array<int, array{slug: string, label: string, count: int, converted: int, share: float}>
     */
    private static function breakdown(string $field, callable $rate): array
    {
        $grouped = PartnershipProspect::query()
            ->selectRaw($field.' as slug, COUNT(*) as aggregate')
            ->selectRaw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted")
            ->groupBy($field)
            ->get()
            ->keyBy(static fn ($row) => (string) $row->slug);

        $slugs = array_values(array_unique([
            ...PartnershipProspectOptions::slugs($field),
            ...$grouped->keys()->all(),
        ]));

        return array_map(static function (string $slug) use ($field, $grouped, $rate) {
            $row = $grouped->get($slug);
            $count = (int) ($row->aggregate ?? 0);

            return [
                'slug' => $slug,
                'label' => PartnershipProspectOptions::label($field, $slug),
                'count' => $count,
                'converted' => (int) ($row->converted ?? 0),
                'share' => $rate($count),
            ];
        }, $slugs);
    }

    /**
     * Where prospects came from, by `utm_source`.
     *
     * Manual entries are their own row rather than being filed under "Untagged": they have no
     * UTMs because nobody clicked anything, which is a different fact from a visitor arriving
     * without a campaign tag. Sources are compared case-insensitively, so a campaign tagged
     * `LinkedIn` in one place and `linkedin` in another is one row.
     *
     * @return array<int, array{label: string, count: int, converted: int}>
     */
    private static function topSources(): array
    {
        $rows = PartnershipProspect::query()
            ->selectRaw("source, LOWER(COALESCE(utm_source, '')) as bucket, COUNT(*) as aggregate")
            ->selectRaw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted")
            ->groupByRaw("source, LOWER(COALESCE(utm_source, ''))")
            ->get();

        $sources = [];

        foreach ($rows as $row) {
            $label = match (true) {
                $row->source === 'manual' => PartnershipProspect::SOURCES['manual'],
                trim((string) $row->bucket) === '' => self::UNTAGGED,
                default => trim((string) $row->bucket),
            };

            $sources[$label] ??= ['label' => $label, 'count' => 0, 'converted' => 0];
            $sources[$label]['count'] += (int) $row->aggregate;
            $sources[$label]['converted'] += (int) $row->converted;
        }

        return self::top(array_values($sources));
    }

    /**
     * The pages the form was submitted from, by path, so `?utm_source=` variants fold together.
     *
     * @return array<int, array{label: string, count: int, converted: int}>
     */
    private static function topLandingPages(): array
    {
        $rows = PartnershipProspect::query()
            ->whereNotNull('landing_url')
            ->where('landing_url', '!=', '')
            ->selectRaw('landing_url, COUNT(*) as aggregate')
            ->selectRaw("SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted")
            ->groupBy('landing_url')
            ->get();

        $pages = [];

        foreach ($rows as $row) {
            $path = (string) (parse_url((string) $row->landing_url, PHP_URL_PATH) ?: '/');
            $pages[$path] ??= ['label' => $path, 'count' => 0, 'converted' => 0];
            $pages[$path]['count'] += (int) $row->aggregate;
            $pages[$path]['converted'] += (int) $row->converted;
        }

        return self::top(array_values($pages));
    }

    /**
     * @param  array<int, array{label: string, count: int, converted: int}>  $rows
     * @return array<int, array{label: string, count: int, converted: int}>
     */
    private static function top(array $rows): array
    {
        usort($rows, static fn (array $a, array $b) => [$b['count'], $b['converted'], $a['label']] <=> [$a['count'], $a['converted'], $b['label']]);

        return array_slice($rows, 0, self::TOP);
    }

    /**
     * @return array<int, array{id: int, name: string, company: string, status: string, organization: string, source: string, booked: bool, created_at: ?int}>
     */
    private static function recent(): array
    {
        return PartnershipProspect::query()
            ->latest('id')
            ->take(self::RECENT)
            ->get()
            ->map(static fn (PartnershipProspect $prospect) => [
                'id' => (int) $prospect->id,
                'name' => $prospect->fullName(),
                'company' => (string) $prospect->company,
                'status' => (string) $prospect->status,
                'organization' => PartnershipProspectOptions::label('organization_type', $prospect->organization_type),
                'source' => (string) $prospect->source,
                'booked' => $prospect->hasBookedCall(),
                'created_at' => $prospect->created_at?->getTimestamp(),
            ])
            ->all();
    }
}
