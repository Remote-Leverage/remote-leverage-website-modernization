<?php

declare(strict_types=1);

namespace App\Ai\Abilities;

use App\Ai\Support\LandingPageComposer;
use Roots\AcornAi\Abilities\Ability;
use WP_Error;

class ListPatternsAbility extends Ability
{
    public function __construct(private LandingPageComposer $composer) {}

    public function label(): string
    {
        return 'List Landing Page Patterns';
    }

    public function description(): string
    {
        return 'Lists the reusable Remote Leverage block patterns (hero, testimonials, booking footer, etc.) '
            .'that a landing page can be composed from. Call this before create-landing-page or '
            .'update-landing-page-content to see valid pattern slugs.';
    }

    public function execute(array $input): mixed
    {
        return $this->composer->listPatterns();
    }

    public function permission(): bool|WP_Error
    {
        return current_user_can('edit_pages');
    }

    /**
     * Takes no parameters, but must still declare a non-empty schema: WP core's
     * WP_Ability::invoke_callback() only passes $input through to the
     * execute_callback when the input schema is non-empty, while acorn-ai's
     * wrapping closure (fn (array $input) => ...) always requires one
     * argument — an empty schema causes a zero-arg call and an
     * ArgumentCountError.
     */
    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function category(): ?string
    {
        return 'site';
    }

    public function outputSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ];
    }

    public function meta(): array
    {
        return ['mcp' => ['public' => true]];
    }
}
