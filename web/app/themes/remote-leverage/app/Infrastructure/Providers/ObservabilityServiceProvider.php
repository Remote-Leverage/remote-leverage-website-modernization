<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Infrastructure\Observability\CredentialRegistry;
use App\Infrastructure\Observability\IntegrationCall;
use App\Infrastructure\Observability\IntegrationCallRecorder;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires integration-call recording into both HTTP stacks this theme uses.
 *
 * Laravel's client covers Calendly, HubSpot, Slack, Stripe and Google. The outgoing lead webhook
 * goes through WordPress' HTTP API instead, which dispatches its own action, so both are hooked
 * here rather than leaving the webhook — the one integration an operator configures themselves,
 * and therefore the one most likely to be misconfigured — as the only blind spot.
 */
class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CredentialRegistry::class, fn () => new CredentialRegistry);

        $this->app->singleton(
            IntegrationCallRecorder::class,
            fn ($app) => new IntegrationCallRecorder($app->make(CredentialRegistry::class)),
        );
    }

    public function boot(): void
    {
        if (! config('observability.integration_calls.enabled', true)) {
            return;
        }

        $this->listenToLaravelHttpClient();
        $this->listenToWordPressHttpApi();
        $this->schedulePruning();
    }

    /**
     * Prune daily.
     *
     * Scheduled rather than left to a deploy task because retention is a data-protection
     * commitment, and one that only holds if it runs on a site nobody has deployed to in a month.
     */
    protected function schedulePruning(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        \add_action('init', function () {
            if (! \wp_next_scheduled('rl_prune_integration_calls')) {
                \wp_schedule_event(time(), 'daily', 'rl_prune_integration_calls');
            }
        });

        \add_action('rl_prune_integration_calls', function () {
            $days = (int) config('observability.integration_calls.retention_days', 30);

            if ($days < 1) {
                return;
            }

            try {
                IntegrationCall::query()->where('created_at', '<', now()->subDays($days))->limit(5000)->delete();
            } catch (\Throwable $e) {
                Log::warning('IntegrationCall pruning failed: '.$e->getMessage());
            }
        });
    }

    /**
     * Record every call made through `Http::`.
     */
    protected function listenToLaravelHttpClient(): void
    {
        Event::listen(RequestSending::class, function (RequestSending $event) {
            $this->recorder()->markSent($event->request->method(), $event->request->url());
        });

        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $recorder = $this->recorder();

            $recorder->record(
                method: $event->request->method(),
                url: $event->request->url(),
                headers: $event->request->headers(),
                body: $event->request->body(),
                statusCode: $event->response->status(),
                responseHeaders: $event->response->headers(),
                responseBody: $event->response->body(),
                durationMs: $recorder->elapsed($event->request->method(), $event->request->url()),
            );
        });

        /*
         * A connection that never completed. Worth recording precisely because there is no
         * response to inspect anywhere else — a Calendly timeout otherwise appears in the
         * activity log as a booking that simply did not happen, with no indication why.
         */
        Event::listen(ConnectionFailed::class, function (ConnectionFailed $event) {
            $recorder = $this->recorder();

            $recorder->record(
                method: $event->request->method(),
                url: $event->request->url(),
                headers: $event->request->headers(),
                body: $event->request->body(),
                durationMs: $recorder->elapsed($event->request->method(), $event->request->url()),
                errorMessage: $this->connectionError($event),
            );
        });
    }

    /**
     * Record calls made through `wp_remote_*`.
     *
     * `http_api_debug` fires after every WordPress HTTP request with the parsed arguments and
     * the raw response, which is everything needed. The hook is deliberately narrow: only the
     * outgoing webhook is recorded, because WordPress itself calls out to api.wordpress.org for
     * update checks on a schedule and those would swamp the table.
     */
    protected function listenToWordPressHttpApi(): void
    {
        if (! config('observability.integration_calls.record_wp_http', true)) {
            return;
        }

        if (! function_exists('add_action')) {
            return;
        }

        \add_action('http_api_debug', function ($response, $context, $class, $parsedArgs, $url) {
            if (! $this->isConfiguredWebhook((string) $url)) {
                return;
            }

            $isError = function_exists('is_wp_error') && \is_wp_error($response);

            $this->recorder()->record(
                method: (string) ($parsedArgs['method'] ?? 'POST'),
                url: (string) $url,
                headers: (array) ($parsedArgs['headers'] ?? []),
                body: is_string($parsedArgs['body'] ?? null) ? $parsedArgs['body'] : null,
                statusCode: $isError ? null : (int) \wp_remote_retrieve_response_code($response),
                responseHeaders: $isError ? [] : $this->wpResponseHeaders($response),
                responseBody: $isError ? null : (string) \wp_remote_retrieve_body($response),
                errorMessage: $isError ? (string) $response->get_error_message() : null,
            );
        }, 10, 5);
    }

    /**
     * Whether a WordPress HTTP call is the operator-configured lead webhook.
     *
     * Compared by host and path rather than exact string so a query string or a trailing slash
     * does not silently stop the recording.
     */
    protected function isConfiguredWebhook(string $url): bool
    {
        $configured = (string) config('services.webhooks.lead_webhook_url');

        if ($configured === '' && function_exists('get_option')) {
            $configured = (string) (\get_option('rl_lead_webhook_url') ?: \get_option('rl_custom_webhook_url'));
        }

        if ($configured === '') {
            return false;
        }

        $a = parse_url($url);
        $b = parse_url($configured);

        return ($a['host'] ?? null) === ($b['host'] ?? false)
            && rtrim((string) ($a['path'] ?? ''), '/') === rtrim((string) ($b['path'] ?? ''), '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function wpResponseHeaders(mixed $response): array
    {
        $headers = $response['headers'] ?? null;

        if (is_object($headers) && method_exists($headers, 'getAll')) {
            return (array) $headers->getAll();
        }

        return is_array($headers) ? $headers : [];
    }

    /**
     * The exception message, on Laravel versions that expose one.
     */
    protected function connectionError(ConnectionFailed $event): string
    {
        return property_exists($event, 'exception') && $event->exception
            ? $event->exception->getMessage()
            : 'Connection failed';
    }

    protected function recorder(): IntegrationCallRecorder
    {
        return $this->app->make(IntegrationCallRecorder::class);
    }
}
