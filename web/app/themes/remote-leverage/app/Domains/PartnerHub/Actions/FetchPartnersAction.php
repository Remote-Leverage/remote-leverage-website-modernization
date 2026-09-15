<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Actions;

use App\Domains\PartnerHub\Models\PartnerProfile;
use WP_Query;

/**
 * Loads every published `rl_partner` entry as a directory-ready array.
 *
 * Replaced SyncNotionPartnersAction on 2026-09-15: the directory now reads the
 * same CPT that backs the co-branded hub pages, so a partner is authored once
 * and no external service has to be reachable for `/partners/` to render.
 *
 * Deliberately uncached — this is a handful of local posts, and the 1-hour
 * cache the Notion version needed meant an edit took up to an hour to show.
 */
class FetchPartnersAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function execute(): array
    {
        $query = new WP_Query([
            'post_type' => 'rl_partner',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ]);

        return array_map(
            static fn ($post) => PartnerProfile::fromPost($post)->toArray(),
            $query->posts,
        );
    }
}
