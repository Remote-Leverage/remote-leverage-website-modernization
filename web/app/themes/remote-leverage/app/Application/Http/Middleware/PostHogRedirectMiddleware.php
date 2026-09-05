<?php

declare(strict_types=1);

namespace App\Application\Http\Middleware;

use App\Domains\Tracking\Actions\EvaluateVariantAction;
use Closure;
use Illuminate\Http\Request;

class PostHogRedirectMiddleware
{
    public function __construct(
        protected EvaluateVariantAction $evaluateVariant
    ) {}

    /**
     * Redirect traffic based on feature flag evaluation.
     */
    public function handle(Request $request, Closure $next, ?string $flag = null, ?string $targetPath = null)
    {
        if ($flag && $targetPath && $this->evaluateVariant->execute($flag)) {
            return redirect($targetPath);
        }

        return $next($request);
    }
}
