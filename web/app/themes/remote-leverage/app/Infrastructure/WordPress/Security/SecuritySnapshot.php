<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Security;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Reads WordFence's state into a plain array the admin UI can render.
 *
 * The Security screens and the dashboard widget are ours; the data behind them is WordFence's.
 * Rather than let the views reach into `wfFirewall`, `wfScanner`, `wfIssues` and `wfConfig`
 * directly, everything funnels through here, which buys three things:
 *
 *  - **Never fatal.** WordFence absent, deactivated, or mid-upgrade yields `available => false`
 *    and a well-formed empty shape, not a white screen on wp-admin's home page. The same
 *    posture `WordfenceConfigurator` takes, and for the same reason: the plugin's internals
 *    are not a stable API and a dashboard widget is not worth a fatal.
 *  - **Testable.** The shape is a plain array, so the views can be exercised without the
 *    plugin loaded.
 *  - **Bounded cost.** `read()` runs six aggregate queries against `wfBlockedIPLog` and
 *    `wfLogins`. Those tables are the largest WordFence keeps, and the widget renders on every
 *    load of wp-admin's home page, so the result is cached.
 *
 * Deliberately NOT used: `wfDashboard`. Its constructor calls out to wordfence.com for
 * `/stats.json` on a cache miss, which would put a third-party HTTP request in the render path
 * of the dashboard. Everything here is local.
 */
class SecuritySnapshot
{
    /** Cache key for the assembled snapshot. */
    public const CACHE_KEY = 'rl.security.snapshot';

    /** How long a snapshot stands. Long enough to keep the dashboard cheap, short enough that a scan you just ran shows up. */
    public const CACHE_TTL = 300;

    /**
     * The shape returned when WordFence cannot be read.
     *
     * Every consumer can index into this without guarding, which is why it is spelled out in
     * full rather than returned as an empty array.
     *
     * @return array<string, mixed>
     */
    public static function unavailable(): array
    {
        return [
            'available' => false,
            'generated_at' => time(),
            'firewall' => [
                'mode' => 'unknown',
                'label' => 'Unavailable',
                'protection' => 'unknown',
                'percentage' => 0.0,
                'rules' => 0.0,
                'brute_force' => 0.0,
                'learning' => false,
            ],
            'scan' => [
                'enabled' => false,
                'running' => false,
                'status' => 'unknown',
                'message' => 'WordFence is not loaded on this environment.',
                'last_run' => null,
                'issues_new' => 0,
                'issues_ignored' => 0,
            ],
            'blocks' => [
                '24h' => ['complex' => 0, 'brute' => 0, 'blocklist' => 0, 'total' => 0],
                '7d' => ['complex' => 0, 'brute' => 0, 'blocklist' => 0, 'total' => 0],
                '30d' => ['complex' => 0, 'brute' => 0, 'blocklist' => 0, 'total' => 0],
            ],
            'top_ips' => [],
            'top_countries' => [],
            'failed_logins' => [],
            'updates' => ['core' => false, 'plugins' => 0, 'themes' => 0],
            'config' => ['managed' => 0, 'drifted' => [], 'unknown' => [], 'enforced' => false],
        ];
    }

