<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Models;

use WP_Post;

/**
 * One partner as the `/partners/` directory grid renders it.
 *
 * Built from the `rl_partner` CPT — the same post that backs the co-branded
 * hub the card links to, so a partner's identity is authored in exactly one
 * place. (Until 2026-09-15 this was hydrated from a Notion database instead,
 * which left the directory empty whenever Notion was unconfigured and had no
 * connection to the CPT entries the hub pages were built from.)
 */
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
        public ?string $slug = null,
    ) {}

    /**
     * Hydrate from an `rl_partner` post.
     *
     * Falls back to the post title when the partner name meta is unset, so an
     * entry created without opening the Branding tab still renders a usable
     * card rather than an empty one.
     */
    public static function fromPost(WP_Post $post): self
    {
        $meta = static fn (string $key): string => (string) get_post_meta($post->ID, $key, true);

        $logoUrl = get_field('_rl_partner_logo_url', $post->ID);

        return new self(
            id: (string) $post->ID,
            name: $meta('_rl_partner_name') ?: $post->post_title,
            category: $meta('_rl_directory_category') ?: 'Staffing & HR',
            description: $meta('_rl_directory_description'),
            logoUrl: is_string($logoUrl) && $logoUrl !== '' ? $logoUrl : null,
            websiteUrl: $meta('_rl_partner_website') ?: null,
            perkDescription: $meta('_rl_directory_perk') ?: null,
            featured: $meta('_rl_directory_featured') === '1',
            tags: array_values(array_filter([$meta('_rl_partner_code')])),
            slug: $post->post_name,
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
            // The directory card links to /partners/{slug}/overview.
            'slug' => $this->slug,
        ];
    }
}
