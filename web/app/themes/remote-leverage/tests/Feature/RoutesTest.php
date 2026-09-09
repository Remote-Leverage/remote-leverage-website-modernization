<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

describe('Application Routes', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::middleware([])->group($baseDir.'/routes/web.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    test('api routes are registered', function () {
        expect(Route::has('api.webhooks.stripe'))->toBeTrue()
            ->and(Route::has('api.webhooks.calendly'))->toBeTrue()
            ->and(Route::has('api.health'))->toBeTrue();
    });

    test('web routes are registered for standalone funnels', function () {
        expect(Route::has('funnel.book-consultation'))->toBeTrue()
            ->and(Route::has('live-call.connect'))->toBeTrue()
            ->and(Route::has('referrer.portal'))->toBeTrue()
            ->and(Route::has('referrer.register'))->toBeTrue()
            ->and(Route::has('referrer.dashboard.legacy'))->toBeTrue()
            ->and(Route::has('tools.signature-generator'))->toBeTrue();
    });

    test('health check route returns healthy json response', function () {
        $request = Request::create('/api/health', 'GET');
        $response = Route::dispatch($request);

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode((string) $response->getContent(), true);
        expect($payload)->toHaveKey('status', 'healthy')
            ->and($payload)->toHaveKey('timestamp');
    });
});
