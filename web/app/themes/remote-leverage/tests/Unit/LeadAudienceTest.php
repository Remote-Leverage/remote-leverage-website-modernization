<?php

declare(strict_types=1);

use App\Domains\Lead\Data\LeadAudience;
use App\Domains\Lead\Models\Lead;

/*
 * Who a lead appears to be — a client, or somebody applying for VA work.
 *
 * The rule is a prompt for a human, so these tests are as much about what it must NOT flag as
 * what it flags: a false "Possible VA" on a real client is the expensive direction, since it
 * invites the team to deprioritise someone who came to buy.
 */

function audienceLead(array $attributes = []): Lead
{
    $lead = new Lead;

    $lead->forceFill(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'source_type' => 'organic',
    ], $attributes));

    return $lead;
}

describe('LeadAudience', function () {
    test('a phone from outside the US and Canada is flagged', function () {
        $audience = LeadAudience::for(audienceLead([
            'phone_country' => 'CO',
            'phone' => '+573115002018',
        ]));

        expect($audience->possibleVirtualAssistant)->toBeTrue()
            ->and($audience->label())->toBe('Possible VA')
            ->and($audience->note())->toContain('from CO');
    });

    test('a US phone is not flagged', function () {
        expect(LeadAudience::for(audienceLead([
            'phone_country' => 'US',
            'phone' => '+13055550000',
        ]))->label())->toBeNull();
    });

    test('a Canadian phone is not flagged', function () {
        expect(LeadAudience::for(audienceLead([
            'phone_country' => 'CA',
            'phone' => '+14165550000',
        ]))->label())->toBeNull();
    });

    test('the country code is read case-insensitively', function () {
        // Whatever the form hands over, 'co' and 'CO' are the same country.
        expect(LeadAudience::for(audienceLead(['phone_country' => 'co']))->possibleVirtualAssistant)
            ->toBeTrue();
    });

    test('a referred lead is never flagged', function () {
        /*
         * Referrers introduce people they know are hiring. Tagging one of those as a possible
         * applicant over a phone country is not a borderline call, it is wrong.
         */
        $audience = LeadAudience::for(audienceLead([
            'phone_country' => 'CO',
            'source_type' => 'referral_hub',
            'referral_code' => 'adrian-salvatori-8oue',
        ]));

        expect($audience->possibleVirtualAssistant)->toBeFalse()
            ->and($audience->label())->toBeNull()
            ->and($audience->note())->toBeNull();
    });

    test('a partnership lead is never flagged', function () {
        expect(LeadAudience::for(audienceLead([
            'phone_country' => 'CO',
            'source_type' => 'partnership',
        ]))->possibleVirtualAssistant)->toBeFalse();
    });

    test('a referral code outranks an organic source_type', function () {
        // The two are stamped independently; a code means somebody made an introduction
        // whatever the channel resolved to.
        expect(LeadAudience::for(audienceLead([
            'phone_country' => 'CO',
            'source_type' => 'organic',
            'referral_code' => 'adrian-salvatori-8oue',
        ]))->possibleVirtualAssistant)->toBeFalse();
    });

    test('falls back to the dial code when no country was collected', function () {
        $audience = LeadAudience::for(audienceLead([
            'phone_country' => null,
            'phone' => '+57 311 500 2018',
        ]));

        expect($audience->possibleVirtualAssistant)->toBeTrue()
            ->and($audience->note())->toContain('outside the US and Canada');
    });

    test('a +1 dial code is treated as home', function () {
        expect(LeadAudience::for(audienceLead([
            'phone_country' => null,
            'phone' => '+1 305 555 0000',
        ]))->possibleVirtualAssistant)->toBeFalse();
    });

    test('declines to guess from a number that is not in E.164', function () {
        // No country and no dial code is not evidence of anything. A wrong guess here puts the
        // tag on a domestic client who typed their number without the prefix.
        expect(LeadAudience::for(audienceLead([
            'phone_country' => null,
            'phone' => '305 555 0000',
        ]))->possibleVirtualAssistant)->toBeFalse();
    });

    test('a lead with no phone at all is not flagged', function () {
        expect(LeadAudience::for(audienceLead(['phone_country' => null, 'phone' => null]))->label())
            ->toBeNull();
    });

    test('the model exposes the same judgement', function () {
        $lead = audienceLead(['phone_country' => 'CO']);

        expect($lead->audience()->label())->toBe('Possible VA');
    });
});
