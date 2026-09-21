<?php

declare(strict_types=1);

namespace App\Domains\Tools\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use WP_Error;

/**
 * The outbound half of the legacy tool proxy: sanitised payload in, OpenAI's JSON out.
 *
 * Deliberately thin. The browser composes every prompt these tools send — that is what the
 * legacy widgets do and rewriting them was not in scope — so this class adds the credential,
 * the timeouts and nothing else. {@see OpenAiProxyGuard} is what decides a payload is
 * allowed to get this far.
 */
class OpenAiProxy
{
    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(
        protected ?OpenAiProxyGuard $guard = null,
        ?array $config = null,
    ) {
        $this->guard ??= new OpenAiProxyGuard;
        $this->config = $config ?? (array) config('job-widget', []);
    }

    /**
     * @param  array<string, mixed>  $body  Already through OpenAiProxyGuard::sanitizeChatPayload().
     * @return array<string, mixed>|WP_Error
     */
    public function chat(array $body): array|WP_Error
    {
        return $this->send(
            fn () => $this->client((int) ($this->config['timeout'] ?? 45))
                ->post($this->url('/chat/completions'), $body),
            'chat',
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function transcribe(string $audio, string $filename): array|WP_Error
    {
        $result = $this->send(
            fn () => $this->client((int) ($this->config['transcription_timeout'] ?? 90))
                ->attach('file', $audio, $filename)
                ->post($this->url('/audio/transcriptions'), [
                    'model' => (string) ($this->config['transcription_model'] ?? 'whisper-1'),
                    'language' => 'en',
                ]),
            'whisper',
        );

        if ($result instanceof WP_Error) {
            return $result;
        }

        $text = trim((string) ($result['text'] ?? ''));

        if ($text === '') {
            return $this->failure('jobwidget_no_transcript', 'No transcription was returned.');
        }

        /*
         * The legacy route answered `{text, success}` rather than OpenAI's own body, and the
         * Audio Scorer widget reads exactly those two keys. Kept as-is: the widget is the
         * contract here, not the upstream API.
         */
        return ['text' => $text, 'success' => true];
    }

    /**
     * @param  callable(): Response  $call
     * @return array<string, mixed>|WP_Error
     */
    protected function send(callable $call, string $route): array|WP_Error
    {
        try {
            $response = $call();
        } catch (Throwable $e) {
            /*
             * The message can carry the request, and the request carries the Authorization
             * header, so only the class and the route are logged. A key in a log file is a key
             * in whatever ships that log file onwards.
             */
            Log::error('Job widget proxy request failed', [
                'route' => $route,
                'exception' => $e::class,
            ]);

            return $this->failure('jobwidget_unreachable', 'The service is temporarily unavailable.');
        }

        if (! $response->successful()) {
            Log::error('Job widget proxy upstream error', [
                'route' => $route,
                'status' => $response->status(),
            ]);

            /*
             * Upstream's status is not returned to the browser. A 401 here means *our* key is
             * wrong, which is not the visitor's problem and not something to tell an
             * unauthenticated caller — the same reasoning that keeps the body out of the log
             * line above. 502 says what is true from the browser's side: the thing behind us
             * failed.
             */
            return $this->failure('jobwidget_upstream_error', 'The service returned an error. Please try again.', 502);
        }

        $json = $response->json();

        if (! is_array($json)) {
            Log::error('Job widget proxy returned non-JSON', ['route' => $route]);

            return $this->failure('jobwidget_bad_json', 'The service returned an unreadable response.');
        }

        return $json;
    }

    protected function client(int $timeout): PendingRequest
    {
        return Http::withToken($this->guard->apiKey())
            ->timeout($timeout)
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5));
    }

    protected function url(string $path): string
    {
        return rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/').$path;
    }

    protected function failure(string $code, string $message, int $status = 502): WP_Error
    {
        return new WP_Error($code, $message, ['status' => $status]);
    }
}
