<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadBookingCompleted;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Referral\Models\Referrer;

/*
 * The "NEW LEAD" Slack alert.
 *
 * Ported from the Gravity Forms Slack feed, whose condition is `submission_type is not Final`.
 * That detail is the point of these tests: `LeadCreated` fires for BOTH the step-1 partial
 * capture and the completed booking, so without the guard the team gets two alerts per lead —
 * and the alert that matters is the first one, which includes the people who never finish.
 */

/** A listener that records what it would send instead of reaching Slack. */
function slackListener(): object
{
    return new class(app(LeadActivityLogger::class)) extends HandleLeadEventsForSlack
    {
        public array $sent = [];

        /** @var array<int, array<int, array<string, mixed>>> */
        public array $sentBlocks = [];

        public ?string $sentColor = null;

        /** @var array<int, ?string> */
        public array $sentThreadTs = [];

        /** @var array<int, bool> */
        public array $sentBroadcast = [];

        protected function send(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
        ): ?array {
            $this->sent[] = $text;
            $this->sentBlocks[] = $blocks;
            $this->sentColor = $color;
            $this->sentThreadTs[] = $threadTs;
            $this->sentBroadcast[] = $broadcast;

            // What chat.postMessage answers with. The listener stores this on the lead so
            // later events can reply to it.
            return ['ts' => '1726500000.000100', 'channel' => 'C086BBKUXL5'];
        }
    };
}

function slackLead(array $attributes = []): Lead
{
    $lead = new Lead;

    $lead->forceFill(array_merge([
        'id' => 1,
        'name' => 'Ada Lovelace',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+1 650 555 0100',
        'monthly_revenue' => '$10k to $50k Per Month',
        'utm_source' => 'linkedin',
        'utm_campaign' => 'va-q4',
        'utm_medium' => 'cpc',
        'utm_content' => 'variant-b',
        'utm_term' => 'virtual assistant',
        'landing_page_base' => 'https://remoteleverage.com/hire-va-4/',
        'submission_type' => 'Partial',
    ], $attributes));

    return $lead;
}

