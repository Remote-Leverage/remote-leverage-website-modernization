<?php

declare(strict_types=1);

namespace App\Application\Http\Controllers;

use App\Application\Http\Support\SlackSignature;
use App\Domains\Lead\Actions\BlockLeadProfileAction;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\SlackMessageRenderer;
use App\Infrastructure\Slack\SlackCredentials;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The buttons on a lead alert, handled.
 *
 * Until this existed the alert's buttons were links only, and Slack rendered "this app is not
 * configured to handle interactive responses" beside anything else — so acting on a lead meant
 * leaving the channel for wp-admin, which is the friction that made the alert a thing people
 * read rather than a thing people use.
 *
 * ## Authorisation is channel membership
 *
 * Anyone who can see the message can press the button, and this endpoint does not check further.
 * That is deliberate and it is the model Slack itself offers: the lead channel is already the
 * list of people trusted with every lead's name, phone number and session replay, and a
 * second permission system layered on top would be a second place to forget someone.
 *
 * What it does check is that the request came from Slack at all — see `SlackSignature`. The
 * button's `value` is a lead id, and it is trustworthy for exactly that reason: it arrives
 * inside the signed body. Everything else about the lead is read from the database rather than
 * taken from the payload, so a replayed interaction cannot assert facts, only repeat an action.
 *
 * ## Timing
 *
 * Slack wants a 200 within three seconds and shows the operator a warning otherwise. The work
 * here is one select, one update, one log row and one `chat.postMessage`, which lands well
 * inside that; it is done inline rather than deferred so that a failure is still a failure
 * the caller can see, rather than a silent no-op after a cheerful 200.
 */
class SlackInteractionController
{
    /**
     * The actions this endpoint knows, and what each one is called in the audit trail.
     */
    private const ACTIONS = [
        'lead_claim' => 'LeadClaimed',
        'lead_contacted' => 'LeadContacted',
        'lead_block' => 'LeadBlocked',
    ];

