<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Gateways;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CalendlyMetadataCache
{
    protected const EVENT_DETAILS_PREFIX = 'rl_calendly_event_details_';

    protected const QUESTIONS_BACKUP_PREFIX = 'rl_calendly_questions_backup_';

    protected const QUESTIONS_FRESH_PREFIX = 'rl_calendly_questions_fresh_';

    protected const EVENT_DETAILS_TTL_SECONDS = 3600; // 1 hour, matches legacy

    protected const QUESTIONS_BACKUP_TTL_DAYS = 30; // bounded, self-cleaning; far longer than the 6h freshness window

    protected const QUESTIONS_FRESH_TTL_HOURS = 6; // matches legacy

    protected const ALERT_EMAIL = 'adrian@remoteleverage.com';

    public function __construct(protected CalendlyClient $client) {}

    /**
     * Event-type details: simple cache-or-fetch, 1h TTL. No stale-while-revalidate —
     * matches legacy's get_event_type_details_rest(), which had none either.
     */
    public function getEventType(string $eventUriOrUuid): ?array
    {
        $key = self::EVENT_DETAILS_PREFIX.md5($eventUriOrUuid);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $resource = $this->client->getEventType($eventUriOrUuid, 'metadata');

        if ($resource !== null) {
            Cache::put($key, $resource, now()->addSeconds(self::EVENT_DETAILS_TTL_SECONDS));
        }

        return $resource;
    }

    /**
     * Booking questions: stale-while-revalidate, ported from legacy's
     * get_booking_questions(). Backup present + fresh -> return immediately, no I/O.
     * Backup present + stale -> return the stale backup immediately (never block),
     * scheduling a background refresh if one isn't already in flight. No backup at
     * all -> fetch synchronously (metadata-context, circuit-breaker gated); on total
     * pool exhaustion, alert and return [].
     */
    public function getEventQuestions(string $eventUriOrUuid): array
    {
        $backupKey = self::QUESTIONS_BACKUP_PREFIX.md5($eventUriOrUuid);
        $freshKey = self::QUESTIONS_FRESH_PREFIX.md5($eventUriOrUuid);

        $backup = Cache::get($backupKey);

        if ($backup !== null) {
            if (! Cache::has($freshKey)) {
                $this->scheduleBackgroundRefresh($eventUriOrUuid, $freshKey);
            }

            return $backup;
        }

        return $this->fetchAndStoreQuestions($eventUriOrUuid);
    }

    /**
     * Refreshes both caches for one event URI in a single HTTP round-trip
     * (getEventType() already returns custom_questions), invoked by the
     * on-demand background-refresh hook and the twice-daily warm-cache job.
     */
    public function warm(string $eventUriOrUuid): void
    {
        $resource = $this->client->getEventType($eventUriOrUuid, 'metadata');

        if ($resource === null) {
            Log::warning("CalendlyMetadataCache::warm: failed to refresh {$eventUriOrUuid}");

            return;
        }

        Cache::put(self::EVENT_DETAILS_PREFIX.md5($eventUriOrUuid), $resource, now()->addSeconds(self::EVENT_DETAILS_TTL_SECONDS));

        $backupKey = self::QUESTIONS_BACKUP_PREFIX.md5($eventUriOrUuid);
        $freshKey = self::QUESTIONS_FRESH_PREFIX.md5($eventUriOrUuid);
        Cache::put($backupKey, $resource['custom_questions'] ?? [], now()->addDays(self::QUESTIONS_BACKUP_TTL_DAYS));
        Cache::put($freshKey, 'fresh', now()->addHours(self::QUESTIONS_FRESH_TTL_HOURS));
    }

    public function forget(string $eventUriOrUuid): void
    {
        Cache::forget(self::EVENT_DETAILS_PREFIX.md5($eventUriOrUuid));
        Cache::forget(self::QUESTIONS_BACKUP_PREFIX.md5($eventUriOrUuid));
        Cache::forget(self::QUESTIONS_FRESH_PREFIX.md5($eventUriOrUuid));
    }

    /**
     * @param  string[]  $eventUris
     */
    public function forgetAll(array $eventUris): void
    {
        foreach ($eventUris as $uri) {
            $this->forget($uri);
        }
    }

    protected function fetchAndStoreQuestions(string $eventUriOrUuid): array
    {
        $questions = $this->client->getEventQuestions($eventUriOrUuid);

        if (! empty($questions)) {
            $backupKey = self::QUESTIONS_BACKUP_PREFIX.md5($eventUriOrUuid);
            $freshKey = self::QUESTIONS_FRESH_PREFIX.md5($eventUriOrUuid);
            Cache::put($backupKey, $questions, now()->addDays(self::QUESTIONS_BACKUP_TTL_DAYS));
            Cache::put($freshKey, 'fresh', now()->addHours(self::QUESTIONS_FRESH_TTL_HOURS));

            return $questions;
        }

        $this->sendCriticalAlert($eventUriOrUuid);

        return [];
    }

    protected function scheduleBackgroundRefresh(string $eventUriOrUuid, string $freshKey): void
    {
        if (! function_exists('wp_next_scheduled') || ! function_exists('wp_schedule_single_event')) {
            return;
        }

        if (wp_next_scheduled('rl_calendly_refresh_questions', [$eventUriOrUuid])) {
            return;
        }

        wp_schedule_single_event(time(), 'rl_calendly_refresh_questions', [$eventUriOrUuid]);
        Cache::put($freshKey, 'scheduled', now()->addHours(self::QUESTIONS_FRESH_TTL_HOURS));
    }

    protected function sendCriticalAlert(string $eventUri): void
    {
        if (! function_exists('wp_mail')) {
            return;
        }

        try {
            $subject = '[Calendly Alert] Total Calendly Question Fetch Failure';
            $body = "CRITICAL: All tokens failed to fetch questions for {$eventUri} and no backup exists.\n\n"
                ."Event URI: {$eventUri}\n\n--\nRemote Leverage Calendly Resilience System\nSite: ".home_url();
            wp_mail(self::ALERT_EMAIL, $subject, $body);
        } catch (\Throwable $e) {
            Log::error('CalendlyMetadataCache: failed to send critical alert email: '.$e->getMessage());
        }
    }
}
