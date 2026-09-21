<?php

declare(strict_types=1);

namespace App\Domains\Tools\Http;

use App\Domains\Tools\Services\OpenAiProxy;
use App\Domains\Tools\Services\OpenAiProxyGuard;
use WP_Error;
use WP_REST_Request;

/**
 * `/wp-json/jobwidget/v1/{chat,chat4,whisper}` — the routes the Elementor-era tool pages call.
 *
 * Ported from the legacy `hello-theme-child/functions.php`, which cutover deleted along with
 * the theme. Eight snapshot pages POST here (vastore5, vastore5-b, resume, text-optimizer,
 * job-description-generator, job-description-generator-2, job-posting-template-generator,
 * tools); until this existed they all rendered "(No output received)", because a
 * `rest_no_route` 404 body is valid JSON and so never reached the widgets' catch blocks.
 *
 * Nothing but wiring lives here: {@see OpenAiProxyGuard} decides, {@see OpenAiProxy} sends.
 */
class JobWidgetRestRoutes
{
    public const NAMESPACE = 'jobwidget/v1';

    public function __construct(
        protected OpenAiProxyGuard $guard,
        protected OpenAiProxy $proxy,
    ) {}

    /**
     * Register the routes, unless this environment has no key.
     *
     * The check is at `rest_api_init` rather than here: `config()` on a theme file that runs
     * before Acorn boots would be resolved too early, and an unconfigured environment should
     * 404 exactly as it does today rather than answer 503 on a route that cannot work.
     */
    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            if (! $this->guard->enabled()) {
                return;
            }

            foreach (['chat', 'chat4'] as $route) {
                register_rest_route(self::NAMESPACE, '/'.$route, [
                    'methods' => 'POST',
                    'callback' => fn (WP_REST_Request $request) => $this->handleChat($request),
                    'permission_callback' => fn (WP_REST_Request $request) => $this->authorize($request),
                ]);
            }

            register_rest_route(self::NAMESPACE, '/whisper', [
                'methods' => 'POST',
                'callback' => fn (WP_REST_Request $request) => $this->handleWhisper($request),
                'permission_callback' => fn (WP_REST_Request $request) => $this->authorize($request),
            ]);
        });
    }

    /**
     * @return true|WP_Error
     */
    public function authorize(WP_REST_Request $request): bool|WP_Error
    {
        $error = $this->guard->authorize(
            $request->get_header('x_wp_nonce'),
            $request->get_header('origin'),
            $this->guard->resolveClientIp($_SERVER),
        );

        return $error ?? true;
    }

    /**
     * @return \WP_REST_Response|WP_Error
     */
    public function handleChat(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();

        if (! is_array($payload)) {
            return new WP_Error('jobwidget_no_body', 'Missing request body.', ['status' => 400]);
        }

        $body = $this->guard->sanitizeChatPayload($payload);

        if ($body instanceof WP_Error) {
            return $body;
        }

        $result = $this->proxy->chat($body);

        return $result instanceof WP_Error ? $result : rest_ensure_response($result);
    }

    /**
     * @return \WP_REST_Response|WP_Error
     */
    public function handleWhisper(WP_REST_Request $request)
    {
        $payload = $request->get_json_params();

        if (! is_array($payload)) {
            return new WP_Error('jobwidget_no_body', 'Missing request body.', ['status' => 400]);
        }

        $audio = $this->guard->sanitizeTranscriptionPayload($payload);

        if ($audio instanceof WP_Error) {
            return $audio;
        }

        $result = $this->proxy->transcribe($audio['audio'], $audio['filename']);

        return $result instanceof WP_Error ? $result : rest_ensure_response($result);
    }
}