describe('Slack lead alert', function () {
    // The layout now lives in config/slack-notifications.php so it can be designed rather than
    // coded. Load the file the application ships, so these assert against the real template.
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('fires on the partial submission', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        expect($listener->sent)->toHaveCount(1);
    });

    test('does NOT fire on the final submission', function () {
        // The GF feed's condition. Without it, every lead alerts twice.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(['submission_type' => 'Final']), context: []));

        expect($listener->sent)->toBe([]);
    });

    test('the suppression is case-insensitive', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(['submission_type' => 'final']), context: []));

        expect($listener->sent)->toBe([]);
    });

    test('a lead with no submission_type still alerts', function () {
        // Leads captured before submission_type existed, and any other capture path.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(['submission_type' => null]), context: []));

        expect($listener->sent)->toHaveCount(1);
    });

    test('the fallback text stands alone, for the notification preview', function () {
        // Slack shows this in the notification and on clients that cannot render blocks, so it
        // must carry the lead itself rather than pointing at the blocks.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        $message = $listener->sent[0];

        expect($message)->toContain('New lead from LinkedIn: Ada Lovelace')
            ->and($message)->toContain('ada@example.com')
            ->and($message)->toContain('$10k to $50k Per Month');
    });

    test('no emoji reaches Slack, anywhere in the payload', function () {
        // House rule. They read as unprofessional in a channel the sales team watches all day.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        $payload = $listener->sent[0].json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $payload))
            ->toBe(0);
    });

    test('the UTMs render labelled, in grey, without code styling', function () {
        // Backticks draw a chip outline but Slack renders inline code orange in dark themes and
        // mrkdwn cannot recolour it. A context block gives small grey text instead.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('source: linkedin')
            ->and($encoded)->toContain('medium: cpc')
            ->and($encoded)->toContain('campaign: va-q4')
            ->and($encoded)->not->toContain('`source: linkedin`');
    });

    test('the message is not boxed', function () {
        // An attachment colour draws a bar down the left; tried and reverted as too heavy.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        expect($listener->sentColor)->toBeNull();
    });

    test('a long click id is truncated so it cannot swamp the row', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'gclid' => str_repeat('A', 90),
        ]), context: []));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('gclid: ')
            ->and($encoded)->not->toContain(str_repeat('A', 90));
    });

    test('absent attribution is omitted rather than rendered as a wall of dashes', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
            'utm_content' => null,
            'utm_term' => null,
        ]), context: []));

        expect(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES))->not->toContain('*Attribution*');
    });

    test('all three destinations are offered when each exists', function () {
        config([
            'services.posthog.project_id' => '282594',
            'services.hubspot.portal_id' => '243484989',
        ]);

        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'posthog_session_id' => 'abc123',
            'hubspot_contact_id' => '55501',
        ]), context: []));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('Open in portal')
            ->and($encoded)->toContain('Watch session')
            ->and($encoded)->toContain('Open in HubSpot')
            ->and($encoded)->toContain('record/0-1/55501')
            ->and($encoded)->toContain('"type":"actions"');
    });

    test('a destination that does not exist yet is not offered', function () {
        // No recording and no CRM record — two buttons that would 404.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        expect($encoded)->toContain('Open in portal')
            ->and($encoded)->not->toContain('Watch session')
            ->and($encoded)->not->toContain('Open in HubSpot');
    });

    test('the landing page is linked without unfurling into a preview card', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'landing_page_base' => null,
            'landing_url' => 'https://remoteleverage.com/spanish/?utm_source=x',
        ]), context: []));

        // Angle-bracket link syntax; unfurl_links=false is asserted at the send() payload.
        expect(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES))
            ->toContain('<https://remoteleverage.com/spanish/?utm_source=x|');
    });

    test('a session-replay button appears only when there is a recording', function () {
        // A button that 404s costs a click and a moment of doubt about the tooling.
        config(['services.posthog.project_id' => '282594']);

        $without = slackListener();
        $without->handleCreated(new LeadCreated(lead: slackLead(), context: []));
        expect(json_encode($without->sentBlocks[0], JSON_UNESCAPED_SLASHES))->not->toContain('Watch session');

        $with = slackListener();
        $with->handleCreated(new LeadCreated(lead: slackLead(['posthog_session_id' => 'abc123']), context: []));
        expect(json_encode($with->sentBlocks[0], JSON_UNESCAPED_SLASHES))->toContain('Watch session')
            ->and(json_encode($with->sentBlocks[0], JSON_UNESCAPED_SLASHES))->toContain('replay/abc123');
    });

    test('a phone from outside the US and Canada adds a line of small print', function () {
        // So whoever picks the lead up knows to check before booking an hour for it.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'phone' => '+573115002018',
            'phone_country' => 'CO',
        ]), context: []));

        expect(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->toContain('Phone number is from CO');
    });

    test('an ordinary lead carries no such line', function () {
        // The alert is unchanged for everyone the offer is actually sold to.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(['phone_country' => 'US']), context: []));

        expect(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES))
            ->not->toContain('looking for VA work');
    });

    test('a referred lead is never called a possible VA', function () {
        // A referrer introduced them; the phone country says nothing about that.
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'phone' => '+573115002018',
            'phone_country' => 'CO',
            'source_type' => 'referral_hub',
            'referral_code' => 'adrian-salvatori-8oue',
        ]), context: []));

        expect(json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES))
            ->not->toContain('looking for VA work');
    });
});

describe('values that can be blank', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    test('a booked lead with no phone omits the field rather than heading a blank one', function () {
        config(['services.slack.notify_on_booking' => true]);

        $listener = slackListener();
        $lead = slackLead(['phone' => null, 'monthly_revenue' => null]);

        $listener->handleBookingCompleted(new LeadBookingCompleted($lead, 'meeting-1', 'calendly'));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        /*
         * The literal `*Phone*` label keeps the text object non-empty, so this was never fatal
         * — it rendered a bold heading with nothing under it, which reads as a bug. Leads from
         * gated downloads and instant-call requests have no phone, and both can go on to book.
         */
        expect($encoded)->not->toContain('*Phone*')
            ->and($encoded)->toContain('Call booked');
    });

    test('a lead with no name keeps its card instead of losing it entirely', function () {
        $listener = slackListener();

        $listener->handleCreated(new LeadCreated(
            lead: slackLead(['name' => '', 'first_name' => '', 'last_name' => '']),
            context: [],
        ));

        $encoded = json_encode($listener->sentBlocks[0], JSON_UNESCAPED_SLASHES);

        // The card title cannot be `_when` guarded, so an empty name used to cost the whole
        // card — name, revenue and contact details — leaving only the headline behind it.
        expect(collect($listener->sentBlocks[0])->pluck('type'))->toContain('card')
            ->and($encoded)->toContain('Unnamed lead')
            ->and($encoded)->not->toContain('"text":""');
    });
});

