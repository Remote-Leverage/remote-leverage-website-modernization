<?php

declare(strict_types=1);

use App\Support\SocialKit;

/**
 * Guards the ported `rl-social-kit` data layer.
 *
 * `resources/js/social-kit.js` and `resources/css/social-kit.css` are the plugin's files
 * carried over verbatim, so the contracts they depend on — option names, the
 * `rl_social_kit_vars` shape, the resource array keys, the size formatting — are not
 * refactorable. These tests pin them.
 */
$theme = dirname(__DIR__, 2);

it('formats sizes the way the plugin did', function () {
    // Bytes below 1 KiB, KiB below 1 MiB, MiB above, all to two decimals.
    expect(SocialKit::formatSize(0))->toBe('0 Bytes')
        ->and(SocialKit::formatSize(1023))->toBe('1023 Bytes')
        ->and(SocialKit::formatSize(1024))->toBe('1 KB')
        ->and(SocialKit::formatSize(584022))->toBe('570.33 KB')
        ->and(SocialKit::formatSize(1048575))->toBe('1024 KB')
        ->and(SocialKit::formatSize(1048576))->toBe('1 MB')
        ->and(SocialKit::formatSize(1869777))->toBe('1.78 MB');
});

it('returns the plugin resource shape for every entry', function () use ($theme) {
    $resources = SocialKit::scan($theme.'/resources/images/pages/social-media-kit/linkedin', 'https://example.test/kit/linkedin/');

    expect($resources)->not->toBeEmpty();

    foreach ($resources as $resource) {
        expect(array_keys($resource))
            ->toBe(['name', 'fullname', 'url', 'size', 'extension', 'is_image']);
    }

    $first = $resources[0];

    expect($first['name'])->toBe('RL_LKD_PersonalBanner_01_4400x1100')
        ->and($first['fullname'])->toBe('RL_LKD_PersonalBanner_01_4400x1100.jpg')
        ->and($first['url'])->toBe('https://example.test/kit/linkedin/RL_LKD_PersonalBanner_01_4400x1100.jpg')
        ->and($first['extension'])->toBe('jpg')
        ->and($first['is_image'])->toBeTrue()
        ->and($first['size'])->toBe('570.33 KB');
});

it('scans the tracked source, not the published output', function () use ($theme) {
    // `themeImages()` writes a sibling .webp next to every raster, so scanning public/images
    // would double every grid (16 LinkedIn cards instead of 8) and label each asset with the
    // re-encoded byte count instead of the size of the file the browser actually downloads.
    $counts = [
        'linkedin' => 8,
        'facebook' => 10,
        'other' => 11,
        'instagram' => 0,
    ];

    foreach ($counts as $tab => $expected) {
        expect(SocialKit::scan($theme.'/resources/images/pages/social-media-kit/'.$tab, '/kit/'.$tab.'/'))
            ->toHaveCount($expected, "{$tab} should expose {$expected} downloads");
    }
});

it('percent-encodes filenames in download URLs', function () {
    $dir = sys_get_temp_dir().'/rl-social-kit-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/brand guide v2.pdf', 'x');

    $resources = SocialKit::scan($dir, 'https://example.test/kit/other/');

    expect($resources[0]['url'])->toBe('https://example.test/kit/other/brand%20guide%20v2.pdf')
        ->and($resources[0]['is_image'])->toBeFalse()
        ->and($resources[0]['extension'])->toBe('pdf');

    unlink($dir.'/brand guide v2.pdf');
    rmdir($dir);
});

it('ignores .DS_Store and subdirectories', function () {
    $dir = sys_get_temp_dir().'/rl-social-kit-'.uniqid();
    mkdir($dir.'/nested', 0777, true);
    file_put_contents($dir.'/.DS_Store', 'junk');
    file_put_contents($dir.'/logo.png', 'x');

    expect(SocialKit::scan($dir, '/kit/'))->toHaveCount(1);

    unlink($dir.'/.DS_Store');
    unlink($dir.'/logo.png');
    rmdir($dir.'/nested');
    rmdir($dir);
});

it('returns nothing for a directory that does not exist or a tab that is not allowed', function () {
    expect(SocialKit::scan('/definitely/not/here', '/kit/'))->toBe([])
        ->and(SocialKit::resources('youtube'))->toBe([]);
});

