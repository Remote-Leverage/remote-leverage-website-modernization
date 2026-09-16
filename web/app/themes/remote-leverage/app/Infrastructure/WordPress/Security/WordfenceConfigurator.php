<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Security;

/**
 * Applies `config/wordfence.php` to WordFence's own option store.
 *
 * WordFence configuration lives in the database and is normally set by hand in wp-admin. On an
 * immutable container that is rebuilt every deploy — and a staging database that is refreshed
 * from elsewhere — hand-set configuration has no durability and no audit trail. This makes the
 * repository the source of truth and re-asserts it on every deploy.
 *
 * Three properties matter, and each is load-bearing:
 *
 *  - **Idempotent.** A value already correct is not rewritten, so re-running costs reads and
 *    reports honestly that nothing changed. `rl:deploy` runs on every container start.
 *  - **Narrow.** Only keys named in the config are touched. Everything else stays under
 *    wp-admin control, so this does not quietly become the owner of all WordFence settings.
 *  - **Never fatal.** WordFence being absent, inactive, or mid-upgrade returns a skipped
 *    result rather than throwing. A security plugin's configuration is not worth failing a
 *    release over, and the deploy command reports the skip.
 *
 * Unknown keys are refused rather than written: `wfConfig::set()` will happily create a row for
 * a misspelled key that nothing ever reads, which looks exactly like success.
 */
class WordfenceConfigurator
{
    /** WordFence's config class. Checked by name so the theme never hard-depends on the plugin. */
    private const WF_CONFIG = '\wfConfig';

    /**
     * Apply the configured settings.
     *
     * @return array{applied: array<string, mixed>, unchanged: string[], unknown: string[], skipped: ?string}
     */
    public function apply(): array
    {
        $result = [
            'applied' => [],
            'unchanged' => [],
            'unknown' => [],
            'skipped' => null,
        ];

        if (! config('wordfence.apply_on_deploy', true)) {
            $result['skipped'] = 'Disabled by config (wordfence.apply_on_deploy).';

            return $result;
        }

        if (! $this->available()) {
            $result['skipped'] = 'WordFence is not loaded on this environment.';

            return $result;
        }

        /** @var array<string, mixed> $settings */
        $settings = config('wordfence.settings', []);

        foreach ($settings as $key => $desired) {
            if (! $this->isKnownKey($key)) {
                $result['unknown'][] = $key;

                continue;
            }

            $current = $this->get($key);

            if ($this->matches($current, $desired)) {
                $result['unchanged'][] = $key;

                continue;
            }

            $this->set($key, $desired);
            $result['applied'][$key] = $desired;
        }

        return $result;
    }

    /**
     * Report what `apply()` would change, without changing it.
     *
     * The Security screens surface this: a key that reads as drifted is either a hand-edit in
     * wp-admin that the next deploy will revert, or a deploy that never ran. Both are worth
     * seeing before the revert surprises someone, and neither is visible from WordFence's own
     * settings pages — they show the live value with nothing to say it is contested.
     *
     * Read-only on purpose. This runs while wp-admin renders; writing WordFence's option
     * store from a page render is the deploy command's job, not a dashboard widget's.
     *
     * @return array{matching: string[], drifted: list<array{key: string, current: mixed, desired: mixed}>, unknown: string[], skipped: ?string}
     */
    public function audit(): array
    {
        $result = [
            'matching' => [],
            'drifted' => [],
            'unknown' => [],
            'skipped' => null,
        ];

        if (! $this->available()) {
            $result['skipped'] = 'WordFence is not loaded on this environment.';

            return $result;
        }

        /** @var array<string, mixed> $settings */
        $settings = config('wordfence.settings', []);

        foreach ($settings as $key => $desired) {
            if (! $this->isKnownKey($key)) {
                $result['unknown'][] = $key;

                continue;
            }

            $current = $this->get($key);

            if ($this->matches($current, $desired)) {
                $result['matching'][] = $key;

                continue;
            }

            $result['drifted'][] = [
                'key' => $key,
                'current' => $current,
                'desired' => $desired,
            ];
        }

        return $result;
    }

    /**
     * Is WordFence loaded and usable?
     */
    public function available(): bool
    {
        return class_exists(self::WF_CONFIG)
            && method_exists(self::WF_CONFIG, 'set')
            && method_exists(self::WF_CONFIG, 'get');
    }

    /**
     * Does WordFence recognise this key?
     *
     * `wfConfig::$defaultConfig` holds the shipped defaults keyed by section. A key absent
     * from it is either a typo or a setting from a different WordFence version, and writing
     * it would create a row nothing reads.
     *
     * When the defaults cannot be inspected the key is accepted — refusing every write
     * because the plugin's internals moved would be worse than writing a key that turns out
     * to be unused.
     */
    protected function isKnownKey(string $key): bool
    {
        $defaults = $this->defaults();

        if ($defaults === null) {
            return true;
        }

        return array_key_exists($key, $defaults);
    }

    /**
     * Flatten `wfConfig::$defaultConfig` to a key => default map, or null if unavailable.
     *
     * @return array<string, mixed>|null
     */
    protected function defaults(): ?array
    {
        if (! property_exists(self::WF_CONFIG, 'defaultConfig')) {
            return null;
        }

        /** @var array<string, mixed>|null $raw */
        $raw = self::WF_CONFIG::$defaultConfig ?? null;

        if (! is_array($raw)) {
            return null;
        }

        $flat = [];

        foreach ($raw as $section) {
            if (is_array($section)) {
                foreach ($section as $key => $value) {
                    $flat[$key] = $value;
                }
            }
        }

        return $flat === [] ? null : $flat;
    }

    /**
     * Compare loosely on purpose.
     *
     * WordFence stores booleans as '1'/'' and numbers as strings, so a strict comparison
     * against a typed config value would report every setting as changed on every deploy and
     * rewrite the whole set each time.
     */
    protected function matches(mixed $current, mixed $desired): bool
    {
        if (is_bool($desired)) {
            return (bool) $current === $desired;
        }

        if (is_int($desired) || is_float($desired)) {
            return is_numeric($current) && (float) $current === (float) $desired;
        }

        return (string) $current === (string) $desired;
    }

    protected function get(string $key): mixed
    {
        return self::WF_CONFIG::get($key);
    }

    protected function set(string $key, mixed $value): void
    {
        // WordFence persists booleans as '1'/'', not as PHP bools.
        if (is_bool($value)) {
            $value = $value ? '1' : '';
        }

        self::WF_CONFIG::set($key, $value);
    }
}
