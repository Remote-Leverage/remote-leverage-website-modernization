<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Gateways;

use App\Domains\Lead\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Server-side `Lead` events to Meta's Conversions API.
 *
 * This is the only conversion signal Meta receives from this site. There is no client-side
 * `fbq('track','Lead')` and there never was one to inherit: the legacy stack's HandL UTM Grabber
 * posted a Lead to the Graph API on every Gravity Forms submit, and removing that plugin at
 * cutover took the entire channel with it. Between 2026-09-17 20:46 and 2026-09-18, Ads Manager
 * reported 0 conversions against roughly 12 real ones while GA4, Google Ads and PostHog all
 * recorded them correctly.
 *
 * ## Fidelity to the integration this replaces
 *
 * The normalisation and hashing below reproduce HandL's `fb-offline-conversion.php` exactly,
 * because Meta matches on the hash: a value normalised even slightly differently hashes to
 * something else and simply fails to match a person, with no error anywhere. That plugin's output
 * is the only version of this we have evidence worked, so it is the specification here rather
 * than a starting point.
 *
 * ## Both pixels
 *
 * Production initialises two pixel ids and nobody has confirmed which the ad account reports
 * against; the legacy CAPI only ever fed the first. The event goes to every id in
 * `pixels.meta.pixel_ids`, because a conversion on the wrong pixel is invisible while a duplicate
 * on the right one is collapsed by `event_id`.
 */
class MetaConversionsApiClient
{
    /**
     * PII fields Meta expects as lowercase sha256 hex.
     *
     * `fbc`, `fbp`, `client_ip_address` and `client_user_agent` are deliberately absent: Meta
     * requires those raw, and hashing them silently destroys the match.
     */
    private const HASHED_FIELDS = ['em', 'ph', 'ge', 'db', 'ln', 'fn', 'ct', 'st', 'zp', 'country', 'external_id'];

    /**
     * Meta rejects an event whose `event_time` is more than 7 days old, for the whole batch.
     * A replay of an old lead is worth sending with a clamped timestamp rather than not at all.
     */
    private const MAX_EVENT_AGE_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(
        protected ?string $accessToken = null,
        protected ?string $apiVersion = null,
    ) {
        $this->accessToken ??= (string) config('services.meta_capi.access_token', '');
        $this->apiVersion ??= (string) config('services.meta_capi.api_version', 'v11.0');
    }

    /**
     * Is there enough configuration to send anything at all?
     *
     * A blank token degrades to the pre-2026-09-18 behaviour (no server-side event) rather than
     * throwing on every lead, so a missing secret costs conversions without costing bookings.
     */
    public function enabled(): bool
    {
        return trim((string) $this->accessToken) !== ''
            && (bool) config('services.meta_capi.enabled', true)
            && $this->pixelIds() !== [];
    }

    /**
     * @return list<string>
     */
    public function pixelIds(): array
    {
        return array_values(array_filter(
            (array) config('pixels.meta.pixel_ids', []),
            static fn ($id): bool => preg_match('/^\d{10,}$/', (string) $id) === 1,
        ));
    }

