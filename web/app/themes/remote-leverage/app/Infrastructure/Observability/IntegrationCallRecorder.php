<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Illuminate\Support\Facades\Log;

/**
 * Records outbound integration traffic: what we sent, what came back.
 *
 * ## Why this exists as one service rather than logging at each call site
 *
 * There are nineteen outbound call sites across Calendly, HubSpot, Slack, Stripe and Google.
 * Logging at each one guarantees they drift: some log the request, some the response, none log
 * both, and the one you need on the day something breaks is the one that logged a summary
 * written *before* the call returned. Laravel's HTTP client dispatches an event carrying the
 * real request and response, so a single listener captures all of them uniformly and new call
 * sites are covered the day they are written.
 *
 * ## Credentials
 *
 * Recording full request headers means recording `Authorization`. These rows are readable by
 * any WordPress administrator and survive in the database for weeks, so storing the bearer token
 * would turn the diagnostic log into the easiest place in the system to harvest credentials from.
 *
 * Instead every secret is replaced by a **fingerprint** that still answers the operational
 * question. "Which token did Calendly use?" is really "did it fall through to the second token
 * in the pool?", and `Bearer ****9f3a (sha256:1b7d40e2)` answers that: the same token always
 * produces the same fingerprint, two different tokens never collide, and the value cannot be
 * used to authenticate. The last four characters are kept so a fingerprint can be matched
 * against the credential in Secrets Manager by eye.
 *
 * ## Failure
 *
 * Every entry point is wrapped. Recording is diagnostic; a broken recorder must never turn a
 * successful HubSpot sync into a failed one, and a table that does not exist yet (a request
 * arriving between deploy and migration) must not take the site down.
 */
class IntegrationCallRecorder
{
    /**
     * The lead the current request is about, if any.
     *
     * Ambient rather than passed, because the call sites that know the lead (an action, a
     * controller) are several layers above the ones that make the HTTP call (a gateway), and
     * threading an id through every gateway signature to serve logging would be the tail
     * wagging the dog.
     */
    protected ?int $leadId = null;

    public function __construct(
        protected ?CredentialRegistry $credentials = null,
    ) {
        $this->credentials ??= new CredentialRegistry;
    }

    /**
     * Start times, keyed by method and URL.
     *
     * Not keyed by object identity: Laravel wraps the outgoing Guzzle request in a fresh
     * `Illuminate\Http\Client\Request` for each of the two events, so the instance seen on the
     * way out is never the instance seen on the way back. Each key holds a queue, so several
     * calls to the same endpoint in one request are timed in the order they return.
     *
     * @var array<string, float[]>
     */
    protected array $started = [];

    public function forLead(?int $leadId): void
    {
        $this->leadId = $leadId ?: null;
    }

    public function currentLead(): ?int
    {
        return $this->leadId;
    }

    public function enabled(): bool
    {
        return (bool) config('observability.integration_calls.enabled', true);
    }

    /**
     * Note that a request has gone out, so the response can be timed.
     */
    public function markSent(string $method, string $url): void
    {
        $key = $this->timingKey($method, $url);

        // Bounded, so a long-running console command cannot accumulate start times for
        // responses that never arrive.
        if (count($this->started) > 200) {
            $this->started = [];
        }

        $this->started[$key][] = microtime(true);
    }

