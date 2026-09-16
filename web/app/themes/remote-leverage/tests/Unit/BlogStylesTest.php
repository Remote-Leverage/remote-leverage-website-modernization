<?php

declare(strict_types=1);

use App\Support\BlogStyles;

/**
 * Guards the blog.css split.
 *
 * blog.css is no longer `@import`ed into app.css — it is a separate Vite entry that
 * App\Support\BlogStyles emits only on blog surfaces, which keeps 4.5 KB gzipped off the
 * render-blocking stylesheet on every marketing page.
 *
 * That trade only holds while blog.css's classes stay inside the blog templates. The failure
 * mode is silent and ugly: a new block or pattern uses `.rl-post-card` or `.rl-summary-box`,
 * looks right on the article it was copied from, and ships unstyled everywhere else. So the
 * set of views using blog.css classes is recomputed here from the files on disk and pinned.
 */
$theme = dirname(__DIR__, 2);

/**
 * Class names a stylesheet defines, comments stripped so a class only mentioned in prose
 * does not count. Restricted to the `rl-` prefix: blog.css also touches generic names
 * (`entry-content`, `wp-block-table`, `space-y-4`) that say nothing about ownership.
 *
 * @return array<int, string>
 */
$definedClasses = static function (string $path): array {
    $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path)) ?? '';

    preg_match_all('/\.(rl-[a-zA-Z0-9_-]+)/', $css, $matches);

    return array_values(array_unique($matches[1] ?? []));
};

it('keeps blog.css classes inside the views BlogStyles loads it for', function () use ($theme, $definedClasses) {
    $blogClasses = $definedClasses($theme.'/resources/css/blog.css');

    expect($blogClasses)->not->toBeEmpty();

    // A class defined in both stylesheets is not evidence of a blog dependency — app.css
    // would style it anyway. None overlap today; this keeps that from becoming a false alarm.
    $shared = array_intersect($blogClasses, $definedClasses($theme.'/resources/css/app.css'));
    $exclusive = array_values(array_diff($blogClasses, $shared));

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($theme.'/resources/views', FilesystemIterator::SKIP_DOTS)
    );

    $using = [];

    foreach ($views as $view) {
        if ($view->getExtension() !== 'php') {
            continue;
        }

        $markup = (string) file_get_contents($view->getPathname());

        foreach ($exclusive as $class) {
            // Whole-token match. Without the boundaries `rl-talent-carousel` matches
            // `rl-talent-carousel-track`, which is an app.css class on the ACF block and
            // has nothing to do with blog.css.
            if (preg_match('/(?<![\w-])'.preg_quote($class, '/').'(?![\w-])/', $markup) === 1) {
                $using[] = str_replace($theme.'/resources/views/', '', $view->getPathname());
                break;
            }
        }
    }

    sort($using);
    $expected = BlogStyles::BLOG_STYLED_VIEWS;
    sort($expected);

    expect($using)->toBe(
        $expected,
        'A view started using (or stopped using) blog.css classes. blog.css is only emitted on '
        .'blog surfaces — see App\Support\BlogStyles::isNeeded() — so a view outside that set '
        .'will render unstyled. Either style it from app.css, or widen isNeeded() and update '
        .'BLOG_STYLED_VIEWS.'
    );
});

it('no longer imports blog.css into app.css', function () use ($theme) {
    $appCss = (string) file_get_contents($theme.'/resources/css/app.css');

    // Stripping comments matters: the note left where the import used to be names the file.
    $withoutComments = preg_replace('#/\*.*?\*/#s', '', $appCss) ?? '';

    expect($withoutComments)->not->toContain('blog.css');
});

it('registers blog.css as its own Vite entry', function () use ($theme) {
    expect((string) file_get_contents($theme.'/vite.config.js'))
        ->toContain("'resources/css/blog.css'");
});

it('puts blog.css before app.css so the cascade does not move', function () {
    $entries = (new ReflectionMethod(BlogStyles::class, 'entryPoints'));

    expect($entries->isStatic())->toBeTrue();

    // blog.css carries no @layer, and so do app.css's own hand-written rules; between two
    // unlayered rules of equal specificity source order decides. As an @import at the top of
    // app.css, blog.css lost those ties, and it has to keep losing them.
    $source = (string) file_get_contents(
        dirname(__DIR__, 2).'/app/Support/BlogStyles.php'
    );

    $blogAt = strpos($source, "'resources/css/blog.css'");
    $appAt = strpos($source, "'resources/css/app.css'");

    expect($blogAt)->not->toBeFalse()
        ->and($appAt)->not->toBeFalse()
        ->and($blogAt)->toBeLessThan($appAt);
});
