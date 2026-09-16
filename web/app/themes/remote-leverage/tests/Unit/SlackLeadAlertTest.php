<?php

declare(strict_types=1);

use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Listeners\HandleLeadEventsForSlack;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadActivityLogger;

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

        protected function send(string $text): bool
        {
            $this->sent[] = $text;

            return true;
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

    test('the message matches the legacy feed, field for field', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        $message = $listener->sent[0];

        expect($message)->toContain('*NEW LEAD:*')
            ->and($message)->toContain('*Name*: Ada Lovelace')
            ->and($message)->toContain('*Email:* ada@example.com')
            ->and($message)->toContain('*Phone:* +1 650 555 0100')
            ->and($message)->toContain('*Company Revenue:* $10k to $50k Per Month')
            ->and($message)->toContain('*Landing page:* https://remoteleverage.com/hire-va-4/');
    });

    test('all five UTMs land on one Source line, in the feed order', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead(), context: []));

        expect($listener->sent[0])
            ->toContain('*Source:* linkedin va-q4 cpc variant-b virtual assistant');
    });

    test('missing fields read as N/A rather than leaving a blank line', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'phone' => null,
            'monthly_revenue' => null,
            'utm_source' => null,
            'utm_campaign' => null,
            'utm_medium' => null,
            'utm_content' => null,
            'utm_term' => null,
        ]), context: []));

        expect($listener->sent[0])->toContain('*Phone:* N/A')
            ->and($listener->sent[0])->toContain('*Source:* N/A')
            ->and($listener->sent[0])->toContain('*Company Revenue:* N/A');
    });

    test('landing_url is used when landing_page_base is absent', function () {
        $listener = slackListener();
        $listener->handleCreated(new LeadCreated(lead: slackLead([
            'landing_page_base' => null,
            'landing_url' => 'https://remoteleverage.com/spanish/?utm_source=x',
        ]), context: []));

        expect($listener->sent[0])->toContain('*Landing page:* https://remoteleverage.com/spanish/?utm_source=x');
    });
});