    /**
     * Send one `Lead` to every configured pixel.
     *
     * Never throws: a Meta outage must not take a booking down with it. The return value is what
     * the caller writes into the lead timeline.
     *
     * @return array{sent: list<string>, failed: list<string>, skipped: bool, event_id: string, errors: list<string>}
     */
    public function sendLead(Lead $lead, string $eventName = 'Lead'): array
    {
        $eventId = $this->eventId($lead);

        $result = ['sent' => [], 'failed' => [], 'skipped' => false, 'event_id' => $eventId, 'errors' => []];

        if (! $this->enabled()) {
            Log::debug('MetaConversionsApiClient: skipped, no access token or no pixel ids configured');
            $result['skipped'] = true;

            return $result;
        }

        $payload = $this->buildEvent($lead, $eventName, $eventId);

        foreach ($this->pixelIds() as $pixelId) {
            if ($this->post($pixelId, $payload, $result)) {
                $result['sent'][] = $pixelId;

                continue;
            }

            $result['failed'][] = $pixelId;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{sent: list<string>, failed: list<string>, skipped: bool, event_id: string, errors: list<string>}  $result
     */
    private function post(string $pixelId, array $payload, array &$result): bool
    {
        $url = sprintf('https://graph.facebook.com/%s/%s/events', $this->apiVersion, $pixelId);

        /*
         * The token goes in the JSON body rather than the query string or a form body on purpose.
         * IntegrationCallRecorder writes every one of these calls into rl_integration_calls, and
         * its `redact_keys` fingerprints `access_token` at any depth of a JSON body — it does not
         * parse a query string or a form encoding. Sending it any other way would write a live
         * Meta token into a database table in plaintext, once per lead.
         */
        $body = [
            'data' => [$payload],
            'access_token' => $this->accessToken,
        ];

        $testCode = (string) config('services.meta_capi.test_event_code', '');

        if ($testCode !== '') {
            $body['test_event_code'] = $testCode;
        }

        try {
            $response = Http::timeout((int) config('services.meta_capi.timeout', 8))
                ->connectTimeout((int) config('services.meta_capi.connect_timeout', 5))
                ->asJson()
                ->post($url, $body);
        } catch (\Throwable $e) {
            $message = sprintf('pixel %s: %s', $pixelId, $e->getMessage());
            Log::error('MetaConversionsApiClient Send Error: '.$message);
            $result['errors'][] = $message;

            return false;
        }

        if (! $response->successful()) {
            /*
             * Logged rather than swallowed. A 400 here is the normal way this integration breaks
             * — an expired token or a pixel the token cannot write to — and it is invisible
             * everywhere else, because Ads Manager showing 0 looks identical to no traffic.
             */
            $message = sprintf(
                'pixel %s returned %d — %s',
                $pixelId,
                $response->status(),
                Str::limit((string) $response->body(), 300),
            );
            Log::error('MetaConversionsApiClient Rejected: '.$message);
            $result['errors'][] = $message;

            return false;
        }

        return true;
    }

    /**
     * A stable id for this conversion, so a retry or a double-dispatch collapses into one event.
     *
     * Also the join key if a browser-side `fbq('track','Lead', {}, {eventID})` is ever added —
     * Meta deduplicates a server and browser event that share it.
     */
    public function eventId(Lead $lead): string
    {
        return 'lead-'.($lead->uuid ?: $lead->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildEvent(Lead $lead, string $eventName, string $eventId): array
    {
        $attribution = (array) ($lead->attribution ?? []);
        $handl = (array) ($attribution['handl'] ?? []);

        $userData = $this->hashUserData(array_filter([
            'em' => $lead->email,
            'ph' => $this->phoneWithCountry($lead),
            'fn' => $lead->first_name,
            'ln' => $lead->last_name,
        ], static fn ($v): bool => (string) $v !== ''));

        /*
         * Raw, never hashed. `fbc` is the click id Meta uses to tie the conversion back to the ad
         * — it is the single highest-value field here, and it is why AttributionCollector persists
         * `_fbc`/`fbclid` on every lead. `_fbp` has no column and lives in the attribution blob.
         */
        $unhashed = array_filter([
            'fbc' => $lead->fbc ?: $this->deriveFbc($lead),
            'fbp' => $handl['_fbp'] ?? null,
            'client_ip_address' => $lead->ip_address,
            'client_user_agent' => $attribution['user_agent'] ?? null,
        ], static fn ($v): bool => (string) $v !== '');

        $event = [
            'event_name' => $eventName,
            'event_time' => $this->eventTime($lead),
            'event_id' => $eventId,
            // Required by Meta for web conversions; the legacy payload omitted it, which is one
            // of the few places this deliberately improves on what it replaces.
            'action_source' => 'website',
            'user_data' => $userData + $unhashed,
        ];

        $sourceUrl = (string) ($lead->landing_url ?: $lead->landing_page_base ?: '');

        if ($sourceUrl !== '') {
            $event['event_source_url'] = $sourceUrl;
        }

        $custom = array_filter([
            'lead_source' => $lead->source_type,
            'submission_type' => $lead->submission_type,
            'intake_form' => $lead->intake_form,
        ], static fn ($v): bool => (string) $v !== '');

        if ($custom !== []) {
            $event['custom_data'] = $custom;
        }

        return $event;
    }

    /**
     * Meta wants the phone with its country code and no punctuation. `phone` is stored without a
     * country code, which would match nothing on its own.
     */
    private function phoneWithCountry(Lead $lead): string
    {
        $phone = (string) ($lead->phone ?? '');

        if ($phone === '') {
            return '';
        }

        $country = preg_replace('/\D/', '', (string) ($lead->phone_country ?? '')) ?: '';

        if ($country !== '' && ! str_starts_with(preg_replace('/\D/', '', $phone) ?: '', $country)) {
            return $country.$phone;
        }

        return $phone;
    }

    /**
     * Rebuild `fbc` from a bare `fbclid` when the `_fbc` cookie never reached us.
     *
     * Meta's documented format is `fb.<subdomainIndex>.<creationTimeMs>.<fbclid>`. The lead's own
     * creation time is the closest honest value for the click time.
     */
    private function deriveFbc(Lead $lead): string
    {
        $fbclid = (string) ($lead->fbclid ?? '');

        if ($fbclid === '') {
            return '';
        }

        $createdAt = $lead->created_at?->getTimestamp() ?? time();

        return sprintf('fb.1.%d.%s', $createdAt * 1000, $fbclid);
    }

    private function eventTime(Lead $lead): int
    {
        $createdAt = $lead->created_at?->getTimestamp() ?? time();
        $oldest = time() - self::MAX_EVENT_AGE_SECONDS;

        return max($createdAt, $oldest);
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    public function hashUserData(array $userData): array
    {
        foreach ($userData as $key => $value) {
            $userData[$key] = self::hash($key, self::normalize($key, $value));
        }

        return $userData;
    }

    /**
     * Ported verbatim from HandL's `fb-offline-conversion.php::normalize()`.
     *
     * Meta matches on the hash, so these rules are load-bearing rather than cosmetic — stripping
     * a different set of characters produces a different hash and a silent non-match.
     */
    public static function normalize(string $key, mixed $value): mixed
    {
        $value = (string) $value;

        if ($key === 'em') {
            return trim(strtolower($value), " \t\r\n\0\x0B.");
        }

        if (in_array($key, ['country', 'ct', 'st', 'fn', 'ln'], true)) {
            return preg_replace('/[^a-z]/', '', strtolower(trim($value))) ?? '';
        }

        if ($key === 'zp') {
            return explode('-', preg_replace('/[ ]/', '', strtolower(trim($value))) ?? '')[0];
        }

        if ($key === 'ph') {
            return trim(strtolower(preg_replace(['/\(/', '/\)/', '/-/', '/\s+/', '/\+/'], '', $value) ?? ''));
        }

        if (in_array($key, ['ge', 'db', 'external_id'], true)) {
            return trim(strtolower($value));
        }

        return $value;
    }

    public static function hash(string $key, mixed $value): mixed
    {
        if (! in_array($key, self::HASHED_FIELDS, true)) {
            return $value;
        }

        return hash('sha256', (string) $value);
    }
}
