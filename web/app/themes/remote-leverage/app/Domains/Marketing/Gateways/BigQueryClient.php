<?php

declare(strict_types=1);

namespace App\Domains\Marketing\Gateways;

use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Marketing\Data\DaySupplement;
use App\Domains\Marketing\Data\MarketingDay;
use App\Domains\Marketing\Data\PartialDay;
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
 * Either a service account key or a user's OAuth credential, whichever `BIGQUERY_CREDENTIALS_JSON`
 * holds — they are both Google credential JSON and both carry a `type`, so nothing has to be
 * configured to say which. A service account is signed locally into a JWT and exchanged; a user
 * credential is a plain refresh-token grant.
 *
 * Both are supported because the credential that exists is not always the one you would choose. A
 * service account is the right thing for an unattended job and is what should end up here. A user
 * credential ties the alert to a person: it dies when they leave, change their password or revoke
 * the grant, and it carries whatever else that account can reach.
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
        return $this->misconfiguration() === null;
    }

    /**
     * Why this cannot run, in words, or null when it can.
     *
     * "Not configured" covers three different situations that need three different fixes, and the
     * one that catches people is the third: a user credential does not name a project, so signing
     * in successfully and then seeing nothing happen is the expected outcome of forgetting the
     * billing project. Saying which is missing turns that from a puzzle into a field to fill in.
     */
    public function misconfiguration(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return 'no Google credential is configured — sign in, or paste a service account key';
        }

        if ($this->projectId() === '') {
            return $this->isServiceAccount($credentials)
                ? 'the service account key names no project; set the billing project ID'
                : 'a signed-in Google account does not name a project; set the billing project ID';
        }

        return null;
    }

    /**
     * Today's running totals, for the hours the marketing day does not cover.
     *
     * Only meaningful before 08:00 Eastern, when {@see self::marketingDay()} reports the previous
     * day complete and says nothing about the hours since midnight. The caller decides when to ask
     * — see FunnelMetricsService::snapshot().
     *
     * Null rather than zeroes when there is no row yet. A day with no channel row is a day nothing
     * has happened on, and "no figures yet" and "zero spend" read very differently at 04:00.
     */
    public function todaySoFar(): ?PartialDay
    {
        $problem = $this->misconfiguration();

        if ($problem !== null) {
            return null;
        }

        try {
            $row = $this->queryOneRow($this->sql('marketing-today-so-far'));
        } catch (\Throwable $e) {
            /*
             * Warning, not an exception. This is a supplementary line on a card whose headline
             * comes from elsewhere; losing it must not cost the whole alert.
             */
            Log::warning('BigQueryClient: could not read today so far', ['error' => $e->getMessage()]);

            return null;
        }

        return $row === null ? null : PartialDay::fromRow($row);
    }

    /**
     * Freshness, per-platform staleness and the funnel above the lead, for a day already reported.
     *
     * Takes the date from the row {@see self::marketingDay()} returned rather than working it out
     * again, so the two cannot end up describing different days. The date is validated rather than
     * bound: `queryOneRow()` posts raw SQL with no parameter support, so the only safe thing to
     * interpolate is a string that has been proven to be exactly a date.
     *
     * Supplementary by definition — null on any failure, because losing it must not cost the card.
     */
    public function supplement(string $date): ?DaySupplement
    {
        if ($this->misconfiguration() !== null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }

        try {
            $row = $this->queryOneRow(str_replace('@@DATE@@', $date, $this->sql('marketing-day-supplement')));
        } catch (\Throwable $e) {
            Log::warning('BigQueryClient: could not read the day supplement', ['error' => $e->getMessage()]);

            return null;
        }

        return $row === null ? null : DaySupplement::fromRow($row);
    }

    /**
     * The marketing day, or null when it cannot be read.
     *
     * The query decides for itself which day that is — before 08:00 Eastern it reports yesterday
     * closed, after it today so far. See resources/sql/marketing-home-daily.sql.
     */
    public function marketingDay(): ?MarketingDay
    {
        $problem = $this->misconfiguration();

        if ($problem !== null) {
            Log::info('BigQueryClient: not reading the warehouse — '.$problem);

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
     * How many consultations sit on the calendar for each of the given local days.
     *
     * Replaces the Calendly round trips this figure used to be built from — six per snapshot, two
     * per day, over the two event-type URIs held in the `t0` and `t10` roles. The account has ten
     * active event types named a VA Hiring Consultation, so that counted two of ten: on
     * 2026-09-21 the card said 80 against a true 112, and sales read the gap as the calendar
     * emptying out. See resources/sql/consultation-calendar-load.sql for why the warehouse view
     * is a better answer than widening the role list would have been.
     *
     * Returns `YYYY-MM-DD => count`, and a day the warehouse answered for is present even when
     * its count is zero — the query LEFT JOINs the requested days precisely so that an empty
     * Sunday and an unreachable warehouse are distinguishable. Callers read a missing key as
     * "unavailable", never as zero.
     *
     * Per-day rather than all-or-nothing, which is the opposite of what the Calendly version did.
     * There it was right: the tiers were *summed*, so a missing tier made the total silently
     * wrong. These are independent figures printed side by side, so one unreadable day costs that
     * day and not the other two.
     *
     * Null on any failure, like everything else feeding the card.
     *
     * @param  array<int, string>  $dates  Local days as YYYY-MM-DD.
     * @return array<string, int>|null
     */
    public function consultationLoad(array $dates): ?array
    {
        $problem = $this->misconfiguration();

        if ($problem !== null) {
            Log::info('BigQueryClient: not reading the consultation calendar load — '.$problem);

            return null;
        }

        /*
         * Validated, not bound. queryRows() posts raw SQL with no parameter support, so the only
         * thing safe to interpolate is a string proven to be exactly a date — the same rule
         * supplement() follows for @@DATE@@.
         */
        $valid = array_values(array_unique(array_filter(
            $dates,
            static fn (string $date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
        )));

        if ($valid === [] || count($valid) !== count($dates)) {
            Log::warning('BigQueryClient: refusing a consultation load query for malformed dates', [
                'dates' => $dates,
            ]);

            return null;
        }

        $literals = implode(', ', array_map(
            static fn (string $date): string => "DATE '".$date."'",
            $valid,
        ));

        try {
            $rows = $this->queryRows(str_replace(
                '@@DATES@@',
                $literals,
                $this->sql('consultation-calendar-load'),
            ));
        } catch (\Throwable $e) {
            Log::warning('BigQueryClient: could not read the consultation calendar load', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $load = [];

        foreach ($rows as $row) {
            $date = (string) ($row['date'] ?? '');

            if ($date !== '') {
                $load[$date] = (int) ($row['consultations'] ?? 0);
            }
        }

        return $load;
    }

    /**
     * Run a query and return its first row as `column => value`, or null when it returned none.
     *
     * @return array<string, mixed>|null
     */
    private function queryOneRow(string $sql): ?array
    {
        $rows = $this->queryRows($sql);

        return $rows === [] ? null : $rows[0];
    }

    /**
     * Run a query and return every row as `column => value`.
     *
     * BigQuery's REST response separates the schema from the rows — fields come back as a list of
     * `{v: ...}` in schema order, with no names on them. Zipping them here is what lets the rest
     * of the code read `$row['cpb_paid']` instead of `$row['f'][18]['v']`, which would silently
     * read the wrong column the day somebody adds one to the SELECT.
     *
     * @return array<int, array<string, mixed>>
     */
    private function queryRows(string $sql): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(
                sprintf('https://bigquery.googleapis.com/bigquery/v2/projects/%s/queries', $this->projectId()),
                [
                    'query' => $sql,
                    'useLegacySql' => false,
                    'timeoutMs' => self::QUERY_TIMEOUT_MS,

                    /*
                     * No cached results, ever.
                     *
                     * BigQuery defaults this to true and will serve a byte-identical earlier
                     * answer for up to 24 hours when the SQL text has not changed — which, for a
                     * query whose only moving part is CURRENT_TIMESTAMP inside it, is every run.
                     * The row comes back whole: the figures *and* the `as_of_et` that says when
                     * they were read.
                     *
                     * A card posted at 03:48 carried "as of 04:50 ET", 62 minutes in the future,
                     * which is only possible if the row predated the request. Three other cards
                     * that night were accurate, so this is intermittent rather than constant —
                     * but freshness is the entire point of an hourly alert, and since every run
                     * now leaves a permanent card rather than editing one, a stale figure is
                     * published rather than overwritten a minute later.
                     *
                     * The query scans one day of an aggregated view and runs in about a second,
                     * so there is nothing here worth caching.
                     */
                    'useQueryCache' => false,
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
            return [];
        }

        $names = array_map(
            static fn (array $field): string => (string) ($field['name'] ?? ''),
            (array) ($response->json('schema.fields') ?? []),
        );

        return array_values(array_map(
            static function ($row) use ($names): array {
                $values = array_map(
                    static fn ($cell) => is_array($cell) ? ($cell['v'] ?? null) : null,
                    (array) ((is_array($row) ? $row['f'] : null) ?? []),
                );

                if (count($names) !== count($values)) {
                    throw new \RuntimeException('the result schema and row do not line up');
                }

                return array_combine($names, $values);
            },
            $rows,
        ));
    }

    /**
     * An access token, from whichever credential shape was supplied.
     *
     * Two are accepted because the credential that exists is not always the one you would choose.
     * A `service_account` key is the right thing for an unattended job; an `authorized_user` is
     * what `gcloud auth application-default login` writes and what an existing automation is
     * likely to already have. Both are valid Google credential JSON and both carry a `type`, so
     * nothing has to be configured to say which is which.
     *
     * Cached just under its hour, and a cached empty string is ignored rather than trusted —
     * that would authenticate nothing for fifty minutes.
     */
    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();

        if ($credentials === null) {
            throw new \RuntimeException('no BigQuery credentials configured');
        }

        $response = Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(self::TOKEN_ENDPOINT, $this->isServiceAccount($credentials)
                ? [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->assertion($credentials),
                ]
                : [
                    'grant_type' => 'refresh_token',
                    'client_id' => $credentials['client_id'] ?? '',
                    'client_secret' => $credentials['client_secret'] ?? '',
                    'refresh_token' => $credentials['refresh_token'] ?? '',
                ]);

        $token = (string) ($response->json('access_token') ?? '');

        if (! $response->successful() || $token === '') {
            /*
             * On a user credential `invalid_scope` here means the refresh token was minted
             * without `auth/bigquery` — it will have been granted for whatever the original
             * automation needed, and reading a view is not implied by that.
             */
            throw new \RuntimeException(sprintf(
                '%s token exchange failed: %s',
                $this->isServiceAccount($credentials) ? 'service account' : 'user',
                $response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status(),
            ));
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }

    /**
     * Is this a service account key, or a user's OAuth credential?
     *
     * `type` decides it, falling back to the presence of a private key — a credential hand-built
     * from three values may not carry the field Google would have written.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function isServiceAccount(array $credentials): bool
    {
        if (($credentials['type'] ?? '') === 'service_account') {
            return true;
        }

        return ($credentials['type'] ?? '') === ''
            && trim((string) ($credentials['private_key'] ?? '')) !== '';
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
     * The credential, whichever way it was supplied.
     *
     * A service account arrives as the JSON file Google gives you. A user credential arrives as
     * three separate values, because that is what an OAuth flow actually hands back — there is no
     * JSON to download, and expecting somebody to hand-assemble one was the wrong ask.
     *
     * The three-value form is assembled into the same shape here so everything downstream sees
     * one thing.
     *
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        $json = $this->setting('marketing.warehouse.credentials', 'bigquery_credentials_json');

        if ($json !== '') {
            return $this->decodeServiceAccount($json);
        }

        $clientId = $this->setting('marketing.warehouse.client_id', 'bigquery_client_id');
        $clientSecret = $this->setting('marketing.warehouse.client_secret', 'bigquery_client_secret');
        $refreshToken = $this->setting('marketing.warehouse.refresh_token', 'bigquery_refresh_token');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return null;
        }

        return [
            'type' => 'authorized_user',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * A service account key, decoded.
     *
     * Base64 is accepted as well as raw JSON: a private key is multi-line, and a task definition
     * or a copy-paste that mangles it is common enough to be worth the escape hatch.
     *
     * @return array<string, mixed>|null
     */
    private function decodeServiceAccount(string $raw): ?array
    {
        if (! str_starts_with($raw, '{')) {
            $decoded = base64_decode($raw, true);
            $raw = $decoded === false ? $raw : $decoded;
        }

        $credentials = json_decode($raw, true);

        if (! is_array($credentials)) {
            Log::warning('BigQueryClient: the service account key is not usable JSON');

            return null;
        }

        if (trim((string) ($credentials['private_key'] ?? '')) === '') {
            Log::warning('BigQueryClient: the service account key carries no private key');

            return null;
        }

        return $credentials;
    }

    private function projectId(): string
    {
        $configured = $this->setting('marketing.warehouse.project_id', 'bigquery_project_id');

        if ($configured !== '') {
            return $configured;
        }

        /*
         * A service account key names its project; a user credential usually does not, which is
         * why BIGQUERY_PROJECT_ID exists and is required in that case.
         */
        return (string) ($this->credentials()['project_id'] ?? $this->credentials()['quota_project_id'] ?? '');
    }

    /** The data team's query, kept verbatim in a file of its own. */
    private function sql(string $file = 'marketing-home-daily'): string
    {
        $path = get_theme_file_path('resources/sql/'.$file.'.sql');

        if (! is_readable($path)) {
            throw new \RuntimeException('the '.$file.' query is missing from the theme');
        }

        return (string) file_get_contents($path);
    }

    /**
     * Environment first, admin setting second.
     *
     * The same precedence `SlackCredentials` and `HubSpotGateway` use, and for the same reason:
     * ECS maps Secrets Manager keys to environment variables one at a time, so a newly added
     * credential cannot reach staging any other way until that changes. The Lead settings blob is
     * in the environment sync whitelist, which is what lets one be set locally and pushed.
     */
    private function setting(string $configKey, string $settingKey): string
    {
        $fromEnvironment = trim((string) (config($configKey) ?? ''));

        if ($fromEnvironment !== '') {
            return $fromEnvironment;
        }

        if (! class_exists(LeadSettingsService::class)) {
            return '';
        }

        return trim((string) ((new LeadSettingsService)->get()[$settingKey] ?? ''));
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
