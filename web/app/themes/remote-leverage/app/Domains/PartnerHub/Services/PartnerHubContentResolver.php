<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Services;

/**
 * Resolves the per-partner content overrides that sit in front of
 * PartnerHubGlobalData's defaults.
 *
 * The legacy rl-partners-hub plugin resolved these inline in its template via
 * RL_Global_Data::get_field(). The v2 port originally shipped globals only, so
 * a partner could not vary its service catalogue, referral pipeline, target
 * industries, or the two overview/ICP callouts. Kept out of the Blade template
 * (as with PartnerHubTabResolver) so the fallback rules are unit-testable
 * without rendering a view.
 *
 * Every rule here is "a non-empty override replaces the default wholesale" —
 * overrides never merge into or append to the global list.
 */
class PartnerHubContentResolver
{
    /**
     * Resolve a repeater-style override (services, lifecycle stages) against
     * its global default.
     *
     * ACF returns `false` for an untouched repeater and `[]` for one whose
     * rows were all removed; both mean "use the default".
     *
     * @param  mixed  $override  raw `get_field()` / `get_post_meta()` value
     * @param  array<int, array<string, mixed>>  $default
     * @return array<int, array<string, mixed>>
     */
    public static function resolveRows(mixed $override, array $default): array
    {
        if (! is_array($override) || $override === []) {
            return $default;
        }

        return array_values($override);
    }

    /**
     * Resolve a plain-text override (a section subtitle, a callout body).
     *
     * @param  mixed  $override  raw meta value
     */
    public static function resolveText(mixed $override, string $default): string
    {
        if (! is_string($override)) {
            return $default;
        }

        $trimmed = trim($override);

        return $trimmed === '' ? $default : $trimmed;
    }

    /**
     * Resolve a newline-separated list override (target industries, referral
     * rules) against its global default.
     *
     * Accepts either the newline-separated string an ACF textarea stores or
     * the array the legacy plugin wrote, so partner rows imported from v1 keep
     * working. Blank lines and surrounding whitespace are discarded.
     *
     * @param  mixed  $override  raw meta value
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    public static function resolveList(mixed $override, array $default): array
    {
        if (is_array($override)) {
            $lines = $override;
        } elseif (is_string($override)) {
            $lines = preg_split('/\R/', $override) ?: [];
        } else {
            return $default;
        }

        $lines = array_values(array_filter(
            array_map(static fn ($line) => trim((string) $line), $lines),
            static fn (string $line) => $line !== '',
        ));

        return $lines === [] ? $default : $lines;
    }

    /**
     * Resolve the page's fixed strings against their defaults, then replace the `{partner}` /
     * `{code}` style tokens in whichever text won.
     *
     * @param  array<string, string>  $defaults  key => default (PartnerHubGlobalData::getCopyDefaults())
     * @param  array<string, mixed>  $overrides  key => raw meta value
     * @param  array<string, string>  $tokens  e.g. ['{partner}' => 'Oyster']
     * @return array<string, string>
     */
    public static function resolveCopy(array $defaults, array $overrides, array $tokens = []): array
    {
        $copy = [];
        foreach ($defaults as $key => $default) {
            $copy[$key] = self::interpolate(self::resolveText($overrides[$key] ?? null, $default), $tokens);
        }

        return $copy;
    }

    /**
     * Replace `{token}` placeholders in an editor- or code-authored string.
     *
     * @param  array<string, string>  $tokens
     */
    public static function interpolate(string $text, array $tokens): string
    {
        return $tokens === [] ? $text : strtr($text, $tokens);
    }

    /**
     * Resolve the case-study repeater. Its challenge / solution / outcome sub-fields are
     * textareas holding one bullet per line, while the defaults hold arrays, so a custom row is
     * normalised to the default's shape. A row with neither an industry nor a client is dropped.
     *
     * @param  mixed  $override  raw `get_field()` value
     * @param  array<int, array<string, mixed>>  $default
     * @return array<int, array{industry: string, client: string, challenge: array<int, string>, solution: array<int, string>, outcome: array<int, string>}>
     */
    public static function resolveCaseStudies(mixed $override, array $default): array
    {
        $rows = self::resolveRows($override, []);
        if ($rows === []) {
            return $default;
        }

        $studies = [];
        foreach ($rows as $row) {
            $industry = trim((string) ($row['industry'] ?? ''));
            $client = trim((string) ($row['client'] ?? ''));
            if ($industry === '' && $client === '') {
                continue;
            }

            $studies[] = [
                'industry' => $industry,
                'client' => $client,
                'challenge' => self::resolveList($row['challenge'] ?? null, []),
                'solution' => self::resolveList($row['solution'] ?? null, []),
                'outcome' => self::resolveList($row['outcome'] ?? null, []),
            ];
        }

        return $studies === [] ? $default : $studies;
    }
}
