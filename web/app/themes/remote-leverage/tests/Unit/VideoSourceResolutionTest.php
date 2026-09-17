<?php

declare(strict_types=1);

use App\Support\BlockDefaults;

/**
 * Covers BlockDefaults::video(), added on 2026-09-17 for videos that 404'd everywhere but here.
 *
 * `public/videos/` is gitignored and excluded from the Docker build context, so the 61MB VSL
 * that `/about-us/` and `/vathankyou/` point at played locally and was simply absent on staging
 * and the production preview host. The fix is a two-location search — EFS `uploads/videos/`
 * first, the theme's built `public/videos/` second — and what has to hold is the *order*, plus
 * the same zero-byte guard the images already grew:
 *
 *   · uploads first is what lets an operator swap the VSL without a deploy;
 *   · the theme second is what makes small clips work in every environment for free;
 *   · a zero-byte upload must fall through, because staging's EFS has carried empty files
 *     before (see ZeroByteImageFallbackTest) and an empty MP4 plays as a dead player, which
 *     looks the same in a screenshot as one that simply has not started;
 *   · and a miss must name the uploads URL, because that is the location someone can fix.
 *
 * None of that is visible from the return value alone, so the files are real files on disk.
 */

/*
 * The resolver reads actual paths, so WordPress's locators have to point somewhere this
 * process owns. These follow stubs.php: guarded definitions, with the part a test needs to
 * steer held in $GLOBALS. WP_CONTENT_DIR is a constant and cannot be re-pointed per test, so
 * the root is fixed once here and only the files underneath it change.
 */
$root = sys_get_temp_dir().'/rl-video-'.bin2hex(random_bytes(4));

if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', $root.'/content');
}

/*
 * The stub is global once defined, and other code reaches for get_theme_file_path() to find
 * real files — SocialKit::themePath() loads its six signature templates through it. So it
 * defaults to the actual theme root and only the tests below swap it, for the length of one
 * test each. Pointing it at the temp tree for the whole run silently emptied SocialKit's
 * template list, and that test failed a long way from here.
 */
$GLOBALS['rl_theme_dir'] ??= dirname(__DIR__, 2);
$GLOBALS['rl_video_theme_dir'] = $root.'/theme';

if (! function_exists('content_url')) {
    function content_url($path = '')
    {
        return 'https://example.test/app'.$path;
    }
}

if (! function_exists('get_theme_file_path')) {
    function get_theme_file_path($file = '')
    {
        return rtrim((string) $GLOBALS['rl_theme_dir'], '/').'/'.ltrim((string) $file, '/');
    }
}

if (! function_exists('get_template_directory_uri')) {
    function get_template_directory_uri()
    {
        return 'https://example.test/app/themes/remote-leverage';
    }
}

if (! function_exists('set_url_scheme')) {
    function set_url_scheme($url, $scheme = null)
    {
        return preg_replace('#^\w+://#', ($scheme ?: 'http').'://', (string) $url);
    }
}

describe('video() searches uploads before the theme', function () {
    beforeEach(function () {
        $this->realThemeDir = $GLOBALS['rl_theme_dir'];
        $GLOBALS['rl_theme_dir'] = $GLOBALS['rl_video_theme_dir'];

        $this->uploads = WP_CONTENT_DIR.'/uploads/videos';
        $this->theme = get_theme_file_path('public/videos');

        foreach ([$this->uploads, $this->theme] as $dir) {
            is_dir($dir) || mkdir($dir, 0777, true);
        }

        // Both locations reuse the same filenames from test to test, and is_file()/filesize()
        // read PHP's stat cache. Without this a file deleted by the previous test can still
        // answer, which would make the fallback cases pass for the wrong reason.
        clearstatcache(true);
    });

    afterEach(function () {
        $rm = function (string $dir) use (&$rm): void {
            foreach (glob($dir.'/*') ?: [] as $entry) {
                is_dir($entry) ? $rm($entry) : unlink($entry);
            }
            rmdir($dir);
        };

        foreach ([$this->uploads, $this->theme] as $dir) {
            is_dir($dir) && $rm($dir);
        }

        // The scaffolding above those two directories is this run's alone, so take it with us
        // rather than leaving an empty tree in the temp dir on every CI run.
        $scaffolding = [
            WP_CONTENT_DIR.'/uploads',
            WP_CONTENT_DIR,
            dirname($this->theme),
            $GLOBALS['rl_video_theme_dir'],
            dirname($GLOBALS['rl_video_theme_dir']),
        ];

        foreach ($scaffolding as $dir) {
            if (is_dir($dir) && (glob($dir.'/*') ?: []) === []) {
                rmdir($dir);
            }
        }

        $GLOBALS['rl_theme_dir'] = $this->realThemeDir;

        clearstatcache(true);
    });

    test('an uploads copy wins over a theme copy of the same name', function () {
        file_put_contents($this->uploads.'/vsl.mp4', 'from-efs');
        file_put_contents($this->theme.'/vsl.mp4', 'from-theme');

        // Uploads winning is the whole reason the VSL can be replaced without a deploy; if the
        // theme won, a build would be needed to ship a new cut and the EFS copy would be dead
        // weight nobody could tell was stale.
        expect(BlockDefaults::video('vsl.mp4'))
            ->toBe('https://example.test/app/uploads/videos/vsl.mp4');
    });

    test('it falls back to the theme copy when uploads has nothing', function () {
        file_put_contents($this->theme.'/walkthrough.mp4', 'from-theme');

        // The small-clip path: in git, built into public/videos/ by themeVideos(), present in
        // every environment without anyone uploading anything.
        expect(BlockDefaults::video('walkthrough.mp4'))
            ->toBe('https://example.test/app/themes/remote-leverage/public/videos/walkthrough.mp4');
    });

    test('a zero-byte upload does not shadow a working theme copy', function () {
        file_put_contents($this->uploads.'/vsl.mp4', '');
        file_put_contents($this->theme.'/vsl.mp4', 'from-theme');

        // An interrupted EFS copy leaves a file that is_file() calls present and that answers
        // 200 with content-length 0. Because uploads is searched first, accepting it would
        // permanently hide a good theme copy — the exact failure isUsableFile() exists for,
        // now reaching video() too.
        expect(BlockDefaults::video('vsl.mp4'))
            ->toBe('https://example.test/app/themes/remote-leverage/public/videos/vsl.mp4');
    });

    test('a file in neither location resolves to the uploads URL', function () {
        // Deliberately not the theme URL. Both are 404s, but only one of them is somewhere an
        // operator can drop the file to fix the page; pointing at the theme would imply a
        // deploy is required.
        expect(BlockDefaults::video('missing.mp4'))
            ->toBe('https://example.test/app/uploads/videos/missing.mp4');
    });

    test('a subdirectory survives and a leading slash does not double up', function () {
        mkdir($this->theme.'/home', 0777, true);
        file_put_contents($this->theme.'/home/clip.mp4', 'x');

        expect(BlockDefaults::video('/home/clip.mp4'))
            ->toBe(BlockDefaults::video('home/clip.mp4'))
            ->toBe('https://example.test/app/themes/remote-leverage/public/videos/home/clip.mp4');
    });
});
