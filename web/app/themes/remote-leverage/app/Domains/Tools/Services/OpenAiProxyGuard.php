<?php

declare(strict_types=1);

namespace App\Domains\Tools\Services;

use WP_Error;

/**
 * Everything that has to be true before a browser payload reaches OpenAI on our key.
 *
 * The legacy routes in `hello-theme-child/functions.php` had none of this: a
 * `permission_callback` of `__return_true`, the request body forwarded verbatim, and the key
 * hardcoded in the theme file. That is an open, unauthenticated proxy to the company OpenAI
 * account — any model, any prompt length, any number of calls, billed to us.
 *
 * Kept separate from {@see OpenAiProxy} so the rules are testable without a network or a
 * WordPress REST stack: everything here is decided from four inputs and the transient store.
 */
class OpenAiProxyGuard
{
    /** Transient key prefix for the per-IP counters. */
    protected const RATE_PREFIX = 'rl_jobwidget_rate_';

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('job-widget', []);
    }

    /**
     * Whether the routes should exist at all.
     *
     * An unset key is not an error state to report at request time — it means this environment
     * was never wired up, and a route that 404s says that more honestly than one that 500s.
     */
    public function enabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true
            && $this->apiKey() !== '';
    }

    public function apiKey(): string
    {
        return trim((string) ($this->config['api_key'] ?? ''));
    }

    /**
     * Gate one request. Null means allowed.
     *
     * Order is deliberate and matches EmailValidationService: the free local checks run before
     * anything that writes to the transient store, so a hotlinked request never consumes a
     * visitor's rate-limit budget.
     */
    public function authorize(?string $nonce, ?string $origin, ?string $clientIp): ?WP_Error
    {
        if (! $this->enabled()) {
            return new WP_Error(
                'jobwidget_disabled',
                'This tool is not available right now.',
                ['status' => 503]
            );
        }

        if (! $this->originAllowed($origin)) {
            return new WP_Error(
                'jobwidget_bad_origin',
                'This tool can only be used from remoteleverage.com.',
                ['status' => 403]
            );
        }

        if (($this->config['require_nonce'] ?? true) === true && ! $this->nonceValid($nonce)) {
            return new WP_Error(
                'jobwidget_bad_nonce',
                'This page has been open too long. Refresh and try again.',
                ['status' => 403]
            );
        }

        return $this->consumeRateLimit($clientIp);
    }

    /**
     * A same-origin browser request, or no Origin header at all.
     *
     * fetch() sends Origin on a same-origin POST in every current browser, so a mismatch is a
     * real signal. A *missing* one is not: curl omits it, and so does anything that is not a
     * browser — which the nonce and the rate limit are there to handle. Rejecting on absence
     * would buy nothing and break the one case nobody can debug.
     */
    public function originAllowed(?string $origin): bool
    {
        $origin = trim((string) $origin);

        if ($origin === '') {
            return true;
        }

        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url((string) home_url(), PHP_URL_HOST));

        if ($originHost === '' || $siteHost === '') {
            return true;
        }

        return $originHost === $siteHost;
    }

    public function nonceValid(?string $nonce): bool
    {
        $nonce = trim((string) $nonce);

        return $nonce !== '' && (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Count this request against the caller's budget, or refuse it.
     *
     * Transients rather than the Cache facade: this has to work on an environment that never
     * configured a cache store, and WordPress already routes transients through the object
     * cache when there is one. The read-then-write is not atomic, so two simultaneous requests
     * can share a slot — that matters for a lock and does not matter for a ceiling measured in
     * tens of requests per ten minutes.
     */
    public function consumeRateLimit(?string $clientIp): ?WP_Error
    {
        $limit = (int) ($this->config['rate_limit']['requests'] ?? 0);
        $window = (int) ($this->config['rate_limit']['window'] ?? 600);

        if ($limit <= 0 || $window <= 0) {
            return null;
        }

        /*
         * An unresolvable IP falls into one shared bucket rather than being waved through.
         * Unlimited is never the safe side of this particular question.
         */
        $bucket = self::RATE_PREFIX.md5(trim((string) $clientIp) ?: 'unknown');
        $used = (int) get_transient($bucket);

        if ($used >= $limit) {
            return new WP_Error(
                'jobwidget_rate_limited',
                'Too many requests. Please wait a few minutes and try again.',
                ['status' => 429]
            );
        }

        /*
         * The TTL is reset on every write, so the window slides rather than resetting on a
         * fixed tick. A caller who keeps hammering never gets a fresh allowance by waiting for
         * a boundary, and a caller who stops is clear after one quiet window.
         */
        set_transient($bucket, $used + 1, $window);

        return null;
    }

    /**
     * Normalise a chat payload down to what we are willing to forward.
     *
     * Allowlisted rather than filtered: the body reaches OpenAI on our credential, so anything
     * this does not recognise is dropped instead of passed through. That is the difference
     * between proxying a request and lending out an API key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|WP_Error
     */
    public function sanitizeChatPayload(array $payload): array|WP_Error
    {
        $models = (array) ($this->config['chat_models'] ?? []);
        $model = (string) ($payload['model'] ?? '');

        if ($model === '' || ! in_array($model, $models, true)) {
            return new WP_Error(
                'jobwidget_bad_model',
                'Unsupported model.',
                ['status' => 400]
            );
        }

        $messages = $this->sanitizeMessages($payload['messages'] ?? null);

        if ($messages instanceof WP_Error) {
            return $messages;
        }

        $body = [
            'model' => $model,
            'messages' => $messages,
        ];

        /*
         * Capped, not rejected. A widget asking for more tokens than we allow is a widget
         * someone edited, not an attack, and truncating its answer beats a 400 the page has no
         * handling for.
         */
        $maxTokens = (int) ($payload['max_tokens'] ?? 0);
        $ceiling = (int) ($this->config['max_tokens'] ?? 1000);

        if ($maxTokens > 0) {
            $body['max_tokens'] = min($maxTokens, $ceiling);
        }

        if (isset($payload['temperature']) && is_numeric($payload['temperature'])) {
            $body['temperature'] = max(0.0, min(2.0, (float) $payload['temperature']));
        }

        return $body;
    }

    /**
     * @return array<int, array{role: string, content: string}>|WP_Error
     */
    protected function sanitizeMessages(mixed $messages): array|WP_Error
    {
        if (! is_array($messages) || $messages === []) {
            return new WP_Error(
                'jobwidget_no_messages',
                'Missing messages.',
                ['status' => 400]
            );
        }

        $clean = [];
        $bytes = 0;
        $maxBytes = (int) ($this->config['max_body_bytes'] ?? 65536);

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? '');
            $content = $message['content'] ?? '';

            // Only the three chat roles, and only plain string content: the multimodal content
            // array is a way to smuggle image URLs through a text-only proxy.
            if (! in_array($role, ['system', 'user', 'assistant'], true) || ! is_string($content)) {
                continue;
            }

            $content = trim($content);

            if ($content === '') {
                continue;
            }

            $bytes += strlen($content);

            if ($bytes > $maxBytes) {
                return new WP_Error(
                    'jobwidget_too_large',
                    'That request is too long. Please shorten it and try again.',
                    ['status' => 413]
                );
            }

            $clean[] = ['role' => $role, 'content' => $content];
        }

        if ($clean === []) {
            return new WP_Error(
                'jobwidget_no_messages',
                'Missing messages.',
                ['status' => 400]
            );
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{audio: string, filename: string}|WP_Error
     */
    public function sanitizeTranscriptionPayload(array $payload): array|WP_Error
    {
        $audio = (string) ($payload['audio'] ?? '');
        $filename = (string) ($payload['filename'] ?? '');

        if ($audio === '' || $filename === '') {
            return new WP_Error(
                'jobwidget_missing_fields',
                'Missing required fields: audio, filename.',
                ['status' => 400]
            );
        }

        // strict: base64_decode() otherwise silently discards junk and "succeeds" on anything.
        $decoded = base64_decode($audio, true);

        if ($decoded === false || $decoded === '') {
            return new WP_Error(
                'jobwidget_bad_audio',
                'That audio could not be read.',
                ['status' => 400]
            );
        }

        $maxBytes = (int) ($this->config['max_audio_bytes'] ?? 20971520);

        if (strlen($decoded) > $maxBytes) {
            return new WP_Error(
                'jobwidget_audio_too_large',
                'That recording is too long. Please upload a shorter clip.',
                ['status' => 413]
            );
        }

        return [
            'audio' => $decoded,
            'filename' => sanitize_file_name($filename) ?: 'audio.mp3',
        ];
    }

    /**
     * The caller's IP, as far as it can be trusted for rate limiting.
     *
     * The **right-most** X-Forwarded-For entry, not the left-most. AttributionCollector takes
     * the left-most one and is right to: it wants the original client for attribution. Here the
     * header is adversarial input — a caller can send any X-Forwarded-For they like, and
     * CloudFront *appends* the viewer IP to it rather than replacing it. Trusting the left-most
     * entry would let one attacker present a fresh IP on every request and never hit the limit.
     *
     * @param  array<string, mixed>  $server
     */
    public function resolveClientIp(array $server): ?string
    {
        $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');

        if ($forwarded !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $forwarded))));

            if ($parts !== []) {
                return substr((string) end($parts), 0, 45);
            }
        }

        $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));

        return $remote === '' ? null : substr($remote, 0, 45);
    }
}
