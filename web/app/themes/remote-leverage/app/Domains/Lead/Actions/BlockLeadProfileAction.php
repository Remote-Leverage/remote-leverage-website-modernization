<?php

declare(strict_types=1);

namespace App\Domains\Lead\Actions;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadProfile;
use App\Domains\Lead\Services\IdentityResolver;
use Illuminate\Support\Facades\DB;

/**
 * Block or unblock a person, across every identifier they have used.
 *
 * Deliberately a human action rather than something the identity graph does on its own.
 * `IdentityResolver` links and never bans, because linking can be poisoned: someone already
 * blocked can enter a competitor's phone number or a victim's email, and a graph that banned
 * automatically would do that work for them. The graph is evidence; this is the decision.
 *
 * Blocking is applied to the **profile**, which is what makes it cover identifiers attached
 * later — a new address seen alongside a blocked phone is blocked the moment it is attached,
 * with no second action.
 */
class BlockLeadProfileAction
{
    public function __construct(
        protected IdentityResolver $resolver = new IdentityResolver,
    ) {}

    /**
     * Block a profile and every lead attached to it.
     */
    public function block(LeadProfile $profile, string $reason = '', ?string $actor = null): LeadProfile
    {
        return $this->apply($profile, blocked: true, reason: $reason, actor: $actor);
    }

    public function unblock(LeadProfile $profile, ?string $actor = null): LeadProfile
    {
        return $this->apply($profile, blocked: false, reason: '', actor: $actor);
    }

    /**
     * Block the person behind a given lead, resolving their profile first.
     *
     * The entry point the admin uses: someone looking at an abusive submission is looking at a
     * lead, not at a profile, and should not have to find one.
     */
    public function blockLead(Lead $lead, string $reason = '', ?string $actor = null): ?LeadProfile
    {
        $profile = $lead->profile_id
            ? LeadProfile::query()->find($lead->profile_id)?->canonical()
            : $this->resolver->resolve($lead);

        return $profile ? $this->block($profile, $reason, $actor) : null;
    }

    protected function apply(LeadProfile $profile, bool $blocked, string $reason, ?string $actor): LeadProfile
    {
        $profile = $profile->canonical();

        DB::transaction(function () use ($profile, $blocked, $reason, $actor) {
            $profile->forceFill([
                'status' => $blocked ? 'blocked' : 'active',
                'blocked_at' => $blocked ? now() : null,
                'blocked_by' => $blocked ? ($actor ?: $this->currentActor()) : null,
                'block_reason' => $blocked ? trim($reason) : null,
            ])->save();

            /*
             * Denormalised onto every lead in one statement. The listeners that suppress read
             * this column rather than joining to the profile on each event, so it has to move
             * with the profile or a blocked person keeps reaching Slack until their next
             * submission re-resolves them.
             */
            Lead::query()
                ->where('profile_id', $profile->id)
                ->update(['is_blocked' => $blocked]);

            // Merged-away profiles point here; keep their leads consistent too.
            $mergedIds = LeadProfile::query()
                ->where('merged_into_id', $profile->id)
                ->pluck('id');

            if ($mergedIds->isNotEmpty()) {
                Lead::query()
                    ->whereIn('profile_id', $mergedIds)
                    ->update(['is_blocked' => $blocked]);
            }
        });

        return $profile->refresh();
    }

    /**
     * Who is doing this, for the audit trail.
     */
    protected function currentActor(): string
    {
        if (function_exists('wp_get_current_user')) {
            $user = \wp_get_current_user();

            if ($user && ! empty($user->user_login)) {
                return (string) $user->user_login;
            }
        }

        return 'system';
    }
}
