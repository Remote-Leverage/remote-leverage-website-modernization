<?php

declare(strict_types=1);

namespace App\View;

use App\Support\RolePages;
use Walker_Nav_Menu;

/**
 * The default primary navigation: Reviews, Roles and Pricing.
 *
 * Two consumers, one list. `PrimaryNavigationSeeder` writes it into a real nav menu on deploy so
 * the nav can be edited in Appearance > Menus, and `fallback()` renders it through the same
 * walkers when no menu is assigned. Because both paths go through the walkers, a seeded menu and
 * the fallback produce the same markup.
 *
 * Once seeded, the database owns the nav. Editing this list changes the fallback and what a fresh
 * database is seeded with, and nothing else. A live site takes nav changes in Appearance > Menus.
 */
final class PrimaryNavigation
{
    public const LOCATION = 'primary_navigation';

    /**
     * Role pages that exist only in the database: created in wp-admin after the RolePages map,
     * with no pattern behind them (HELP 453). Linked only where the page exists, so a local or
     * staging database without them gets no dead links.
     */
    private const DATABASE_ROLE_PAGES = [
        'engineering-virtual-assistants',
        'technical-virtual-assistants',
        'custom-role-virtual-assistants',
    ];

    /**
     * The nav as written, before resolving against the database.
     *
     * `optional` entries are dropped when no published page sits at their path.
     *
     * @return list<array{title: string, path: string, optional?: bool, children?: list<array{title: string, path: string, optional?: bool}>}>
     */
    public static function definition(): array
    {
        $roles = array_map(
            fn (string $slug) => ['title' => RolePages::title($slug), 'path' => $slug.'/'],
            RolePages::slugs()
        );

        foreach (self::DATABASE_ROLE_PAGES as $slug) {
            $roles[] = ['title' => '', 'path' => $slug.'/', 'optional' => true];
        }

        return [
            ['title' => 'Reviews', 'path' => 'reviews/', 'children' => [
                ['title' => 'Testimonial Reviews', 'path' => 'reviews/'],
                ['title' => 'Case Studies', 'path' => 'case-study/'],
                ['title' => 'Sample Applicant Recordings', 'path' => 'samples/'],
            ]],
            ['title' => 'Roles', 'path' => 'admin-virtual-assistants/', 'children' => $roles],
            ['title' => 'Pricing', 'path' => 'vapricing/'],
        ];
    }

    /**
     * The definition resolved against this database: each entry gains the ID of the published
     * page at its path (0 when there is none), optional entries without a page are dropped, and
     * an untitled entry takes its page's title.
     *
     * @return list<array{title: string, path: string, page_id: int, children: list<array{title: string, path: string, page_id: int}>}>
     */
    public static function resolved(): array
    {
        $resolve = function (array $item): ?array {
            $page = function_exists('get_page_by_path') ? get_page_by_path(rtrim($item['path'], '/')) : null;
            $pageId = ($page && $page->post_status === 'publish') ? (int) $page->ID : 0;

            if (($item['optional'] ?? false) && $pageId === 0) {
                return null;
            }

            return [
                'title' => $item['title'] !== '' ? $item['title'] : ($pageId ? get_the_title($pageId) : $item['path']),
                'path' => $item['path'],
                'page_id' => $pageId,
            ];
        };

        $items = [];

        foreach (self::definition() as $item) {
            $top = $resolve($item);

            if ($top === null) {
                continue;
            }

            $top['children'] = array_values(array_filter(array_map($resolve, $item['children'] ?? [])));
            $items[] = $top;
        }

        return $items;
    }

    /**
     * `wp_nav_menu()`'s `fallback_cb`: the default nav, rendered through the caller's walker.
     *
     * The items are shaped like the `WP_Post` objects `wp_nav_menu()` hands a walker. Their IDs
     * are negative so no `the_title` filter can mistake one for a real post.
     */
    public static function fallback(array $args): string
    {
        $walker = ($args['walker'] ?? null) instanceof Walker_Nav_Menu ? $args['walker'] : new NavWalker;
        $items = [];
        $id = 0;

        foreach (self::resolved() as $top) {
            $parentId = --$id;
            $items[] = self::fallbackItem($parentId, 0, $top);

            foreach ($top['children'] as $child) {
                $items[] = self::fallbackItem(--$id, $parentId, $child);
            }
        }

        $html = $walker->walk($items, (int) ($args['depth'] ?? 0), (object) $args);

        // wp_nav_menu() fills %1$s with a generated id; the fallback has no menu to name it after.
        $wrap = str_replace(' id="%1$s"', '', $args['items_wrap'] ?? '<ul id="%1$s" class="%2$s">%3$s</ul>');

        return sprintf($wrap, '', esc_attr($args['menu_class'] ?? ''), $html);
    }

    private static function fallbackItem(int $id, int $parentId, array $item): object
    {
        return (object) [
            'ID' => $id,
            'db_id' => $id,
            'menu_item_parent' => $parentId,
            'title' => $item['title'],
            'url' => $item['page_id'] ? get_permalink($item['page_id']) : home_url('/'.$item['path']),
            'classes' => [],
            'attr_title' => '',
            'target' => '',
            'xfn' => '',
        ];
    }
}
