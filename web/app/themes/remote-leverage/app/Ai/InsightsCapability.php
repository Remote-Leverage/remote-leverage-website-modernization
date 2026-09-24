<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Gates read access to real enquiry data (rl_leads and friends).
 *
 * Deliberately separate from edit_pages, which the MCP content-agent user
 * holds: composing a landing page and reading the contact details of everyone
 * who filled in a form are different powers, and the second one is customer
 * PII. A team that wants the content workflow should not have to hand over the
 * lead database to get it, so this capability is granted per user, on purpose,
 * with `wp acorn rl:ai:grant-insights`.
 *
 * DatasetRegistry already classifies leads as purge-only — never copied
 * between environments. This keeps the same posture for reads: the data can be
 * queried where it lives, never transferred.
 *
 * With one deliberate exception: the data team's export
 * (App\Domains\Lead\Api\LeadDataRestRoutes) hands full rows, contact details
 * included, to a warehouse outside this site. It is gated on this same
 * capability, held by a dedicated `data-api` user whose credentials are issued
 * per consumer from Settings → Data API, and every pull is logged.
 */
final class InsightsCapability
{
    public const NAME = 'rl_read_business_data';

    public static function currentUserCan(): bool
    {
        return current_user_can(self::NAME);
    }
}