describe('lead headline', function () {
    beforeEach(function () {
        config(['slack-notifications' => require __DIR__.'/../../config/slack-notifications.php']);
    });

    function headlineFor(array $attributes): string
    {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead($attributes), context: []));

        return $listener->sent[0];
    }

    test('names the channel in words the team uses, not the raw utm_source', function () {
        // "fb", "facebook" and "meta" are one channel to a salesperson and three strings in
        // the data.
        expect(headlineFor(['utm_source' => 'fb', 'partner' => null]))->toContain('New lead from Facebook')
            ->and(headlineFor(['utm_source' => 'Meta', 'partner' => null]))->toContain('New lead from Facebook')
            ->and(headlineFor(['utm_source' => 'instagram', 'partner' => null]))->toContain('New lead from Instagram')
            ->and(headlineFor(['utm_source' => 'linkedin', 'partner' => null]))->toContain('New lead from LinkedIn');
    });

    test('distinguishes paid search from organic search', function () {
        expect(headlineFor(['utm_source' => 'google', 'utm_medium' => 'cpc', 'partner' => null]))
            ->toContain('New lead from Google Ads')
            ->and(headlineFor(['utm_source' => 'google', 'utm_medium' => 'organic', 'partner' => null]))
            ->toContain('New lead from Google');
    });

    test('a lead with no attribution reads as organic, not as missing data', function () {
        expect(headlineFor([
            'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null,
            'utm_content' => null, 'utm_term' => null, 'partner' => null,
        ]))->toContain('New organic lead');
    });

    test('a referred lead names the referrer, not the campaign it happened to carry', function () {
        Referrer::query()->create([
            'name' => 'Dana Whitfield',
            'email' => 'headline-'.uniqid().'@agency.com',
            'referral_code' => 'headline-dana',
            'status' => 'active',
        ]);

        /*
         * The referral programme stamps `source_type`/`source_id`; it sets neither `partner`
         * nor any UTM. The headline read only those two, so a correctly attributed referral
         * with no campaign parameters was announced as "New organic lead" — right in the
         * database, wrong in the only place anyone reads it.
         */
        expect(headlineFor([
            'source_type' => 'referral_hub',
            'source_id' => 'headline-dana',
            'partner' => null,
            'utm_source' => null, 'utm_medium' => null,
        ]))->toContain('New referral from Dana Whitfield');
    });

    test('a referral outranks a campaign, because it names someone who is owed a commission', function () {
        Referrer::query()->create([
            'name' => 'Priya Raghunathan',
            'email' => 'headline2-'.uniqid().'@agency.com',
            'referral_code' => 'headline-priya',
            'status' => 'active',
        ]);

        expect(headlineFor([
            'source_type' => 'referral_hub',
            'source_id' => 'headline-priya',
            'partner' => null,
            'utm_source' => 'facebook', 'utm_medium' => 'cpc',
        ]))->toContain('New referral from Priya Raghunathan');
    });

    test('a referral whose referrer row is gone still reads as a referral', function () {
        expect(headlineFor([
            'source_type' => 'referral_hub',
            'source_id' => 'deleted-code',
            'partner' => null,
            'utm_source' => null, 'utm_medium' => null,
        ]))->toContain('New referral from deleted-code');
    });

    test('a partner referral outranks the utm, because it names a relationship', function () {
        expect(headlineFor(['partner' => 'oyster', 'utm_source' => 'google']))
            ->toContain('New lead from Oyster');
    });

    test('an unmapped source still reads as a channel rather than being dropped', function () {
        expect(headlineFor(['utm_source' => 'reddit', 'partner' => null]))
            ->toContain('New lead from Reddit');
    });
});
