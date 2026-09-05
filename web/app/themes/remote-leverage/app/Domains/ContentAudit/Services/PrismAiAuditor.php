<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Services;

class PrismAiAuditor
{
    /**
     * Perform static and AI heuristic analysis on Markdown / VA guide content.
     */
    public function auditContent(string $markdown): array
    {
        $wordCount = str_word_count(strip_tags($markdown));
        $headingCount = preg_match_all('/^#{1,6}\s+/m', $markdown);
        $hasSchemaKeywords = str_contains(strtolower($markdown), 'remote leverage') || str_contains(strtolower($markdown), 'virtual assistant');
        $readabilityScore = min(100, max(40, (int) round(100 - ($wordCount > 0 ? ($wordCount / 50) : 0))));

        $issues = [];
        if ($headingCount < 2) {
            $issues[] = 'Content lacks sufficient hierarchical headings (H2/H3).';
        }
        if ($wordCount < 300) {
            $issues[] = 'Guide length is below 300 words recommended minimum for SEO authority.';
        }
        if (! $hasSchemaKeywords) {
            $issues[] = 'Primary domain entity keywords are missing from the content.';
        }

        return [
            'word_count' => $wordCount,
            'heading_count' => $headingCount,
            'readability_score' => $readabilityScore,
            'status' => empty($issues) ? 'passed' : 'warning',
            'issues' => $issues,
        ];
    }
}
