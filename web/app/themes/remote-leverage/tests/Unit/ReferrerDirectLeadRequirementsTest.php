<?php

declare(strict_types=1);

use App\Application\Livewire\Referrer\ReferrerPortalDashboard;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadQualification;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Domains\Referral\Services\ReferralSettingsService;

/**
 * A referred lead must arrive with everything the main booking form demands.
 *
 * The modal used to ask for a full name plus *either* an email or a phone. Two things followed
 * from that, and both are what these tests exist to prevent coming back:
 *
 *  - Referred leads reached sales missing fields the self-served funnel has always required —
 *    no last name, and no revenue band, which makes `LeadQualification::isT10()` report the
 *    lead unqualified regardless of what it actually earns.
 *  - The self-referral guard is keyed on email, so a phone-only submission walked past it
 *    entirely. The synthesised `@remoteleverage.internal` address such a lead received then
 *    matched nothing in `HandleLeadBookingCompletedForReferrer`'s guard either, so neither
 *    layer caught it.
 */
beforeEach(function () {
    app()->bind(ReferrerRepositoryInterface::class, EloquentReferrerRepository::class);
    $GLOBALS['_test_session'] = [];
    $GLOBALS['_test_request_ip'] = '198.51.100.'.random_int(1, 254);

    // Several assertions below are "nothing was written at all", which only means anything
    // against an empty table.
    Referral::query()->delete();
    Referrer::query()->delete();
    Lead::query()->forceDelete();

    /*
     * Only this key, and only because another suite leaves a saved reward amount of 250 behind
     * in the shared option store — which makes the default assertion below pass alone and fail
     * in a full run. Clearing the whole store would take the Referral repository bindings and
     * session fixtures with it.
     */
    unset($GLOBALS['_wp_mock_options'][ReferralSettingsService::OPTION_KEY]);
});

function directLeadReferrer(string $password = 'correct-horse'): Referrer
{
    return Referrer::query()->create([
        'name' => 'Direct Lead Referrer',
        'email' => 'direct-'.uniqid().'@venture.com',
        'referral_code' => 'direct-'.uniqid(),
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'status' => 'active',
    ]);
}

/**
 * An authenticated portal with the modal filled in, minus whatever the caller unsets.
 */
function directLeadPortal(Referrer $referrer, array $overrides = []): ReferrerPortalDashboard
{
    $portal = new ReferrerPortalDashboard;
    $portal->mount();
    $portal->lookupCode = $referrer->referral_code;
    $portal->password = 'correct-horse';
    $portal->authenticateReferrer();

    $fields = array_merge([
        'leadModalFirstName' => 'David',
        'leadModalLastName' => 'Vance',
        'leadModalEmail' => 'david-'.uniqid().'@client.com',
        'leadModalPhone' => '+1 555 0100',
        'leadModalRevenue' => '$10k to $50k Per Month',
    ], $overrides);

    foreach ($fields as $property => $value) {
        $portal->{$property} = $value;
    }

    return $portal;
}

