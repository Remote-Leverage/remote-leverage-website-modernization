<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress;

use App\View\PrimaryNavigation;
use RuntimeException;

/**
 * Writes the default primary nav into a real nav menu, once, so it can be edited in
 * Appearance > Menus.
 *
 * Until this existed the header only ever rendered its hard-coded fallback: no menu was assigned
 * to `primary_navigation`, so nothing done in Appearance > Menus reached the site (HELP 453).
 *
 * Runs from `rl:deploy`. It seeds once and records that in an option, then never touches the
 * menu again. That flag is what makes the menu safe to edit: a deploy must not reset what
 * marketing changed. A menu already assigned to the location is left alone as well.
 */
class PrimaryNavigationSeeder
{
    public const SEEDED_OPTION = 'rl_primary_nav_seeded';

    private const MENU_NAME = 'Primary Navigation';

    /**
     * @return array{skipped: ?string, menu_id: ?int, items: int}
     */
    public function seed(): array
    {
        if (get_option(self::SEEDED_OPTION)) {
            return ['skipped' => 'already seeded', 'menu_id' => null, 'items' => 0];
        }

        $assigned = (int) (get_nav_menu_locations()[PrimaryNavigation::LOCATION] ?? 0);

        if ($assigned && wp_get_nav_menu_object($assigned)) {
            $this->markSeeded();

            return ['skipped' => 'a menu is already assigned to '.PrimaryNavigation::LOCATION, 'menu_id' => $assigned, 'items' => 0];
        }

        $menuId = $this->createMenu();
        $count = 0;

        foreach (PrimaryNavigation::resolved() as $top) {
            $parentId = $this->addItem($menuId, $top, 0);
            $count++;

            foreach ($top['children'] as $child) {
                $this->addItem($menuId, $child, $parentId);
                $count++;
            }
        }

        $locations = get_nav_menu_locations();
        $locations[PrimaryNavigation::LOCATION] = $menuId;
        set_theme_mod('nav_menu_locations', $locations);

        $this->markSeeded();

        return ['skipped' => null, 'menu_id' => $menuId, 'items' => $count];
    }

    /**
     * A menu someone started by hand but never assigned is not ours to fill or overwrite, so a
     * name clash gets a new menu beside it.
     */
    private function createMenu(): int
    {
        $name = wp_get_nav_menu_object(self::MENU_NAME) ? self::MENU_NAME.' (default)' : self::MENU_NAME;
        $menuId = wp_create_nav_menu($name);

        if (is_wp_error($menuId)) {
            throw new RuntimeException('Could not create the nav menu: '.$menuId->get_error_message());
        }

        return (int) $menuId;
    }

    /**
     * An item with a page behind it is linked as that page, so it follows a slug change and
     * shows as a page in Appearance > Menus. Anything else is a custom link.
     */
    private function addItem(int $menuId, array $item, int $parentId): int
    {
        $args = [
            'menu-item-title' => $item['title'],
            'menu-item-status' => 'publish',
            'menu-item-parent-id' => $parentId,
        ];

        $args += $item['page_id']
            ? ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $item['page_id']]
            : ['menu-item-type' => 'custom', 'menu-item-url' => home_url('/'.$item['path'])];

        $itemId = wp_update_nav_menu_item($menuId, 0, $args);

        if (is_wp_error($itemId)) {
            throw new RuntimeException("Could not add '{$item['title']}' to the nav menu: ".$itemId->get_error_message());
        }

        return (int) $itemId;
    }

    private function markSeeded(): void
    {
        update_option(self::SEEDED_OPTION, gmdate('c'), true);
    }
}
