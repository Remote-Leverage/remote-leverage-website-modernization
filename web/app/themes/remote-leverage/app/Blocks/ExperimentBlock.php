<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

/**
 * One arm of an A/B test, wrapping ordinary blocks.
 *
 * A test is composed by placing two of these in a pattern with the same flag key and different
 * variant names — not by forking a block. That is what keeps an experiment from turning into the
 * permanent second copy of a section that the legacy site accumulated 43 pages of.
 *
 * Every arm renders into the same cached HTML and the browser hides all but one, because logged-out
 * HTML is FastCGI-cached and served to CloudFront with `s-maxage=60` and no invalidation: a variant
 * chosen in PHP would be handed to every visitor who hit that cache entry. See `docs/ab-testing.md`.
 *
 * **This is the only block in the theme that uses InnerBlocks.** `supports.jsx` is what enables the
 * `<InnerBlocks />` tag in the view.
 */
class ExperimentBlock extends Block
{
    public $name = 'A/B Experiment Variant';

    public $slug = 'experiment';

    public $description = 'One arm of a PostHog A/B test. Place two with the same flag key and different variant names, wrapping whichever blocks each arm should show. Exactly one arm per flag is the default: it renders for everyone PostHog cannot decide for, including visitors with JavaScript off or an ad blocker.';

    public $category = 'remote-leverage';

    public $icon = 'randomize';

    public $keywords = ['experiment', 'ab test', 'variant', 'split test', 'feature flag', 'posthog'];

    public $view = 'blocks.experiment';

    public $mode = 'preview';

    public $example = [
        'attributes' => [
            'mode' => 'preview',
            'data' => [
                'flag' => 'example-experiment',
                'variant' => 'control',
                'is_default' => true,
                'is_preview' => true,
            ],
        ],
    ];

    /** `jsx` is what makes `<InnerBlocks />` work. */
    public $supports = [
        'jsx' => true,
        'align' => false,
        'anchor' => false,
    ];

    public function with(): array
    {
        return [
            'flag' => self::sanitiseKey(function_exists('get_field') ? get_field('flag') : null),
            'variant' => self::sanitiseKey(function_exists('get_field') ? get_field('variant') : null),
            'isDefault' => (bool) (function_exists('get_field') ? get_field('is_default') : false),
            'isEditor' => $this->isEditor(),
        ];
    }

    /**
     * Whether this render is the editor rather than the front end.
     *
     * Both checks are needed: `$this->preview` covers the editor canvas and the REST block
     * renderer, `is_admin()` covers an admin-side render where ACF did not set it. An experiment
     * that hid an arm in wp-admin would be a page an editor cannot edit.
     */
    protected function isEditor(): bool
    {
        return (bool) $this->preview || (function_exists('is_admin') && is_admin());
    }

    /**
     * Reduce a key to what is safe in an HTML attribute and in the inline `resolve()` call.
     *
     * The flag key reaches a `<script>` tag, so this is a security boundary and not tidiness.
     * PostHog's own keys are lowercase with dashes or underscores, so nothing legitimate is lost.
     */
    public static function sanitiseKey(mixed $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($value ?? ''));
    }

    public function fields(): array
    {
        $fields = Builder::make('experiment_block');

        $fields
            ->addText('flag', [
                'label' => 'Feature flag key',
                'instructions' => 'The PostHog feature flag or experiment key, exactly as it appears in PostHog. Letters, numbers, dashes and underscores only. Every arm of the same test uses the same key.',
                'required' => 1,
            ])
            ->addText('variant', [
                'label' => 'Variant name',
                'instructions' => 'Which arm this is. For a boolean flag (on/off) use <code>control</code> and <code>test</code> — PostHog answers those with false and true. For a multivariate experiment, use the variant keys from PostHog verbatim.',
                'required' => 1,
                'default_value' => 'control',
            ])
            ->addTrueFalse('is_default', [
                'label' => 'This is the default arm',
                'instructions' => 'Renders for anyone PostHog cannot decide for: JavaScript off, an ad blocker, a slow or failed flag request, or a variant name PostHog returns that this page does not ship. Mark exactly one arm per flag key. It is also the only arm that renders before the decision, so make it the safe one.',
                'ui' => 1,
                'default_value' => 0,
            ]);

        return $fields->build();
    }
}
