<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Models;

readonly class PartnerProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $description,
        public ?string $logoUrl = null,
        public ?string $websiteUrl = null,
        public ?string $perkDescription = null,
        public bool $featured = false,
        public array $tags = [],
    ) {}

    public static function fromNotion(array $page): self
    {
        $props = $page['properties'] ?? [];

        $name = $props['Name']['title'][0]['plain_text'] ?? 'Unknown Partner';
        $category = $props['Category']['select']['name'] ?? 'General';
        $description = $props['Description']['rich_text'][0]['plain_text'] ?? '';
        $websiteUrl = $props['Website']['url'] ?? null;
        $perkDescription = $props['Perk']['rich_text'][0]['plain_text'] ?? null;
        $featured = (bool) ($props['Featured']['checkbox'] ?? false);
        $tags = array_map(fn ($tag) => $tag['name'], $props['Tags']['multi_select'] ?? []);

        return new self(
            id: $page['id'],
            name: $name,
            category: $category,
            description: $description,
            logoUrl: $page['cover']['file']['url'] ?? $page['cover']['external']['url'] ?? null,
            websiteUrl: $websiteUrl,
            perkDescription: $perkDescription,
            featured: $featured,
            tags: $tags,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'logo_url' => $this->logoUrl,
            'website_url' => $this->websiteUrl,
            'perk_description' => $this->perkDescription,
            'featured' => $this->featured,
            'tags' => $this->tags,
        ];
    }
}
