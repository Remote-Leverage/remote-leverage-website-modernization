<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

class QueryPartnersAction
{
    public function __construct(
        protected SyncNotionPartnersAction $syncPartnersAction
    ) {}

    /**
     * Query, filter, and search partner listings.
     */
    public function execute(?string $search = null, ?string $category = null, bool $featuredOnly = false): array
    {
        $allPartners = $this->syncPartnersAction->execute();

        return array_values(array_filter($allPartners, function (array $partner) use ($search, $category, $featuredOnly) {
            if ($featuredOnly && empty($partner['featured'])) {
                return false;
            }

            if ($category && $category !== 'All' && strcasecmp($partner['category'], $category) !== 0) {
                return false;
            }

            if ($search && trim($search) !== '') {
                $term = strtolower(trim($search));
                $nameMatches = str_contains(strtolower($partner['name']), $term);
                $descMatches = str_contains(strtolower($partner['description']), $term);
                $perkMatches = str_contains(strtolower($partner['perk_description'] ?? ''), $term);

                if (! $nameMatches && ! $descMatches && ! $perkMatches) {
                    return false;
                }
            }

            return true;
        }));
    }
}
