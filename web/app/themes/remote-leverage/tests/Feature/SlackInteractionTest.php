<?php

declare(strict_types=1);

use App\Application\Http\Controllers\SlackInteractionController;
use App\Application\Http\Support\SlackSignature;
use App\Domains\Lead\Actions\BlockLeadProfileAction;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/*
 * The action buttons on a lead alert.
 *
 * This endpoint can block a person and change a lead's status from an HTTP POST, which puts it
 * in the same class as the Stripe and Calendly webhooks: the signature is the only thing
 * standing between a channel button and anybody on the internet. The first three tests pin the
 * refusal, and they matter more than the rest of the file.
 */

/** A transport that records rather than posts. */
function recordingSlackTransport(): SlackTransport
{
    return new class extends SlackTransport
    {
        /** @var array<int, array{text: string, thread_ts: ?string, broadcast: bool}> */
        public array $posts = [];

        public function post(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->posts[] = ['text' => $text, 'thread_ts' => $threadTs, 'broadcast' => $broadcast];

            return ['ts' => '1726500001.000200', 'channel' => 'C086BBKUXL5'];
        }
    };
}

function slackController(?SlackTransport $transport = null): SlackInteractionController
{
    return new SlackInteractionController(
        new LeadActivityLogger,
        new BlockLeadProfileAction,
        $transport ?? recordingSlackTransport(),
    );
}

/**
 * A button press, signed the way Slack signs one.
 *
 * The body goes in as a raw url-encoded string AND as parameters: Slack signs the bytes it
 * sent, so verifying against a re-encoded form body would "verify" something Slack never wrote.
 */
function slackInteraction(
    string $actionId,
    int $leadId,
    string $secret,
    ?int $timestamp = null,
    ?string $signWith = null,
    string $responseUrl = '',
): Request {
    $payload = json_encode([
        'type' => 'block_actions',
        'user' => ['id' => 'U0SALES01', 'username' => 'ana', 'name' => 'ana'],
        'channel' => ['id' => 'C086BBKUXL5'],
        'response_url' => $responseUrl,
        'actions' => [[
            'type' => 'button',
            'action_id' => $actionId,
            'value' => (string) $leadId,
        ]],
    ], JSON_THROW_ON_ERROR);

    $body = 'payload='.urlencode($payload);
    $timestamp ??= time();

    return Request::create('/api/webhooks/slack/interactions', 'POST', ['payload' => $payload], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SLACK_SIGNATURE' => SlackSignature::sign($body, $signWith ?? $secret, $timestamp),
    ], $body);
}

function interactionLead(array $attributes = []): Lead
{
    return Lead::query()->create(array_merge([
        'uuid' => (string) Str::uuid(),
        'name' => 'Katherine Johnson',
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'email' => 'katherine-'.Str::random(6).'@example.com',
        'status' => 'captured',
        'slack_message_ts' => '1726500000.000100',
        'slack_channel_id' => 'C086BBKUXL5',
    ], $attributes));
}

describe('the interaction endpoint fails closed', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('refuses with 503 when no signing secret is configured', function () {
        config(['services.slack.signing_secret' => '']);

        $lead = interactionLead();
        $response = slackController()->handle(slackInteraction('lead_block', (int) $lead->id, 'anything'));

        expect($response->getStatusCode())->toBe(503);

        // The refusal is total: nothing in the body is acted on.
        $lead->refresh();
        expect($lead->is_blocked)->toBeFalse();
    });

    test('rejects a forged signature with 403 and leaves the lead untouched', function () {
        config(['services.slack.signing_secret' => 'the_real_secret']);

        $lead = interactionLead();
        $response = slackController()->handle(
            slackInteraction('lead_block', (int) $lead->id, 'the_real_secret', signWith: 'the_attackers_guess')
        );

        expect($response->getStatusCode())->toBe(403);

        $lead->refresh();
        expect($lead->is_blocked)->toBeFalse();
    });

    test('rejects a replayed request, however well signed', function () {
        // A correctly signed request captured off the wire is still valid forever without this.
        config(['services.slack.signing_secret' => 'the_real_secret']);

        $lead = interactionLead();
        $response = slackController()->handle(
            slackInteraction('lead_block', (int) $lead->id, 'the_real_secret', timestamp: time() - 3600)
        );

        expect($response->getStatusCode())->toBe(403);

        $lead->refresh();
        expect($lead->is_blocked)->toBeFalse();
    });
});

