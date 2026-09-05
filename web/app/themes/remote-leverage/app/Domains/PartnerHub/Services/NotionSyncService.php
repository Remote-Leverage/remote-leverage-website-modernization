<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Services;

use App\Domains\PartnerHub\Models\PartnerProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotionSyncService
{
    protected ?string $apiKey;

    protected ?string $databaseId;

    public function __construct()
    {
        $this->apiKey = config('services.notion.api_key');
        $this->databaseId = config('services.notion.database_id');
    }

    /**
     * Query Notion partners database and parse records.
     *
     * @return array<PartnerProfile>
     */
    public function fetchPartners(): array
    {
        if (! $this->apiKey || ! $this->databaseId) {
            Log::debug('NotionSyncService: Missing Notion credentials');

            return [];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders([
                    'Notion-Version' => '2022-06-28',
                ])
                ->post("https://api.notion.com/v1/databases/{$this->databaseId}/query", [
                    'page_size' => 100,
                ]);

            if ($response->failed()) {
                Log::error('NotionSyncService: Query failed', $response->json());

                return [];
            }

            $results = $response->json('results', []);
            $profiles = [];

            foreach ($results as $page) {
                $profiles[] = PartnerProfile::fromNotion($page);
            }

            return $profiles;
        } catch (\Throwable $e) {
            Log::error('NotionSyncService Exception: '.$e->getMessage());

            return [];
        }
    }
}
