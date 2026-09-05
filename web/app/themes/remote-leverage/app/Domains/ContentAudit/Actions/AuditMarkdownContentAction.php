<?php

declare(strict_types=1);

namespace App\Domains\ContentAudit\Actions;

use App\Domains\ContentAudit\Services\PrismAiAuditor;

class AuditMarkdownContentAction
{
    public function __construct(
        protected PrismAiAuditor $auditor
    ) {}

    /**
     * Audit markdown content for SEO, formatting, and completeness.
     */
    public function execute(string $content): array
    {
        return $this->auditor->auditContent($content);
    }
}
