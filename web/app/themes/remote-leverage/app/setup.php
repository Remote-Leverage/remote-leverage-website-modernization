<?php

/**
 * Theme setup.
 */

namespace App;

use Illuminate\Support\Facades\Vite;

/**
 * Inject styles into the block editor.
 *
 * @return array
 */
add_filter('block_editor_settings_all', function ($settings) {
    $style = Vite::asset('resources/css/editor.css');

    $settings['styles'][] = [
        'css' => "@import url('{$style}')",
    ];

    return $settings;
});

/**
 * Inject scripts into the block editor.
 *
 * @return void
 */
add_action('admin_head', function () {
    if (! get_current_screen()?->is_block_editor()) {
        return;
    }

    if (! Vite::isRunningHot()) {
        $dependencies = json_decode(Vite::content('editor.deps.json'));

        foreach ($dependencies as $dependency) {
            if (! wp_script_is($dependency)) {
                wp_enqueue_script($dependency);
            }
        }
    }
    echo Vite::withEntryPoints([
        'resources/js/editor.js',
    ])->toHtml();
});

/**
 * Use the generated theme.json file.
 *
 * @return string
 */
add_filter('theme_file_path', function ($path, $file) {
    return $file === 'theme.json'
        ? public_path('build/assets/theme.json')
        : $path;
}, 10, 2);

/**
 * Disable on-demand block asset loading.
 *
 * @link https://core.trac.wordpress.org/ticket/61965
 */
add_filter('should_load_separate_core_block_assets', '__return_false');

/**
 * Register the initial theme setup.
 *
 * @return void
 */
add_action('after_setup_theme', function () {
    /**
     * Disable full-site editing support.
     *
     * @link https://wptavern.com/gutenberg-10-5-embeds-pdfs-adds-verse-block-color-options-and-introduces-new-patterns
     */
    remove_theme_support('block-templates');

    /**
     * Register the navigation menus.
     *
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'sage'),
    ]);

    /**
     * Disable the default block patterns.
     *
     * @link https://developer.wordpress.org/block-editor/developers/themes/theme-support/#disabling-the-default-block-patterns
     */
    remove_theme_support('core-block-patterns');

    /**
     * Enable plugins to manage the document title.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Enable post thumbnail support.
     *
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable responsive embed support.
     *
     * @link https://developer.wordpress.org/block-editor/how-to-guides/themes/theme-support/#responsive-embedded-content
     */
    add_theme_support('responsive-embeds');

    /**
     * Enable HTML5 markup support.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', [
        'caption',
        'comment-form',
        'comment-list',
        'gallery',
        'search-form',
        'script',
        'style',
    ]);

    /**
     * Enable selective refresh for widgets in customizer.
     *
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#customize-selective-refresh-widgets
     */
    add_theme_support('customize-selective-refresh-widgets');
}, 20);

/**
 * Register the theme sidebars.
 *
 * @return void
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];

    register_sidebar([
        'name' => __('Primary', 'sage'),
        'id' => 'sidebar-primary',
    ] + $config);

    register_sidebar([
        'name' => __('Footer', 'sage'),
        'id' => 'sidebar-footer',
    ] + $config);
});

/**
 * Ensure the site is indexable and robots allow indexing for Lighthouse audit.
 */
add_filter('pre_option_blog_public', function () {
    return '1';
}, PHP_INT_MAX);

add_filter('wp_robots', function (array $robots) {
    unset($robots['noindex'], $robots['nofollow']);
    $robots['index'] = true;
    $robots['follow'] = true;
    $robots['max-image-preview'] = 'large';
    $robots['max-snippet'] = '-1';
    $robots['max-video-preview'] = '-1';
    return $robots;
}, PHP_INT_MAX);

/**
 * Dequeue Gutenberg block library and classic styles on frontend since we use Tailwind.
 */
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
}, 100);

/**
 * Enforce HTTPS redirect for Best Practices audit and security.
 */
add_action('template_redirect', function () {
    $isHttps = is_ssl()
        || (isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS']))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']))
        || (isset($_SERVER['SERVER_PORT']) && '443' == $_SERVER['SERVER_PORT']);

    if (! $isHttps && isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
        wp_safe_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
        exit;
    }
}, 1);

/**
 * Register custom Gutenberg block styles and pattern categories.
 */
