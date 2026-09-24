<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RolePages;
use App\View\PrimaryNavigation;

/**
 * The primary nav is editable in Appearance > Menus (HELP 453).
 *
 * Before this, the header rendered a hard-coded fallback and no menu was ever assigned, so edits
 * in wp-admin never reached the site. Now the deploy seeds the default nav into a real menu once,
 * and the fallback renders that same list through the same walkers.
 *
 * WordPress is not booted here, so these pin the list and the wiring. The seeder and walkers were
 * verified against a real database: the seeded menu and the fallback both render markup identical
 * to the hand-written nav they replaced.
 */
describe('the default primary navigation', function () {
    test('is Reviews, Roles and Pricing, with every role page under Roles in map order', function () {
        $nav = PrimaryNavigation::definition();

        expect(array_column($nav, 'title'))->toBe(['Reviews', 'Roles', 'Pricing']);

        $required = array_values(array_filter($nav[1]['children'], fn ($item) => ! ($item['optional'] ?? false)));

        expect(array_column($required, 'path'))
            ->toBe(array_map(fn ($slug) => $slug.'/', RolePages::slugs()));
    });

    test('links the database-only role pages only where they exist', function () {
        $optional = array_values(array_filter(
            PrimaryNavigation::definition()[1]['children'],
            fn ($item) => $item['optional'] ?? false
        ));

        expect(array_column($optional, 'path'))->toBe([
            'engineering-virtual-assistants/',
            'technical-virtual-assistants/',
            'custom-role-virtual-assistants/',
        ]);
    });
});

describe('wiring', function () {
    $theme = dirname(__DIR__, 2);

    test('both header navs read the menu location and fall back to the shared default', function () use ($theme) {
        $header = (string) file_get_contents($theme.'/resources/views/sections/header.blade.php');

        expect(substr_count($header, "'theme_location' => 'primary_navigation'"))->toBe(2)
            ->and(substr_count($header, "'fallback_cb' => [\\App\\View\\PrimaryNavigation::class, 'fallback']"))->toBe(2)
            // No hand-written copy left to drift from the menu.
            ->and($header)->not->toContain('home_url(\'/vapricing\')');
    });

    test('the deploy seeds the menu once and never fails a release over it', function () use ($theme) {
        $command = (string) file_get_contents($theme.'/app/Infrastructure/Console/Commands/RunDeployTasksCommand.php');
        $seeder = (string) file_get_contents($theme.'/app/Infrastructure/WordPress/PrimaryNavigationSeeder.php');

        expect($command)->toContain('$this->seedPrimaryNavigation();')
            ->and($command)->toContain('Could not seed the primary navigation')
            // Seed-once: a deploy must never reset what was edited in Appearance > Menus.
            ->and($seeder)->toContain('if (get_option(self::SEEDED_OPTION))')
            ->and($seeder)->toContain('$this->markSeeded();');
    });
});
