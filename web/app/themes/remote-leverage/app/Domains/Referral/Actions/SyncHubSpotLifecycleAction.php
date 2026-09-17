<?php

declare(strict_types=1);

namespace App\Domains\Referral\Actions;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\HubSpotGateway;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\LeadSettingsService;
use App\Domains\Referral\Models\Referral;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Mirror HubSpot contact lifecycle stages onto referred leads, and fulfil the referral when
 * one reaches the closing stage.
 *
 * HubSpot is the system of record for where a deal stands; nothing here writes back to it.
 * The local mirror exists so the referrer portal can render a timeline and a staleness badge
 * without an API call per page view, and so "the deal closed" becomes an event this codebase
 * can act on rather than something an admin has to notice and click.
 *
 * **`applyStage()` is the seam.** Everything specific to polling lives in `execute()`; the
 * mapping from "this contact is now at stage X" to "the lead, its timeline and its referral
 * say so" is `applyStage()` alone. A HubSpot webhook receiver, once the portal is configured
 * to send one, calls that method and inherits every rule below — the fulfilment threshold, the
 * idempotency, the activity-log write — rather than reimplementing them and drifting.
 */
class SyncHubSpotLifecycleAction
{
    /**
     * Most leads one run will look at when the caller names no limit.
     *
     * A cap rather than "everything": this runs unattended against a rate-limited API, and an
     * unbounded run would both load the whole result set into memory and burn the hourly quota
     * in one tick. 500 is five batch calls. The ordering below guarantees the leads it skips
     * are the ones checked most recently, so the tail is picked up on the next tick rather
     * than starved.
     */
    public const DEFAULT_RUN_LIMIT = 500;

    public function __construct(
        protected HubSpotGateway $hubspot,
        protected LeadActivityLogger $activityLogger,
        protected FulfillReferralAction $fulfillReferral,
        protected LeadSettingsService $leadSettings,
    ) {}

    /**
     * Poll HubSpot for every lead attached to a still-open referral.
     *
     * @return array{checked: int, changed: int, fulfilled: int, unresolved: int}
     */
    public function execute(?int $limit = null): array
    {
        $settings = $this->leadSettings->get();
        $property = (string) ($settings['hubspot_lifecycle_property'] ?: 'lifecyclestage');

        $summary = ['checked' => 0, 'changed' => 0, 'fulfilled' => 0, 'unresolved' => 0];

        $leads = $this->pendingLeads($limit ?? self::DEFAULT_RUN_LIMIT)->get();

        foreach ($leads->chunk(HubSpotGateway::BATCH_READ_LIMIT) as $chunk) {
            /** @var array<string, Lead> $byContactId */
            $byContactId = $chunk->keyBy(fn (Lead $lead) => (string) $lead->hubspot_contact_id)->all();

            $results = $this->hubspot->fetchContactProperties(array_keys($byContactId), [$property]);

            foreach ($byContactId as $contactId => $lead) {
                $summary['checked']++;

                /*
                 * An id HubSpot did not return is "unknown", not "empty". Writing null here
                 * would record a lifecycle change to nothing — which reads on the dashboard as
                 * the deal regressing, and resets the staleness clock on a lead nobody touched.
                 */
                if (! array_key_exists($contactId, $results)) {
                    $summary['unresolved']++;

                    continue;
                }

                $stage = $results[$contactId][$property] ?? null;
                $stage = is_scalar($stage) ? trim((string) $stage) : '';

                $outcome = $this->applyStage($lead, $stage === '' ? null : $stage);

                if ($outcome['changed']) {
                    $summary['changed']++;
                }

                if ($outcome['fulfilled']) {
                    $summary['fulfilled']++;
                }
            }
        }

        return $summary;
    }