add_action('init', function () {
    // Register Remote Leverage Pattern Category
    register_block_pattern_category('remote-leverage', [
        'label' => __('Remote Leverage', 'remote-leverage'),
    ]);

    // Register theme block patterns from patterns/ directory
    $patternFiles = glob(get_theme_file_path('patterns/*.php'));
    if (!empty($patternFiles)) {
        foreach ($patternFiles as $patternFile) {
            $headers = get_file_data($patternFile, [
                'title' => 'Title',
                'slug' => 'Slug',
                'categories' => 'Categories',
                'description' => 'Description',
            ]);
            if (!empty($headers['slug']) && !empty($headers['title'])) {
                ob_start();
                include $patternFile;
                $patternContent = ob_get_clean();

                register_block_pattern($headers['slug'], [
                    'title' => $headers['title'],
                    'content' => $patternContent,
                    'categories' => !empty($headers['categories']) ? array_map('trim', explode(',', $headers['categories'])) : ['remote-leverage'],
                    'description' => $headers['description'] ?? '',
                ]);
            }
        }
    }

    // Register Button Block Styles
    register_block_style('core/button', [
        'name' => 'pill-purple',
        'label' => __('Pill Purple (Primary)', 'remote-leverage'),
        'is_default' => true,
    ]);

    register_block_style('core/button', [
        'name' => 'pill-outline',
        'label' => __('Pill Outline (Consultation)', 'remote-leverage'),
    ]);

    register_block_style('core/button', [
        'name' => 'pill-black',
        'label' => __('Pill Black', 'remote-leverage'),
    ]);

    // Register Remote Leverage Smart Blocks with server render callbacks
    $smartBlocks = [
        'talent-marquee' => [
            'class' => \App\Blocks\TalentMarqueeBlock::class,
            'view' => 'blocks.talent-marquee',
        ],
        'client-logos-marquee' => [
            'class' => \App\Blocks\ClientLogosMarqueeBlock::class,
            'view' => 'blocks.client-logos-marquee',
        ],
        'trust-stats' => [
            'class' => \App\Blocks\TrustStatsBlock::class,
            'view' => 'blocks.trust-stats',
        ],
        'department-cards' => [
            'class' => \App\Blocks\DepartmentCardsBlock::class,
            'view' => 'blocks.department-cards',
        ],
        'data-table' => [
            'class' => \App\Blocks\DataTableBlock::class,
            'view' => 'blocks.data-table',
        ],
        'process-steps' => [
            'class' => \App\Blocks\ProcessStepsBlock::class,
            'view' => 'blocks.process-steps',
        ],
        'testimonials' => [
            'class' => \App\Blocks\TestimonialsBlock::class,
            'view' => 'blocks.testimonials',
        ],
        'accordion-faq' => [
            'class' => \App\Blocks\AccordionFaqBlock::class,
            'view' => 'blocks.accordion-faq',
        ],
        'booking' => [
            'class' => \App\Blocks\BookingBlock::class,
            'view' => 'blocks.booking',
        ],
        'feature-cards' => [
            'class' => \App\Blocks\FeatureCardsBlock::class,
            'view' => 'blocks.feature-cards',
        ],
        'roles-grid' => [
            'class' => \App\Blocks\RolesGridBlock::class,
            'view' => 'blocks.roles-grid',
        ],
        'hire-va-hero' => [
            'class' => \App\Blocks\HireVaHeroBlock::class,
            'view' => 'blocks.hire-va-hero',
        ],
        'why-hire' => [
            'class' => \App\Blocks\WhyHireBlock::class,
            'view' => 'blocks.why-hire',
        ],
        'guarantee-card' => [
            'class' => \App\Blocks\GuaranteeCardBlock::class,
            'view' => 'blocks.guarantee-card',
        ],
        'comparison-matrix' => [
            'class' => \App\Blocks\ComparisonMatrixBlock::class,
            'view' => 'blocks.comparison-matrix',
        ],
        'booking-footer' => [
            'class' => \App\Blocks\BookingFooterBlock::class,
            'view' => 'blocks.booking-footer',
        ],
    ];

    foreach ($smartBlocks as $slug => $config) {
        $renderCallback = function ($attributes = [], $content = '') use ($config) {
            $data = [];
            if (isset($config['class']) && class_exists($config['class'])) {
                try {
                    $instance = app($config['class']);
                    if (method_exists($instance, 'with')) {
                        $data = $instance->with();
                    }
                    if (isset($attributes['data']['columns']) && method_exists($instance, 'cards')) {
                        $data['columns'] = (string) $attributes['data']['columns'];
                        $data['cards'] = $instance->cards($data['columns']);
                    }
                } catch (\Throwable $e) {
                    // Fallback
                }
            }
            if (! empty($attributes['data']) && is_array($attributes['data'])) {
                $data = array_merge($data, $attributes['data']);
            }

            return view($config['view'], $data)->render();
        };

        if (! \WP_Block_Type_Registry::get_instance()->is_registered("remote-leverage/{$slug}")) {
            register_block_type("remote-leverage/{$slug}", [
                'render_callback' => $renderCallback,
                'category' => 'remote-leverage',
            ]);
        }
        if (! \WP_Block_Type_Registry::get_instance()->is_registered("acf/{$slug}")) {
            register_block_type("acf/{$slug}", [
                'render_callback' => $renderCallback,
                'category' => 'remote-leverage',
            ]);
        }
    }
});



