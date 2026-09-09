<?php

declare(strict_types=1);

namespace App\Domains\PartnerHub\Services;

/**
 * Resolves which tabs are visible on a partner hub page and which one is
 * active, given the raw `_rl_enable_comarketing` post meta and the requested
 * tab. Extracted out of the Blade template (WR-119) so this logic is
 * unit-testable without rendering a view.
 */
class PartnerHubTabResolver
{
    /**
     * Whether the co-marketing tab should be shown, given the raw
     * `_rl_enable_comarketing` post meta value (defaults to enabled, matching
     * the legacy plugin: only an explicit "0" turns it off).
     */
    public static function isComarketingEnabled(string $rawMetaValue): bool
    {
        return $rawMetaValue !== '0';
    }

    /**
     * Build the ordered tab map for a partner hub page.
     *
     * @return array<string, string> tab key => label
     */
    public static function resolveTabs(bool $hasComarketing): array
    {
        $tabs = [
            'overview' => 'Overview & Actions',
            'icp' => 'Ideal Client Profile',
            'services' => 'Services Overview',
            'why-rl' => 'Why Remote Leverage',
            'referral-program' => 'Referral Program & Fees',
        ];

        if ($hasComarketing) {
            $tabs['comarketing'] = 'Co-Marketing';
        }

        $tabs['case-studies'] = 'Case Studies';
        $tabs['faq'] = 'Partner FAQ';
        $tabs['contact'] = 'Contact Team';

        return $tabs;
    }

    /**
     * Resolve the active tab key, falling back to "overview" for an unknown
     * or disabled (e.g. comarketing-off) tab request.
     *
     * @param  array<string, string>  $validTabs
     */
    public static function resolveCurrentTab(string $rawTab, array $validTabs): string
    {
        return array_key_exists($rawTab, $validTabs) ? $rawTab : 'overview';
    }
}
