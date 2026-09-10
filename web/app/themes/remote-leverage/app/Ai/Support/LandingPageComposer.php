<?php

declare(strict_types=1);

namespace App\Ai\Support;

use InvalidArgumentException;
use RuntimeException;
use WP_Block_Patterns_Registry;

/**
 * Composes landing pages from already-registered "remote-leverage/*" block
 * patterns (patterns/*.php), the same building blocks used when hand-authoring
 * a page per docs/page-migration-and-design-system-workflow.md.
 */
class LandingPageComposer
{
    public const PATTERN_PREFIX = 'remote-leverage/';

    /**
     * @return array<int, array{slug: string, title: string, categories: array<int, string>}>
     */
    public function listPatterns(): array
    {
        $patterns = [];

        foreach (WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern) {
            if (! str_starts_with($pattern['name'], self::PATTERN_PREFIX)) {
                continue;
            }

            $patterns[] = [
                'slug' => $pattern['name'],
                'title' => $pattern['title'] ?? $pattern['name'],
                'categories' => $pattern['categories'] ?? [],
            ];
        }

        return $patterns;
    }

    /**
     * Concatenate the registered content of each pattern slug, in order.
     *
     * @param  array<int, string>  $patternSlugs
     */
    public function composeContent(array $patternSlugs): string
    {
        $registry = WP_Block_Patterns_Registry::get_instance();
        $content = '';

        foreach ($patternSlugs as $slug) {
            $pattern = $registry->get_registered($slug);

            if ($pattern === null) {
                throw new InvalidArgumentException("Unknown pattern slug: {$slug}");
            }

            $content .= $pattern['content']."\n";
        }

        return $content;
    }

    /**
     * Per docs/page-migration-and-design-system-workflow.md § 8: wp_insert_post
     * runs wp_unslash() on post_content, so unicode-escaped block JSON must be
     * wp_slash()'d first or backslashes get stripped and corrupt the markup.
     */
    public function createPage(string $title, string $slug, string $content, string $status): int
    {
        $postId = wp_insert_post([
            'post_type' => 'page',
            'post_title' => $title,
            'post_name' => $slug,
            'post_status' => $status,
            'post_content' => wp_slash($content),
        ], true);

        if (is_wp_error($postId)) {
            throw new RuntimeException($postId->get_error_message());
        }

        return $postId;
    }

    public function updatePage(int $postId, string $content): void
    {
        $result = wp_update_post([
            'ID' => $postId,
            'post_content' => wp_slash($content),
        ], true);

        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }

    /**
     * Mirrors the parse_blocks() orphan-HTML audit from
     * docs/page-migration-and-design-system-workflow.md § 9: any block with a
     * null blockName but non-whitespace innerHTML means raw HTML leaked outside
     * a block comment, which triggers Gutenberg's "unexpected or invalid
     * content" recovery modal.
     *
     * @return array{clean: bool, issues: array<int, string>}
     */
    public function auditBlocks(string $content): array
    {
        $issues = [];
        $this->auditBlockList(parse_blocks($content), $issues);

        return [
            'clean' => $issues === [],
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>  $issues
     */
    private function auditBlockList(array $blocks, array &$issues): void
    {
        foreach ($blocks as $block) {
            if (($block['blockName'] ?? null) === null && trim($block['innerHTML'] ?? '') !== '') {
                $issues[] = trim($block['innerHTML']);
            }

            if (! empty($block['innerBlocks'])) {
                $this->auditBlockList($block['innerBlocks'], $issues);
            }
        }
    }
}
