<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domains\ContentAudit\Actions\ApplyElementorConversionAction;
use App\Domains\ContentAudit\Actions\AuditMarkdownContentAction;
use App\Domains\ContentAudit\Actions\ConvertElementorPostAction;
use App\Domains\ContentAudit\Actions\GenerateSignatureHtmlAction;
use App\Domains\ContentAudit\Commands\AuditElementorCommand;
use App\Domains\ContentAudit\Commands\ConvertElementorCommand;
use App\Domains\ContentAudit\Commands\ImportBlogPostsCommand;
use App\Domains\ContentAudit\Services\ElementorAuditService;
use App\Domains\ContentAudit\Services\PrismAiAuditor;
use Illuminate\Support\ServiceProvider;

class ContentAuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PrismAiAuditor::class, fn () => new PrismAiAuditor);
        $this->app->singleton(ElementorAuditService::class, fn () => new ElementorAuditService);
        $this->app->singleton(ConvertElementorPostAction::class);
        $this->app->singleton(ApplyElementorConversionAction::class);
        $this->app->singleton(AuditMarkdownContentAction::class);
        $this->app->singleton(GenerateSignatureHtmlAction::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditElementorCommand::class,
                ConvertElementorCommand::class,
                ImportBlogPostsCommand::class,
            ]);
        }
    }
}
