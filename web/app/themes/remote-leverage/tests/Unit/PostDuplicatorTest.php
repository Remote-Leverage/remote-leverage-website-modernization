<?php

declare(strict_types=1);

use App\Infrastructure\WordPress\Admin\DuplicatePostAdmin;
use App\Infrastructure\WordPress\PostDuplicator;

beforeEach(function () {
    $GLOBALS['_wp_mock_posts'] = [];
    $GLOBALS['_wp_mock_post_meta'] = [];
    $GLOBALS['_wp_mock_object_terms'] = [];
    $GLOBALS['_wp_mock_taxonomies'] = [];
    unset(
        $GLOBALS['_wp_mock_capabilities'],
        $GLOBALS['wp_is_admin'],
        $GLOBALS['wp_is_singular'],
        $GLOBALS['wp_queried_object'],
        $_GET['post'],
        $_GET['_wpnonce'],
    );

    $GLOBALS['_wp_mock_posts'][10] = [
        'ID' => 10,
        'post_type' => 'rl_partner',
        'post_status' => 'publish',
        'post_title' => 'Oyster',
        'post_name' => 'oyster',
        'post_content' => '<!-- wp:acf/hero {"data":{"headline":"Line\\u003cbr\\u003e"}} /-->',
        'post_excerpt' => 'Hub excerpt',
    ];

    update_post_meta(10, '_rl_partner_name', 'Oyster');
    update_post_meta(10, '_rl_partner_code', 'RL-OYSTER');
    update_post_meta(10, '_rl_services', ['0' => ['name' => 'Payroll']]);
    update_post_meta(10, '_rl_override_welcome_text', 'C:\\path');
    update_post_meta(10, '_yoast_wpseo_canonical', 'https://remoteleverage.com/partners/oyster/');
    update_post_meta(10, '_edit_lock', '123:1');
});

describe('PostDuplicator', function () {
    test('copies the post as a draft titled "(Copy)" with content intact', function () {
        $copyId = (new PostDuplicator)->duplicate(10);
        $copy = $GLOBALS['_wp_mock_posts'][$copyId];

        expect($copyId)->not->toBe(10)
            ->and($copy['post_type'])->toBe('rl_partner')
            ->and($copy['post_status'])->toBe('draft')
            ->and($copy['post_title'])->toBe('Oyster (Copy)')
            ->and($copy['post_excerpt'])->toBe('Hub excerpt')
            // The unicode escapes in block JSON survive wp_insert_post()'s unslash.
            ->and($copy['post_content'])->toBe($GLOBALS['_wp_mock_posts'][10]['post_content']);
    });

    test('copies meta, including arrays and backslashes, but not identity keys', function () {
        $copyId = (new PostDuplicator)->duplicate(10);
        $meta = $GLOBALS['_wp_mock_post_meta'][$copyId];

        expect($meta['_rl_partner_name'])->toBe('Oyster')
            ->and($meta['_rl_services'])->toBe(['0' => ['name' => 'Payroll']])
            ->and($meta['_rl_override_welcome_text'])->toBe('C:\\path')
            ->and($meta)->not->toHaveKeys(['_rl_partner_code', '_yoast_wpseo_canonical', '_edit_lock']);
    });

    test('copies taxonomy terms', function () {
        $GLOBALS['_wp_mock_taxonomies']['rl_partner'] = ['category'];
        $GLOBALS['_wp_mock_object_terms'][10]['category'] = [3, 7];

        $copyId = (new PostDuplicator)->duplicate(10);

        expect($GLOBALS['_wp_mock_object_terms'][$copyId]['category'])->toBe([3, 7]);
    });

    test('returns an error for a missing post', function () {
        expect((new PostDuplicator)->duplicate(999))->toBeInstanceOf(WP_Error::class);
    });
});

describe('DuplicatePostAdmin', function () {
    test('adds a nonce-protected Duplicate row action', function () {
        $actions = (new DuplicatePostAdmin(new PostDuplicator))->addRowAction([], get_post(10));

        expect($actions['rl_duplicate'])->toContain('action=rl_duplicate_post')
            ->and($actions['rl_duplicate'])->toContain('post=10')
            ->and($actions['rl_duplicate'])->toContain('_wpnonce=');
    });

    test('offers no action on unsupported types or to users who cannot create them', function () {
        $admin = new DuplicatePostAdmin(new PostDuplicator);

        $GLOBALS['_wp_mock_posts'][11] = ['ID' => 11, 'post_type' => 'attachment'];
        expect($admin->addRowAction([], get_post(11)))->toBe([]);

        $GLOBALS['_wp_mock_capabilities'] = ['edit_post'];
        expect($admin->addRowAction([], get_post(10)))->toBe([]);
    });

    test('adds a Duplicate node beside Edit on the front end of a singular page', function () {
        $GLOBALS['wp_is_singular'] = true;
        $GLOBALS['wp_queried_object'] = get_post(10);
        $bar = new WP_Admin_Bar;

        (new DuplicatePostAdmin(new PostDuplicator))->addAdminBarNode($bar);

        expect($bar->get_node('rl-duplicate')['title'])->toBe('Duplicate Rl_partner')
            ->and($bar->get_node('rl-duplicate')['href'])->toContain('post=10');
    });

    test('adds no admin-bar node in wp-admin or on archives', function () {
        $admin = new DuplicatePostAdmin(new PostDuplicator);
        $GLOBALS['wp_queried_object'] = get_post(10);

        $bar = new WP_Admin_Bar;
        $admin->addAdminBarNode($bar);
        expect($bar->nodes)->toBe([]);

        $GLOBALS['wp_is_singular'] = true;
        $GLOBALS['wp_is_admin'] = true;
        $admin->addAdminBarNode($bar);
        expect($bar->nodes)->toBe([]);
    });

    test('duplicates on a valid request and returns the copy\'s edit link', function () {
        $_GET['post'] = '10';
        $_GET['_wpnonce'] = wp_create_nonce('rl_duplicate_post_10');

        $url = (new DuplicatePostAdmin(new PostDuplicator))->duplicateFromRequest();

        expect($url)->toContain('post=11&action=edit')
            ->and($GLOBALS['_wp_mock_posts'][11]['post_title'])->toBe('Oyster (Copy)');
    });

    test('rejects a request with a bad nonce', function () {
        $_GET['post'] = '10';
        $_GET['_wpnonce'] = 'forged';

        expect(fn () => (new DuplicatePostAdmin(new PostDuplicator))->duplicateFromRequest())
            ->toThrow(RuntimeException::class, 'expired');
        expect($GLOBALS['_wp_mock_posts'])->toHaveCount(1);
    });
});
