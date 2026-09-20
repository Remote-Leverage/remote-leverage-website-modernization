<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Gateways;

use App\Domains\Marketing\Data\MarketingDay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the data team's marketing day out of BigQuery.
 *
 * This replaced three ad platform clients — Meta REST, Google GAQL and a hand-built Microsoft SOAP
 * flow — that between them existed to obtain spend the warehouse already had, reconciled, behind
 * one query. The clients were deleted rather than kept as a fallback: two sources for the same
 * number is how a Slack card and a dashboard start disagreeing, and the whole reason for moving
 * to the warehouse was to stop that happening.
 *
 * ## Authentication
 *
 * A service account, signed locally. The flow is a self-signed JWT exchanged for an access token
 * — `openssl_sign` with the key from the service account JSON, posted to Google's token endpoint.
 * The official SDK would do this too, and would bring in `google/cloud-bigquery`, gRPC and a
 * transport layer to run one query an hour.
 *
 * The whole credential is one JSON blob in one secret rather than a private key split across
 * environment variables. A PEM has newlines in it, and a newline in an ECS task definition value
 * is the kind of thing that works locally and produces `error:0909006C:PEM routines` in
 * production.
 *
 * ## It never throws
 *
 * Same contract as everything else feeding the alert: a failure returns null and is logged, the
 * snapshot reports the cost half as unavailable, and the card says which part is missing rather
 * than rendering zeros.
 */
class BigQueryClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Not `bigquery.readonly`, which is the intuitive choice and does not work.
     *
     * Running a query is `jobs.query`, which creates a job resource, and the readonly scope is not
     * among the ones that method accepts — it covers reading data and metadata, not asking
     * BigQuery to compute something. A service account with the readonly scope authenticates
     * perfectly and is refused at the first query.
     *
     * Read-only is enforced by IAM instead, which is where it belongs: grant the service account
     * `roles/bigquery.jobUser` on the billing project and `roles/bigquery.dataViewer` on the
     * dataset, and this scope can do nothing beyond running the query it is given.
     */
    private const SCOPE = 'https://www.googleapis.com/auth/bigquery';

    private const TOKEN_CACHE_KEY = 'rl_bigquery_access_token';

    private const TOKEN_TTL_SECONDS = 3000;

    /** A warehouse query is not a page load, but it must not hang an hourly job either. */
    private const TIMEOUT_SECONDS = 30;

    /** BigQuery's own budget for returning results inline before it makes us poll a job. */
    private const QUERY_TIMEOUT_MS = 25000;

    public function isConfigured(): bool
    {
        return $this->credentials() !== null && $this->projectId() !== '';
    }

    /**
     * The marketing day, or null when it cannot be read.
     *
     * The query decides for itself which day that is — before 08:00 Eastern it reports yesterday
     * closed, after it today so far. See resources/sql/marketing-home-daily.sql.
     */
    public function marketingDay(): ?MarketingDay
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $row = $this->queryOneRow($this->sql());
        } catch (\Throwable $e) {
            Log::warning('BigQueryClient: could not read the marketing day', ['error' => $e->getMessage()]);

            return null;
        }

        return $row === null ? null : MarketingDay::fromRow($row);
    }

    /**
     * Run a query and return its first row as `column => value`.
     *
     * BigQuery's REST response separates the schema from the rows — fields come back as a list of
     * `{v: ...}` in schema order, with no names on them. Zipping them here is what lets the rest
     * of the code read `$row['cpb_paid']` instead of `$row['f'][18]['v']`, which would silently
     * read the wrong column the day somebody adds one to the SELECT.
     *
     * @return array<string, mixed>|null
     */
    private function queryOneRow(string $sql): ?array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(
                sprintf('https://bigquery.googleapis.com/bigquery/v2/projects/%s/queries', $this->projectId()),
                [
                    'query' => $sql,
                    'useLegacySql' => false,
                    'timeoutMs' => self::QUERY_TIMEOUT_MS,
                ],
            );

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorFrom($response->json(), $response->status()));
        }

        /*
         * A query that outran `timeoutMs` answers with a job reference and no rows. Treated as a
         * failure rather than polled: this runs hourly and the next run will get it, where a poll
         * loop holds the request open for a query that is already slower than it should be.
         */
        if ($response->json('jobComplete') !== true) {
            throw new \RuntimeException('the query did not complete within '.self::QUERY_TIMEOUT_MS.'ms');
        }

        $rows = (array) ($response->json('rows') ?? []);

        if ($rows === []) {
            return null;
        }

        $names = array_map(
            static fn (array $field): string => (string) ($field['name'] ?? ''),
            (array) ($response->json('schema.fields') ?? []),
        );

        $values = array_map(
            static fn ($cell) => is_array($cell) ? ($cell['v'] ?? null) : null,
            (array) ($rows[0]['f'] ?? []),
        );

        if (count($names) !== count($values)) {
            throw new \RuntimeException('the result schema and row do not line up');
        }

        return array_combine($names, $values);
    }

    /**
     * An access token for the service account.
     *
     * Self-signed JWT, exchanged. Cached just under its hour, and a cached empty string is
     * ignored rather than trusted — that would authenticate nothing for fifty minutes.
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();

        if ($credentials === null) {
            throw new \RuntimeException('no service account credentials configured');
        }

        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($credentials),
            ]);

        $token = (string) ($response->json('access_token') ?? '');

        if (! $response->successful() || $token === '') {
            throw new \RuntimeException(sprintf(
                'token exchange failed: %s',
                $response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status(),
            ));
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * The signed JWT Google exchanges for a token.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function assertion(array $credentials): string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'] ?? '',
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,

            // Google rejects anything over an hour, and clock skew eats the rest.
            'exp' => $now + 3600,
        ];

        $payload = $this->base64Url(json_encode($header)).'.'.$this->base64Url(json_encode($claims));

        $key = (string) ($credentials['private_key'] ?? '');
        $signature = '';

        if ($key === '' || ! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('could not sign the assertion; check the service account private key');
        }

        return $payload.'.'.$this->base64Url($signature);
    }

    private function base64Url(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    /**
     * The service account, decoded.
     *
     * Accepts the raw JSON or a base64 blob of it — a task definition that mangles quoting is a
     * real thing, and base64 is the usual escape hatch.
     *
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $raw = trim((string) config('marketing.warehouse.credentials', ''));

        if ($raw === '') {
            return null;
        }

        if (! str_starts_with($raw, '{')) {
            $decoded = base64_decode($raw, true);
            $raw = $decoded === false ? $raw : $decoded;
        }

        $credentials = json_decode($raw, true);

        if (! is_array($credentials) || ($credentials['private_key'] ?? '') === '') {
            Log::warning('BigQueryClient: the service account credential is not usable JSON');

            return null;
        }

        return $credentials;
    }

    private function projectId(): string
    {
        $configured = trim((string) config('marketing.warehouse.project_id', ''));

        if ($configured !== '') {
            return $configured;
        }

        // The service account's own project is the right default for billing the query.
        return (string) ($this->credentials()['project_id'] ?? '');
    }

    /** The data team's query, kept verbatim in a file of its own. */
    private function sql(): string
    {
        $path = get_theme_file_path('resources/sql/marketing-home-daily.sql');

        if (! is_readable($path)) {
            throw new \RuntimeException('the marketing day query is missing from the theme');
        }

        return (string) file_get_contents($path);
    }

    /** @param mixed $body */
    private function errorFrom($body, int $status): string
    {
        if (is_array($body)) {
            $message = $body['error']['message'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return sprintf('BigQuery returned HTTP %d with no error message', $status);
    }
}