    public function __construct(
        protected LeadActivityLogger $activityLogger,
        protected BlockLeadProfileAction $blockAction = new BlockLeadProfileAction,
        protected SlackTransport $transport = new SlackTransport,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // Environment first, admin setting second: staging is wired by environment sync
        // rather than by a task-definition change. See SlackCredentials.
        $secret = SlackCredentials::signingSecret();

        if ($secret === '') {
            /*
             * Fails closed, like the Stripe and Calendly endpoints. This one can block a person
             * and change a lead's status, so an unsigned payload is enough to do real damage —
             * and an unconfigured environment should not be quietly accepting them.
             */
            Log::critical('Slack interactions: refused, no signing secret configured (SLACK_SIGNING_SECRET).');

            return response()->json(['error' => 'Slack signing secret is not configured.'], 503);
        }

        $error = SlackSignature::verify(
            $request->getContent(),
            (string) $request->header(SlackSignature::TIMESTAMP_HEADER, ''),
            (string) $request->header(SlackSignature::SIGNATURE_HEADER, ''),
            $secret,
        );

        if ($error !== null) {
            Log::error('Slack interactions: signature verification failed', ['error' => $error]);

            return response()->json(['error' => 'Signature verification failed.'], 403);
        }

        $payload = json_decode((string) $request->input('payload', ''), true);

        if (! is_array($payload)) {
            return response()->json(['error' => 'Malformed interaction payload.'], 400);
        }

        // Slack posts several interaction types to the one URL. Anything that is not a button
        // press is acknowledged and ignored rather than treated as an error.
        if (($payload['type'] ?? '') !== 'block_actions') {
            return response()->json([]);
        }

        return $this->act($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function act(array $payload): JsonResponse
    {
        $action = $payload['actions'][0] ?? [];
        $actionId = (string) ($action['action_id'] ?? '');
        $responseUrl = (string) ($payload['response_url'] ?? '');

        if (! isset(self::ACTIONS[$actionId])) {
            return response()->json([]);
        }

        $lead = Lead::query()->find((int) ($action['value'] ?? 0));

        if (! $lead) {
            /*
             * A lead that was purged, or an alert older than the retention window. Told to the
             * person who clicked and nobody else — the channel does not need to watch someone
             * press a button on a lead that is gone.
             */
            $this->tellTheOperator($responseUrl, 'That lead no longer exists. It may have been purged.');

            return response()->json([]);
        }

        $actor = $this->actor($payload);

        /*
         * Each action answers with what it did, or with a sentence saying why it did nothing.
         * The distinction matters to whoever pressed the button: "already blocked" is a shrug,
         * "could not identify this person" is something they need to go and look at, and
         * collapsing both into one message would hide the second behind the first.
         */
        $outcome = match ($actionId) {
            'lead_claim' => $this->claim($lead, $actor),
            'lead_contacted' => $this->markContacted($lead, $actor),
            'lead_block' => $this->block($lead, $actor),
            default => 'That button is not one this app knows about.',
        };

        if (is_string($outcome)) {
            $this->tellTheOperator($responseUrl, $outcome);

            return response()->json([]);
        }

        $this->activityLogger->logConsumption(
            leadId: (int) $lead->id,
            eventType: self::ACTIONS[$actionId],
            actorDomain: 'Slack',
            outcome: 'succeeded',
            description: $outcome['description'],
            payload: [
                'slack_user_id' => $payload['user']['id'] ?? null,
                'slack_user_name' => $payload['user']['username'] ?? ($payload['user']['name'] ?? null),
                'action_id' => $actionId,
            ],
        );

        $this->replyInThread($lead, $outcome['template'], $actor);

        return response()->json([]);
    }

    /**
     * Claim a lead.
     *
     * Recorded in the activity log rather than in a column on the lead. A claim is a statement
     * about a moment ("Ana took this at 10:42"), not a property of the lead, and the log is
     * already what the portal's timeline renders — a column would have to be kept in step with
     * it and would still not say when, or who before.
     *
     * @return array{description: string, template: string}|string An outcome, or why there was none.
     */
    protected function claim(Lead $lead, string $actor): array|string
    {
        if ($lead->is_blocked === true) {
            return 'That person is blocked, so there is nothing to work.';
        }

        return [
            'description' => "Claimed in Slack by {$actor}",
            'template' => 'lead_claimed',
        ];
    }

    /**
     * @return array{description: string, template: string}|string An outcome, or why there was none.
     */
    protected function markContacted(Lead $lead, string $actor): array|string
    {
        /*
         * Does not overwrite a booking. Someone marking a booked lead "contacted" is pressing
         * the wrong button, and letting it walk the status backwards would lose the one state
         * the pipeline reports on.
         */
        if ((string) $lead->status === 'booked') {
            return 'That lead has already booked, which is further along than contacted.';
        }

        if ((string) $lead->status === 'contacted') {
            return 'That lead was already marked contacted.';
        }

        $lead->forceFill(['status' => 'contacted'])->saveQuietly();

        return [
            'description' => "Marked contacted in Slack by {$actor}",
            'template' => 'lead_contacted',
        ];
    }

    /**
     * @return array{description: string, template: string}|string An outcome, or why there was none.
     */
    protected function block(Lead $lead, string $actor): array|string
    {
        if ($lead->is_blocked === true) {
            return 'That person is already blocked.';
        }

        $profile = $this->blockAction->blockLead($lead, "Blocked from Slack by {$actor}", $actor);

        if (! $profile) {
            /*
             * No identity profile could be resolved, so there is nothing to attach the block to
             * — a lead with no email, phone or device id. Saying "already blocked" here would
             * report a silent failure as a success, and the person would keep coming back.
             */
            return 'Could not identify this person well enough to block them. Block them in the portal instead.';
        }

        // The action updates every lead on the profile in one statement, so this instance is
        // stale the moment it returns.
        $lead->refresh();

        return [
            'description' => "Blocked in Slack by {$actor}",
            'template' => 'lead_blocked',
        ];
    }

    /**
     * Post the confirmation under the lead's own alert.
     *
     * In the thread rather than the channel because it is an answer to a message that is already
     * there — and because the alternative is a channel where every click costs everyone a line.
     */
    protected function replyInThread(Lead $lead, string $template, string $actor): void
    {
        $threadTs = trim((string) $lead->slack_message_ts);

        $rendered = app(SlackMessageRenderer::class)->render($template, [
            'actor' => $actor,
            'name' => trim(($lead->first_name ?: $lead->name).' '.(string) $lead->last_name),
            'email' => (string) $lead->email,
            'admin_url' => $lead->id && function_exists('admin_url')
                ? (string) \admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)
                : '',
        ]);

        $this->transport->post(
            $rendered['text'],
            $rendered['blocks'],
            $rendered['color'] ?? null,
            $threadTs !== '' ? $threadTs : null,

            /*
             * A block is a moderation decision the channel should see; a claim and a contact are
             * bookkeeping between the people already in the thread.
             */
            broadcast: $template === 'lead_blocked' && $threadTs !== '',
        );
    }

    /**
     * Who pressed it, for the audit trail and the confirmation line.
     *
     * Prefers the Slack user id in mention form, so the confirmation names a person Slack can
     * resolve rather than a handle that may since have changed.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function actor(array $payload): string
    {
        $id = (string) ($payload['user']['id'] ?? '');

        if ($id !== '') {
            return "<@{$id}>";
        }

        return (string) ($payload['user']['username'] ?? ($payload['user']['name'] ?? 'someone in Slack'));
    }

    /**
     * Say something to the person who clicked and to nobody else.
     *
     * `response_url` takes an ephemeral reply with no scope and no bot token, which is the only
     * way to report a non-event — "already blocked", "lead is gone" — without posting it.
     */
    protected function tellTheOperator(string $responseUrl, string $text): void
    {
        if ($responseUrl === '') {
            return;
        }

        try {
            Http::timeout(5)->post($responseUrl, [
                'response_type' => 'ephemeral',
                'replace_original' => false,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Slack interactions: could not deliver ephemeral reply: '.$e->getMessage());
        }
    }
}