describe('every field the main form requires is required here', function () {
    test('the authenticated portal is the precondition, not the subject', function () {
        // Guards the helper: if authentication silently failed, every rejection test below
        // would pass for the wrong reason.
        expect(directLeadPortal(directLeadReferrer())->isAuthenticated)->toBeTrue();
    });

    test('a missing phone is rejected, where it used to be optional', function () {
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalPhone' => '']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please enter the lead phone number.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a missing email is rejected, where it used to be optional', function () {
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalEmail' => '']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please enter the lead email address.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a missing last name is rejected, where the field did not exist', function () {
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalLastName' => '']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please enter the lead last name.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a missing revenue band is rejected, where the field did not exist', function () {
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalRevenue' => '']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please select the lead monthly revenue band.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a revenue band outside the offered list is rejected', function () {
        // The band arrives from the browser, so the list is a whitelist rather than a hint.
        // A value outside it would be stored verbatim on the lead and compared against
        // SUB_T10_BANDS forever after.
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalRevenue' => '$1bn Per Month']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please select the lead monthly revenue band.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a malformed email is rejected', function () {
        $portal = directLeadPortal(directLeadReferrer(), ['leadModalEmail' => 'not-an-address']);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('That does not look like a valid email address.')
            ->and(Lead::query()->count())->toBe(0);
    });
});

describe('the self-referral guard now always runs', function () {
    test('a referrer submitting their own email is refused', function () {
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, ['leadModalEmail' => strtoupper($referrer->email)]);

        $portal->submitDirectLead();

        // Refused in the component rather than thrown: the message belongs next to the form.
        // Upper-cased deliberately — the comparison is case-insensitive on both sides.
        expect($portal->leadModalError)->toBe('You cannot submit yourself as a referred lead.')
            ->and(Lead::query()->count())->toBe(0)
            ->and(Referral::query()->count())->toBe(0);
    });

    test('the phone-only bypass is gone, because phone-only submissions are gone', function () {
        /*
         * The exact shape of the old hole: blank email, phone supplied. It used to short-circuit
         * the guard (`&& $this->leadModalEmail`) and create a referral for the referrer
         * themselves. Now it cannot get past validation at all.
         */
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, [
            'leadModalEmail' => '',
            'leadModalPhone' => '+1 555 0100',
        ]);

        $portal->submitDirectLead();

        expect($portal->leadModalError)->toBe('Please enter the lead email address.')
            ->and(Lead::query()->count())->toBe(0)
            ->and(Referral::query()->count())->toBe(0);
    });
});

describe('a complete submission', function () {
    test('captures the lead with every field the main form would have captured', function () {
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, ['leadModalEmail' => 'complete@client.com']);

        $portal->submitDirectLead();

        $lead = Lead::query()->where('email', 'complete@client.com')->first();

        expect($portal->leadModalError)->toBeNull()
            ->and($lead)->not->toBeNull()
            ->and($lead->first_name)->toBe('David')
            ->and($lead->last_name)->toBe('Vance')
            // Derived by LeadCaptureData from the two parts, not posted separately.
            ->and($lead->name)->toBe('David Vance')
            ->and($lead->phone)->toBe('+1 555 0100')
            ->and($lead->monthly_revenue)->toBe('$10k to $50k Per Month')
            // No synthesised @remoteleverage.internal address any more.
            ->and($lead->email)->toBe('complete@client.com');
    });

    test('the captured lead is T10-qualifiable, which a bandless one never was', function () {
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, ['leadModalEmail' => 't10@client.com']);

        $portal->submitDirectLead();

        $lead = Lead::query()->where('email', 't10@client.com')->first();

        // The practical consequence of requiring the band: before this, every referred lead
        // answered "no" here whatever it earned, because the question was never asked.
        expect(LeadQualification::isT10($lead))->toBeTrue();
    });

    test('the referral row carries the assembled full name', function () {
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, ['leadModalEmail' => 'named@client.com']);

        $portal->submitDirectLead();

        $referral = Referral::query()->where('lead_email', 'named@client.com')->first();

        expect($referral)->not->toBeNull()
            // rl_referrals.lead_name is a single column, so the two parts are joined for it.
            ->and($referral->lead_name)->toBe('David Vance')
            ->and($referral->lead_phone)->toBe('+1 555 0100')
            ->and($referral->source)->toBe('referrer_direct_submission')
            ->and($referral->status)->toBe('pending');
    });

    test('the form is cleared so a second prospect does not inherit the first', function () {
        $referrer = directLeadReferrer();
        $portal = directLeadPortal($referrer, ['leadModalEmail' => 'cleared@client.com']);

        $portal->submitDirectLead();

        expect($portal->leadModalFirstName)->toBe('')
            ->and($portal->leadModalLastName)->toBe('')
            ->and($portal->leadModalEmail)->toBe('')
            ->and($portal->leadModalPhone)->toBe('')
            ->and($portal->leadModalRevenue)->toBe('');
    });
});

describe('the revenue bands are defined once', function () {
    test('the modal and the booking wizard read the same list', function () {
        // Both the wizard view and the portal modal render
        // LeadQualification::REVENUE_BANDS. Two copies is how one form starts offering a band
        // the other cannot qualify.
        $wizard = file_get_contents(__DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php');
        $modal = file_get_contents(__DIR__.'/../../resources/views/livewire/referrer/referrer-portal-dashboard.blade.php');

        expect($wizard)->toContain('LeadQualification::REVENUE_BANDS')
            ->and($modal)->toContain('LeadQualification::REVENUE_BANDS');
    });

    test('every band the wizard used to hardcode is still offered, in order', function () {
        // Pinned verbatim: these strings are stored data, not just copy — `monthly_revenue`
        // holds them and SUB_T10_BANDS compares against them.
        expect(LeadQualification::REVENUE_BANDS)->toBe([
            '$0 to $5k Per Month',
            '$5k to $10k Per Month',
            '$10k to $50k Per Month',
            '$50k-$100k Per Month',
            '$100k+ Per Month',
        ]);
    });
});

describe('the default reward', function () {
    test('is $1000', function () {
        expect((new ReferralSettingsService)->get()['default_reward_amount'])->toBe(1000.00);
    });

    test('the config default and the defaults() fallback carry the same number', function () {
        /*
         * Two places hold it: `config/services.php` and `defaults()`' own second argument, which
         * is what applies when the theme config is not loaded — as it is not in this suite,
         * where `config('services.referral.default_reward_amount')` resolves to null. So the
         * file is read rather than the container asked, which is the only way to catch the two
         * drifting apart.
         */
        $config = file_get_contents(__DIR__.'/../../config/services.php');

        expect($config)->toContain("env('REFERRAL_DEFAULT_REWARD_AMOUNT', 1000.00)")
            ->and(ReferralSettingsService::defaults()['default_reward_amount'])->toBe(1000.00);
    });
});