    /**
     * Read the current state, from cache when warm.
     *
     * @return array<string, mixed>
     */
    public function read(bool $fresh = false): array
    {
        if ($fresh) {
            $this->forget();
        }

        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->build());
        } catch (Throwable) {
            // A cache backend that is down must not take wp-admin with it.
            return $this->safeBuild();
        }
    }

    /**
     * Drop the cached snapshot so the next read is live.
     */
    public function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Nothing to do — a miss on the next read is the same outcome.
        }
    }

    /**
     * Is WordFence loaded far enough to be read?
     */
    public function available(): bool
    {
        return class_exists('\wfConfig') && class_exists('\wfActivityReport');
    }

    /**
     * Build the snapshot, swallowing anything WordFence throws.
     *
     * @return array<string, mixed>
     */
    protected function safeBuild(): array
    {
        try {
            return $this->build();
        } catch (Throwable) {
            return self::unavailable();
        }
    }

    /**
     * Assemble the snapshot from WordFence's own classes.
     *
     * Each section is guarded on its own rather than the whole build, because the sections
     * fail independently — a missing `wfFirewall` should not cost us the block counts.
     *
     * @return array<string, mixed>
     */
    protected function build(): array
    {
        if (! $this->available()) {
            return self::unavailable();
        }

        $snapshot = self::unavailable();
        $snapshot['available'] = true;
        $snapshot['generated_at'] = time();

        $snapshot['firewall'] = $this->section(fn () => $this->readFirewall(), $snapshot['firewall']);
        $snapshot['scan'] = $this->section(fn () => $this->readScan(), $snapshot['scan']);
        $snapshot['blocks'] = $this->section(fn () => $this->readBlocks(), $snapshot['blocks']);
        $snapshot['top_ips'] = $this->section(fn () => $this->readTopIps(), []);
        $snapshot['top_countries'] = $this->section(fn () => $this->readTopCountries(), []);
        $snapshot['failed_logins'] = $this->section(fn () => $this->readFailedLogins(), []);
        $snapshot['updates'] = $this->section(fn () => $this->readUpdates(), $snapshot['updates']);
        $snapshot['config'] = $this->section(fn () => $this->readConfigDrift(), $snapshot['config']);

        return $snapshot;
    }

    /**
     * Run one section, falling back to its empty shape if WordFence throws.
     *
     * @template T
     *
     * @param  callable(): T  $reader
     * @param  T  $fallback
     * @return T
     */
    protected function section(callable $reader, mixed $fallback): mixed
    {
        try {
            return $reader();
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Firewall posture: mode, protection depth, and the completeness percentages
     * WordFence shows as donut gauges on its own Firewall page.
     *
     * @return array<string, mixed>
     */
    protected function readFirewall(): array
    {
        if (! class_exists('\wfFirewall')) {
            return self::unavailable()['firewall'];
        }

        $firewall = new \wfFirewall;
        $mode = (string) $firewall->firewallMode();

        return [
            'mode' => $mode,
            'label' => match ($mode) {
                'enabled' => 'Enabled and protecting',
                'learning-mode' => 'Learning mode',
                default => 'Disabled',
            },
            'protection' => (string) $firewall->protectionMode(),
            'percentage' => round((float) $firewall->overallStatus() * 100, 0),
            'rules' => round((float) $firewall->ruleStatus(true) * 100, 0),
            'brute_force' => round((float) $firewall->bruteForceStatus() * 100, 0),
            'learning' => $mode === 'learning-mode',
        ];
    }

    /**
     * Scan posture: whether it is on, whether it is running now, how the last one ended,
     * and how many issues are outstanding.
     *
     * `lastScanCompleted` is WordFence's own convention — the literal string 'ok' on success,
     * otherwise the failure message, otherwise empty when no scan has ever run.
     *
     * @return array<string, mixed>
     */
    protected function readScan(): array
    {
        $scan = self::unavailable()['scan'];

        $completed = \wfConfig::get('lastScanCompleted');

        if ($completed === false || $completed === '' || $completed === null) {
            $scan['status'] = 'never';
            $scan['message'] = 'No scan has run on this environment yet.';
        } elseif ($completed === 'ok') {
            $scan['status'] = 'ok';
            $scan['message'] = 'Last scan completed without error.';
        } else {
            $scan['status'] = 'failed';
            $scan['message'] = mb_substr((string) $completed, 0, 160);
        }

        if (class_exists('\wfScanner')) {
            $scanner = \wfScanner::shared();
            $scan['enabled'] = (bool) $scanner->isEnabled();
            $scan['running'] = (bool) $scanner->isRunning();

            $lastRun = (int) $scanner->lastScanTime();
            $scan['last_run'] = $lastRun > 0 ? $lastRun : null;
        }

        if (class_exists('\wfIssues')) {
            $counts = (array) (new \wfIssues)->getIssueCounts();
            $scan['issues_new'] = (int) ($counts['new'] ?? 0);
            $scan['issues_ignored'] = (int) ($counts['ignoreP'] ?? 0) + (int) ($counts['ignoreC'] ?? 0);
        }

        return $scan;
    }

    /**
     * Blocked-request counts over three windows, split by the three groupings WordFence
     * records: complex rules, brute force, and blocklist/manual.
     *
     * @return array<string, array<string, int>>
     */
    protected function readBlocks(): array
    {
        $report = new \wfActivityReport;

        $groups = [
            'complex' => \wfActivityReport::BLOCK_TYPE_COMPLEX,
            'brute' => \wfActivityReport::BLOCK_TYPE_BRUTE_FORCE,
            'blocklist' => \wfActivityReport::BLOCK_TYPE_BLACKLIST,
        ];

        $blocks = [];

        foreach (['24h' => 1, '7d' => 7, '30d' => 30] as $window => $days) {
            $row = ['complex' => 0, 'brute' => 0, 'blocklist' => 0, 'total' => 0];

            foreach ($groups as $key => $grouping) {
                $row[$key] = (int) $report->getBlockedCount($days, $grouping);
            }

            $row['total'] = $row['complex'] + $row['brute'] + $row['blocklist'];
            $blocks[$window] = $row;
        }

        return $blocks;
    }

    /**
     * Most-blocked IPs over the last 7 days.
     *
     * The IP column is stored packed, so it has to go back through `inet_ntop` to be legible.
     *
     * @return list<array{ip: string, country: string, count: int}>
     */
    protected function readTopIps(int $limit = 6): array
    {
        $rows = (array) (new \wfActivityReport)->getTopIPsBlocked($limit, 7);
        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $out[] = [
                'ip' => class_exists('\wfUtils') ? (string) \wfUtils::inet_ntop($row['IP'] ?? '') : '',
                'country' => (string) ($row['countryName'] ?? '') ?: 'Unknown',
                'count' => (int) ($row['blockCount'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Most-blocked countries over the last 7 days.
     *
     * @return list<array{code: string, name: string, count: int, ips: int}>
     */
    protected function readTopCountries(int $limit = 6): array
    {
        $rows = (array) (new \wfActivityReport)->getTopCountriesBlocked($limit, 7);
        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $out[] = [
                'code' => (string) ($row['countryCode'] ?? ''),
                'name' => (string) ($row['countryName'] ?? '') ?: 'Unknown',
                'count' => (int) ($row['totalBlockCount'] ?? 0),
                'ips' => (int) ($row['totalIPs'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Usernames with the most failed logins.
     *
     * `is_valid_user` is the interesting column: failures against a username that exists are
     * a credential-stuffing attempt on a real account, failures against one that does not are
     * an enumeration sweep. They warrant different responses, so the UI separates them.
     *
     * @return list<array{username: string, attempts: int, exists: bool}>
     */
    protected function readFailedLogins(int $limit = 6): array
    {
        $rows = (array) (new \wfActivityReport)->getTopFailedLogins($limit);
        $out = [];

        foreach ($rows as $row) {
            $row = (array) $row;

            $out[] = [
                'username' => (string) ($row['username'] ?? ''),
                'attempts' => (int) ($row['fail_count'] ?? 0),
                'exists' => (bool) ($row['is_valid_user'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Outstanding core/plugin/theme updates, from WordFence's cached check.
     *
     * The cached value is used deliberately: the live check reaches wordpress.org, and this
     * runs while wp-admin's home page is rendering.
     *
     * @return array{core: bool, plugins: int, themes: int}
     */
    protected function readUpdates(): array
    {
        $needed = (new \wfActivityReport)->getUpdatesNeeded(true);

        if (! is_array($needed)) {
            return ['core' => false, 'plugins' => 0, 'themes' => 0];
        }

        return [
            'core' => ! empty($needed['core']),
            'plugins' => count((array) ($needed['plugins'] ?? [])),
            'themes' => count((array) ($needed['themes'] ?? [])),
        ];
    }

    /**
     * How far WordFence's live configuration has drifted from `config/wordfence.php`.
     *
     * This is the piece WordFence's own dashboard cannot show and the reason the widget is
     * worth replacing rather than restyling. The repository is the source of truth here and
     * `rl:deploy` re-asserts it on every container start, so a key that reads as drifted is
     * either a hand-edit in wp-admin that is about to be reverted, or a deploy that did not
     * run. Both are worth seeing before the revert surprises someone.
     *
     * @return array{managed: int, drifted: list<array<string, mixed>>, unknown: list<string>, enforced: bool}
     */
    protected function readConfigDrift(): array
    {
        $audit = app(WordfenceConfigurator::class)->audit();

        return [
            'managed' => count($audit['matching']) + count($audit['drifted']),
            'drifted' => $audit['drifted'],
            'unknown' => $audit['unknown'],
            'enforced' => (bool) config('wordfence.apply_on_deploy', true),
        ];
    }
}
