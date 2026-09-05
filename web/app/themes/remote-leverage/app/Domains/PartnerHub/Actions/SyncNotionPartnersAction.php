<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

use App\Domains\PartnerHub\Services\NotionSyncService;
use Illuminate\Support\Facades\Cache;

class SyncNotionPartnersAction
{
    public const CACHE_KEY = 'rl_notion_partners_cache';

    public const CACHE_TTL_SECONDS = 3600; // 1 hour

    public function __construct(
        protected NotionSyncService $notionSync
    ) {}

    /**
     * Synchronize and cache Notion partner records.
     */
    public function execute(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $partners = $this->notionSync->fetchPartners();

            return array_map(fn ($partner) => $partner->toArray(), $partners);
        });
    }
}