it('keeps the plugin option names and defaults', function () {
    // An existing wp_options row from the plugin has to keep working untouched.
    expect(SocialKit::OPTION_DEFAULTS)->toMatchArray([
        'rl_social_kit_company_name' => 'Remote Leverage',
        'rl_social_kit_company_website' => 'https://remoteleverage.com',
        'rl_social_kit_company_address' => '1900 Camden Ave, San Jose, CA. 95124, USA',
        'rl_social_kit_global_linkedin' => 'https://www.linkedin.com/company/remote-leverage',
        'rl_social_kit_global_twitter' => 'https://x.com/Remote_Leverage',
        'rl_social_kit_global_facebook' => 'https://www.facebook.com/people/Remote-Leverage',
        'rl_social_kit_global_instagram' => 'https://www.instagram.com/remoteleverageva',
        'rl_social_kit_global_youtube' => 'https://www.youtube.com/@remoteleverage',
    ]);
});

it('leaves the kit public by default', function () {
    // Deliberate divergence from the plugin, which defaulted to '1'. Adrián asked for the kit
    // to be open rather than minting a WordPress user for everyone who wants a banner.
    expect(SocialKit::OPTION_DEFAULTS['rl_social_kit_require_login'])->toBe('0')
        ->and(SocialKit::requireLogin())->toBeFalse();
});

it('honours an explicitly enabled login gate', function () {
    update_option('rl_social_kit_require_login', '1');

    expect(SocialKit::requireLogin())->toBeTrue();

    delete_option('rl_social_kit_require_login');
});

it('ships the six signature templates the script looks up', function () {
    $templates = SocialKit::templates();

    expect(array_keys($templates))->toBe([
        'sig-1-light', 'sig-1-dark',
        'sig-2-light', 'sig-2-dark',
        'sig-3-light', 'sig-3-dark',
    ]);

    foreach ($templates as $html) {
        expect($html)->toContain('{{NAME}}');
    }

    // Template 2 is the only one that renders an avatar; script.js substitutes the brand mark
    // when the field is empty, which is the anonymous-visitor path.
    expect($templates['sig-2-light'])->toContain('{{AVATAR_URL}}')
        ->and($templates['sig-1-light'])->not->toContain('{{AVATAR_URL}}');
});

it('keeps the social-kit signature templates independent of the Livewire generator', function () use ($theme) {
    // `resources/views/signatures/` belongs to the Livewire email-signature generator and has
    // diverged (a different dark background). Sharing the files would silently change what
    // this page produces, so the kit carries its own copies.
    $kit = file_get_contents($theme.'/resources/social-kit/signatures/sig-1-dark.html');

    expect($kit)->toContain('#250D4A');
});

it('points plugin_assets at a directory holding every icon the script joins onto it', function () use ($theme) {
    // script.js does `plugin_assets + 'ln.png'` and `plugin_assets + 'logo-icon-black.svg'`,
    // so the base has to end in a slash and hold all seven brand files.
    expect(SocialKit::assetsBaseUrl())->toEndWith('/public/images/social-media-kit/brand/');

    foreach (['ln.png', 'fb.png', 'ig.png', 'x.png', 'yt.png', 'logo-icon-black.svg', 'logo-icon-white.svg'] as $file) {
        expect($theme.'/resources/images/pages/social-media-kit/brand/'.$file)->toBeFile();
    }
});

it('defaults the company logo next to its dark-theme sibling', function () use ($theme) {
    // script.js derives the dark logo by swapping rl-logo-6.png -> rl-logo-5.png in this URL.
    expect(SocialKit::defaultCompanyLogo())->toEndWith('/public/images/social-media-kit/other/rl-logo-6.png')
        ->and($theme.'/resources/images/pages/social-media-kit/other/rl-logo-5.png')->toBeFile();
});

it('builds the global settings block the script reads', function () {
    expect(array_keys(SocialKit::globalSettings()))->toBe([
        'company_name',
        'company_website',
        'company_address',
        'company_logo',
        'global_linkedin',
        'global_twitter',
        'global_facebook',
        'global_instagram',
        'global_youtube',
    ]);
});

describe('Social Media Kit is a route, not a WordPress page', function () {
    test('the slug is declared once and drives both the route and the asset directories', function () {
        expect(SocialKit::SLUG)->toBe('social-media-kit')
            ->and(SocialKit::assetsBaseUrl())->toContain('/'.SocialKit::SLUG.'/')
            ->and(defined(SocialKit::class.'::PAGE_SLUG'))->toBeFalse();
    });

    test('routes/web.php registers the kit and names it', function () {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

        expect($routes)->toContain('SocialKit::SLUG')
            ->and($routes)->toContain("->name('social-media-kit')");
    });

    test('the view lives under pages/ so the route can render it', function () {
        $theme = dirname(__DIR__, 2);

        expect($theme.'/resources/views/pages/social-media-kit.blade.php')->toBeFile()
            ->and($theme.'/resources/views/page-social-media-kit.blade.php')->not->toBeFile();
    });
});
