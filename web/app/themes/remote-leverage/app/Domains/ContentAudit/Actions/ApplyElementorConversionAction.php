<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Actions;

class ApplyElementorConversionAction
{
    public function __construct(
        protected ConvertElementorPostAction $convertAction
    ) {}

    /**
     * Convert a single post's `_elementor_data` into native Gutenberg markup and persist it,
     * queuing the post for mandatory human editorial review (ADR-0005 Amendment).
     *
     * @return array{post_id: int, status: string, unmapped_widgets: array<string>, dry_run: bool}
     */
    public function execute(int $postId, bool $dryRun = false): array
    {
        $elementorData = get_post_meta($postId, '_elementor_data', true);

        if (empty($elementorData)) {
            return [
                'post_id' => $postId,
                'status' => 'skipped_no_elementor_data',
                'unmapped_widgets' => [],
                'dry_run' => $dryRun,
            ];
        }

        $conversion = $this->convertAction->execute($elementorData);

        if ($conversion['status'] === 'clean_no_conversion_needed') {
            return [
                'post_id' => $postId,
                'status' => 'clean_no_conversion_needed',
                'unmapped_widgets' => [],
                'dry_run' => $dryRun,
            ];
        }

        $unmappedWidgets = array_keys($conversion['audit']['unmapped_widgets'] ?? []);

        if (! $dryRun) {
            wp_update_post([
                'ID' => $postId,
                'post_content' => $conversion['gutenberg_content'],
            ]);

            update_post_meta($postId, '_rl_conversion_status', 'needs_review');
            update_post_meta($postId, '_rl_conversion_unmapped_widgets', $unmappedWidgets);
            update_post_meta($postId, '_rl_conversion_audit', $conversion['audit']);
        }

        return [
            'post_id' => $postId,
            'status' => 'needs_review',
            'unmapped_widgets' => $unmappedWidgets,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Mark a converted post as human-approved and archive its legacy Elementor data,
     * per ADR-0005 Amendment's mandatory editorial sign-off gate.
     */
    public function approve(int $postId): void
    {
        $elementorData = get_post_meta($postId, '_elementor_data', true);

        if (! empty($elementorData)) {
            update_post_meta($postId, '_elementor_data_archived', $elementorData);
            delete_post_meta($postId, '_elementor_data');
        }

        update_post_meta($postId, '_rl_conversion_status', 'approved');
    }
}