    /**
     * Elapsed milliseconds for a request, if we saw it leave.
     */
    public function elapsed(string $method, string $url): ?int
    {
        $key = $this->timingKey($method, $url);

        if (empty($this->started[$key])) {
            return null;
        }

        $startedAt = array_shift($this->started[$key]);

        if ($this->started[$key] === []) {
            unset($this->started[$key]);
        }

        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    protected function timingKey(string $method, string $url): string
    {
        return strtoupper($method).' '.$url;
    }

    /**
     * Write a call to the log.
     *
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $responseHeaders
     */
    public function record(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $statusCode = null,
        array $responseHeaders = [],
        ?string $responseBody = null,
        ?int $durationMs = null,
        ?string $errorMessage = null,
        ?int $leadId = null,
    ): ?IntegrationCall {
        try {
            if (! $this->enabled()) {
                return null;
            }

            $integration = $this->classify($url);

            if ($integration === null) {
                return null;
            }

            $outcome = $this->outcome($statusCode, $errorMessage);
            $redactedResponseBody = $this->redactBody($responseBody);
            $errorMessage ??= $this->summarizeFailure($outcome, $statusCode, $redactedResponseBody);

            return IntegrationCall::query()->create([
                'lead_id' => $leadId ?? $this->leadId,
                'integration' => $integration,
                'operation' => $this->operation($method, $url),
                'method' => strtoupper($method),
                'url' => $this->redactUrl($url),
                'credential_label' => $this->credentialLabel($headers),
                'request_headers' => $this->redactHeaders($headers),
                'request_body' => $this->redactBody($body),
                'status_code' => $statusCode,
                'response_headers' => $this->redactHeaders($responseHeaders),
                'response_body' => $redactedResponseBody,
                'duration_ms' => $durationMs,
                'outcome' => $outcome,
                'error_message' => $errorMessage,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Diagnostics must never break the thing being diagnosed.
            Log::warning('IntegrationCallRecorder: could not record call to '.$url.': '.$e->getMessage());

            return null;
        }
    }

    /**
     * The account a request authenticated as, when something can name it.
     *
     * Stored as its own column rather than left inside the headers blob, because the whole
     * value of it is being able to see at a glance which account a failing call used — and to
     * filter a week of traffic down to one of them.
     *
     * @param  array<string, mixed>  $headers
     */
    public function credentialLabel(array $headers): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'authorization') {
                continue;
            }

            $raw = is_array($value) ? (string) reset($value) : (string) $value;
            $secret = trim($raw);

            foreach (['Bearer ', 'Basic ', 'Token '] as $scheme) {
                if (stripos($secret, $scheme) === 0) {
                    $secret = trim(substr($secret, strlen($scheme)));

                    break;
                }
            }

            return $this->credentials->labelFor($secret);
        }

        return null;
    }

    /**
     * Which integration a URL belongs to, or null if we do not record it.
     */
    public function classify(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return null;
        }