    /**
     * Record that a lead's HubSpot lifecycle stage is now `$stage`.
     *
     * Idempotent by design: calling it repeatedly with an unchanged stage touches only
     * `hubspot_lifecycle_synced_at` and writes no timeline entry. That is what makes it safe
     * for a webhook to call on every HubSpot property change, including the ones that did not
     * actually move the stage.
     *
     * @return array{changed: bool, fulfilled: bool}
     */
    public function applyStage(Lead $lead, ?string $stage): array
    {
        $previous = $lead->hubspot_lifecycle_stage;
        $changed = $stage !== null && $stage !== $previous;

        $lead->forceFill(array_filter([
            'hubspot_lifecycle_stage' => $changed ? $stage : null,
            /*
             * Only moves when the stage itself moves. This is the staleness clock, so
             * refreshing it on an unchanged poll would mean nothing ever went stale — the
             * hourly cron would reset every lead's timer before the threshold could elapse.
             */
            'hubspot_lifecycle_changed_at' => $changed ? now() : null,
            // Always moves. Distinguishes "nothing has happened for 5 days" from "the sync has
            // been broken for 5 days"; only the first is the referrer's problem.
            'hubspot_lifecycle_synced_at' => now(),
        ], static fn ($value) => $value !== null))->saveQuietly();

        if (! $changed) {
            return ['changed' => false, 'fulfilled' => false];
        }

        $this->activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'HubSpotLifecycleChanged',
            actorDomain: 'Referral',
            outcome: 'succeeded',
            description: sprintf(
                'HubSpot lifecycle stage moved from %s to %s',
                $previous ?: 'unset',
                $stage,
            ),
            payload: ['from' => $previous, 'to' => $stage],
        );

        return ['changed' => true, 'fulfilled' => $this->maybeFulfill($lead, $stage)];
    }

    /**
     * Close and reward the referral attached to this lead, if the stage says the deal closed.
     */
    protected function maybeFulfill(Lead $lead, string $stage): bool
    {
        $fulfilledValue = strtolower(trim((string) (
            $this->leadSettings->get()['hubspot_lifecycle_fulfilled_value'] ?: 'customer'
        )));

        if ($fulfilledValue === '' || strtolower($stage) !== $fulfilledValue) {
            return false;
        }

        $referral = Referral::query()->where('lead_id', $lead->id)->orderBy('id')->first();

        if (! $referral) {
            return false;
        }

        /*
         * Already closed: nothing to do, and re-running would be the second place a reward
         * could be created. FulfillReferralAction is itself idempotent, but returning early
         * also keeps a duplicate "deal closed" entry out of the referrer's timeline.
         */
        if (in_array($referral->status, Referral::FULFILLED_STATUSES, true)) {
            return false;
        }

        // A referral reversed after a cancellation stays reversed. Re-opening it from a CRM
        // stage change would silently undo an ops decision.
        if ($referral->status === 'rejected') {
            return false;
        }

        try {
            $referral->update(['status' => 'fulfilled']);
            $this->fulfillReferral->execute($referral);
        } catch (\Throwable $e) {
            Log::error("SyncHubSpotLifecycleAction: could not fulfil referral #{$referral->id}: ".$e->getMessage());

            $this->activityLogger->logConsumption(
                leadId: $lead->id,
                eventType: 'HubSpotLifecycleChanged',
                actorDomain: 'Referral',
                outcome: 'failed',
                description: 'Reached the closing stage but the referral could not be fulfilled: '.$e->getMessage(),
                payload: ['referral_id' => $referral->id],
            );

            return false;
        }

        $this->activityLogger->logConsumption(
            leadId: $lead->id,
            eventType: 'ReferralFulfilled',
            actorDomain: 'Referral',
            outcome: 'succeeded',
            description: "Deal closed in HubSpot — referral #{$referral->id} fulfilled and reward created",
            payload: ['referral_id' => $referral->id, 'stage' => $stage],
        );

        return true;
    }

    /**
     * Leads worth polling: attached to a referral that can still move, and known to HubSpot.
     *
     * Scoped to referred leads on purpose. This runs on a schedule against a rate-limited API,
     * and the portal only ever renders lifecycle for referrals — polling the whole lead table
     * would spend the quota on rows nothing reads.
     */
    protected function pendingLeads(int $limit): Builder
    {
        return Lead::query()
            ->whereNotNull('hubspot_contact_id')
            ->where('hubspot_contact_id', '<>', '')
            ->whereIn('id', Referral::query()
                ->whereNotNull('lead_id')
                ->whereNotIn('status', ['rewarded', 'rejected'])
                ->select('lead_id'))
            // Longest un-synced first, so a limit-capped run cannot starve the same tail of
            // leads on every tick.
            ->orderByRaw('hubspot_lifecycle_synced_at IS NULL DESC')
            ->orderBy('hubspot_lifecycle_synced_at')
            ->limit($limit);
    }
}