describe('the buttons do what they say', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
        config(['services.slack.signing_secret' => 'the_real_secret']);
    });

    test('Mark contacted moves the lead and says so in its thread', function () {
        $lead = interactionLead();
        $transport = recordingSlackTransport();

        $response = slackController($transport)->handle(
            slackInteraction('lead_contacted', (int) $lead->id, 'the_real_secret')
        );

        expect($response->getStatusCode())->toBe(200);

        $lead->refresh();
        expect($lead->status)->toBe('contacted');

        // The confirmation goes under the lead's own alert, quietly.
        expect($transport->posts)->toHaveCount(1)
            ->and($transport->posts[0]['thread_ts'])->toBe('1726500000.000100')
            ->and($transport->posts[0]['broadcast'])->toBeFalse()
            ->and($transport->posts[0]['text'])->toContain('<@U0SALES01>');
    });

    test('Mark contacted does not walk a booked lead backwards', function () {
        // Pressing it on a lead that already booked is a mis-tap, and honouring it would lose
        // the one status the pipeline reports on.
        $lead = interactionLead(['status' => 'booked']);
        $transport = recordingSlackTransport();

        slackController($transport)->handle(
            slackInteraction('lead_contacted', (int) $lead->id, 'the_real_secret')
        );

        $lead->refresh();
        expect($lead->status)->toBe('booked')
            ->and($transport->posts)->toBe([]);
    });

    test('Claim records who took it without inventing a column for it', function () {
        $lead = interactionLead();
        $transport = recordingSlackTransport();

        slackController($transport)->handle(
            slackInteraction('lead_claim', (int) $lead->id, 'the_real_secret')
        );

        $lead->refresh();

        // A claim is a moment, not a property: the status is untouched and the activity log is
        // where it lives.
        expect($lead->status)->toBe('captured');

        $logged = LeadActivityLog::query()
            ->where('lead_id', $lead->id)
            ->where('event_type', 'LeadClaimed')
            ->first();

        expect($logged)->not->toBeNull()
            ->and($logged->actor_domain)->toBe('Slack')
            ->and($logged->payload['slack_user_name'] ?? null)->toBe('ana');
    });

    test('Block blocks the person and broadcasts it to the channel', function () {
        $lead = interactionLead();
        $transport = recordingSlackTransport();

        slackController($transport)->handle(
            slackInteraction('lead_block', (int) $lead->id, 'the_real_secret')
        );

        $lead->refresh();
        expect($lead->is_blocked)->toBeTrue();

        // A moderation decision the rest of the team can see and question.
        expect($transport->posts[0]['broadcast'])->toBeTrue()
            ->and($transport->posts[0]['thread_ts'])->toBe('1726500000.000100');
    });

    test('blocking an already blocked lead changes nothing and posts nothing', function () {
        $lead = interactionLead();
        $transport = recordingSlackTransport();
        $controller = slackController($transport);

        $controller->handle(slackInteraction('lead_block', (int) $lead->id, 'the_real_secret'));
        $controller->handle(slackInteraction('lead_block', (int) $lead->id, 'the_real_secret'));

        // A second press must not post a second "blocked" notice to the whole channel.
        expect($transport->posts)->toHaveCount(1);
    });

    test('claiming a blocked lead is refused rather than quietly recorded', function () {
        // A blocked profile has nothing to work. Logging a claim against one would put a real
        // salesperson's name on a lead that is never going to reach them.
        $lead = interactionLead();
        $lead->forceFill(['is_blocked' => true])->saveQuietly();

        $transport = recordingSlackTransport();

        slackController($transport)->handle(
            slackInteraction('lead_claim', (int) $lead->id, 'the_real_secret')
        );

        expect($transport->posts)->toBe([])
            ->and(LeadActivityLog::query()
                ->where('lead_id', $lead->id)
                ->where('event_type', 'LeadClaimed')
                ->count())->toBe(0);
    });

    test('an action on a lead that no longer exists is survivable', function () {
        $transport = recordingSlackTransport();

        $response = slackController($transport)->handle(
            slackInteraction('lead_block', 999999, 'the_real_secret')
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($transport->posts)->toBe([]);
    });

    test('an unknown action_id is acknowledged and ignored', function () {
        // Slack retries anything that is not a 200, so an unrecognised button must not 500 its
        // way into a retry loop.
        $lead = interactionLead();
        $transport = recordingSlackTransport();

        $response = slackController($transport)->handle(
            slackInteraction('lead_teleport', (int) $lead->id, 'the_real_secret')
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($transport->posts)->toBe([]);
    });
});