        foreach ((array) config('observability.integration_calls.hosts', []) as $suffix => $name) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return (string) $name;
            }
        }

        return config('observability.integration_calls.record_unknown_hosts', false) ? 'other' : null;
    }

    /**
     * A short, groupable label: the method plus the path with ids stripped out.
     *
     * Without the substitution every Calendly event is its own operation and the log cannot be
     * grouped or scanned — `GET /scheduled_events/{id}` is the useful unit, not one row per UUID.
     */
    protected function operation(string $method, string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        $path = preg_replace('#/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}#i', '/{uuid}', $path) ?? $path;
        $path = preg_replace('#/\d{4,}#', '/{id}', $path) ?? $path;

        return mb_substr(strtoupper($method).' '.$path, 0, 160);
    }

    /**
     * `succeeded` for a 2xx/3xx, `failed` for an answer we did not want, `error` for no answer.
     */
    protected function outcome(?int $statusCode, ?string $errorMessage): string
    {
        if ($statusCode === null) {
            return 'error';
        }

        if ($statusCode >= 400) {
            return 'failed';
        }

        return $errorMessage ? 'failed' : 'succeeded';
    }

    /**
     * A short, human summary of why a call failed, for the calls that never hand `record()` one.
     *
     * `ConnectionFailed` and a WordPress `wp_error` already carry a real exception message — but
     * those are transport failures, the rarer case. The far more common failure is a request
     * that *completed* with a 4xx/5xx: HubSpot rejecting a contact property, a Stripe key that
     * lost access. Laravel's `ResponseReceived` event carries no exception at all then, so
     * `error_message` stayed null for exactly the failures this table exists to explain, while
     * the detail sat unread in `response_body` — the health widget and the CLI show only
     * `error_message`, so a real rejection looked identical to an integration nobody has called.
     *
     * Reads the already-redacted body, never the raw one, so a summary can never surface a
     * secret redaction stripped from the full copy.
     */
    protected function summarizeFailure(string $outcome, ?int $statusCode, ?string $redactedResponseBody): ?string
    {
        if ($outcome !== 'failed' || $statusCode === null) {
            return null;
        }

        $decoded = $redactedResponseBody !== null ? json_decode($redactedResponseBody, true) : null;

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach (['message', 'error_description', 'error'] as $key) {
                $value = $decoded[$key] ?? null;

                if (is_string($value) && $value !== '') {
                    return "HTTP {$statusCode}: {$value}";
                }
            }

            $nested = $decoded['error']['message'] ?? null;

            if (is_string($nested) && $nested !== '') {
                return "HTTP {$statusCode}: {$nested}";
            }
        }

        $snippet = trim((string) $redactedResponseBody);

        return $snippet === '' ? "HTTP {$statusCode}" : "HTTP {$statusCode}: ".mb_substr($snippet, 0, 300);
    }

    /**
     * Replace credential header values with fingerprints.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    public function redactHeaders(array $headers): array
    {
        $sensitive = array_map('strtolower', (array) config('observability.integration_calls.redact_headers', []));
        $out = [];

        foreach ($headers as $name => $value) {
            // Guzzle and WordPress both hand back arrays for repeated headers.
            $flat = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;

            $out[(string) $name] = in_array(strtolower((string) $name), $sensitive, true)
                ? $this->fingerprintHeader($flat)
                : mb_substr($flat, 0, 1000);
        }

        return $out;
    }

    /**
     * Fingerprint a header, preserving its scheme.
     *
     * `Bearer eyJhbGci...` becomes `Bearer ****XKt4 (sha256:9c14be02)` — enough to tell two
     * tokens apart and to recognise one, useless for authenticating.
     */
    protected function fingerprintHeader(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        foreach (['Bearer ', 'Basic ', 'Token '] as $scheme) {
            if (stripos($value, $scheme) === 0) {
                return $scheme.$this->fingerprint(substr($value, strlen($scheme)));
            }
        }

        return $this->fingerprint($value);
    }

    /**
     * A stable, non-reversible identifier for a secret.
     *
     * Salted with the app key so the log is not a lookup table of hashes, and truncated because
     * eight hex characters already make an accidental collision between the handful of
     * credentials this site holds vanishingly unlikely.
     */
    public function fingerprint(string $secret): string
    {
        $secret = trim($secret);

        if ($secret === '') {
            return '';
        }

        $salt = (string) (config('app.key') ?: 'rl-observability');
        $hash = substr(hash('sha256', $secret.$salt), 0, 8);

        // Short values are masked entirely: showing the last four of a six-character
        // secret gives away most of it.
        $tail = mb_strlen($secret) >= 12 ? mb_substr($secret, -4) : '';

        return $tail === ''
            ? "****(sha256:{$hash})"
            : "****{$tail} (sha256:{$hash})";
    }

    /**
     * Strip credentials that travel in the query string, and cap the length.
     */
    public function redactUrl(string $url): string
    {
        $keys = (array) config('observability.integration_calls.redact_keys', []);

        $redacted = preg_replace_callback(
            '/([?&])('.implode('|', array_map('preg_quote', $keys)).')=([^&#]*)/i',
            fn (array $m) => $m[1].$m[2].'='.rawurlencode($this->fingerprint(rawurldecode($m[3]))),
            $url
        );

        return mb_substr($redacted ?? $url, 0, 2000);
    }

    /**
     * Redact secrets inside a body and truncate it.
     *
     * JSON is walked key by key so a nested credential is caught. Anything that is not JSON is
     * kept verbatim: a form post or an HTML error page has no reliable structure to walk, and
     * mangling it with regexes would cost more in legibility than it buys.
     */
    public function redactBody(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        $max = (int) config('observability.integration_calls.max_body_bytes', 65536);

        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $body = (string) json_encode($this->redactArray($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (strlen($body) > $max) {
            $body = substr($body, 0, $max)."\n… truncated at {$max} bytes";
        }

        return $body;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function redactArray(array $data): array
    {
        $keys = array_map('strtolower', (array) config('observability.integration_calls.redact_keys', []));

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->redactArray($value);

                continue;
            }

            if (is_string($value) && is_string($key) && in_array(strtolower($key), $keys, true)) {
                $data[$key] = $this->fingerprint($value);
            }
        }

        return $data;
    }
}
