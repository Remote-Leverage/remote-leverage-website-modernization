<?php

declare(strict_types=1);

use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\ReferralReward;
use App\Domains\Referral\Models\Referrer;
use App\Infrastructure\WordPress\Admin\ReferralAdminDashboard;

function makeAdminCrudReferrer(array $overrides = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Existing Partner',
        'email' => 'partner-'.uniqid().'@venture.com',
        'referral_code' => 'partner-'.uniqid(),
        'password' => password_hash('whatever', PASSWORD_BCRYPT),
        'status' => 'active',
    ], $overrides));
}

describe('Creating a referrer from the admin screen', function () {
    test('a valid submission creates the referrer and hands back a one-time password', function () {
        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => 'Dana Okonkwo',
            'email' => 'Dana-'.uniqid().'@Venture.com',
            'company' => 'Okonkwo Partners',
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['errors'])->toBe([])
            ->and($result['password'])->toBeString()
            ->and(strlen((string) $result['password']))->toBe(20);

        $referrer = $result['referrer'];

        expect($referrer->name)->toBe('Dana Okonkwo')
            ->and($referrer->email)->toBe(strtolower($referrer->email))
            ->and($referrer->company)->toBe('Okonkwo Partners')
            ->and($referrer->status)->toBe('active');
    });

    test('the generated password is stored only as a verifiable hash', function () {
        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => 'Hash Check',
            'email' => 'hashcheck-'.uniqid().'@venture.com',
        ]);

        expect($result['referrer']->password)->not->toBe($result['password'])
            ->and(password_verify($result['password'], $result['referrer']->password))->toBeTrue();
    });

    test('a referral code is generated from the name when none is given', function () {
        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => 'Dana Okonkwo',
            'email' => 'code-'.uniqid().'@venture.com',
        ]);

        expect($result['referrer']->referral_code)->toStartWith('dana-okonkwo-');
    });

    test('a duplicate email is reported rather than silently returning the existing referrer', function () {
        $existing = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => 'Impostor',
            'email' => strtoupper($existing->email),
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('A referrer with that email already exists.')
            ->and($result['referrer'])->toBeNull()
            ->and($result['password'])->toBeNull();
    });

    test('a taken referral code is rejected before it reaches the unique index', function () {
        $existing = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => 'Code Clash',
            'email' => 'clash-'.uniqid().'@venture.com',
            'referral_code' => $existing->referral_code,
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('That referral code is already taken.');
    });

    test('name, email and status are validated', function () {
        $result = (new ReferralAdminDashboard)->createReferrer([
            'name' => '',
            'email' => 'not-an-email',
            'status' => 'banished',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('Name is required.')
            ->and($result['errors'])->toContain('That email address is not valid.')
            ->and($result['errors'])->toContain('Status must be one of: active, pending, inactive.');

        // The rejected values come back so the form can be re-filled rather than cleared.
        expect($result['input']['email'])->toBe('not-an-email');
    });
});

describe('Creating a referral from the admin screen', function () {
    test('a valid submission records the referral against the referrer', function () {
        $referrer = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Priya Raman',
            'lead_email' => 'priya-'.uniqid().'@acme.test',
            'notes' => 'Met at the Lagos meetup.',
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['referral']->referrer_id)->toBe($referrer->id)
            ->and($result['referral']->lead_name)->toBe('Priya Raman')
            ->and($result['referral']->status)->toBe('pending')
            ->and($result['referral']->source)->toBe('admin_manual')
            ->and($result['referral']->notes)->toBe('Met at the Lagos meetup.');
    });

    test('no lead row is created, so the lead pipeline stays silent', function () {
        $referrer = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'No Lead Row',
            'lead_phone' => '+234 800 000 0000',
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['referral']->lead_id)->toBeNull()
            ->and($result['referral']->lead_email)->toBe('');
    });

    test('a referral needs either an email or a phone number', function () {
        $referrer = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Contactless',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('Provide either a lead email or a lead phone number.');
    });

    test('a referral without an existing referrer is rejected', function () {
        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => 999999,
            'lead_name' => 'Orphan',
            'lead_email' => 'orphan-'.uniqid().'@acme.test',
        ]);

        expect($result['success'])->toBeFalse()
            ->and($result['errors'])->toContain('Choose the referrer this referral belongs to.')
            ->and($result['referral'])->toBeNull();
    });

    test('entering a referral as already fulfilled generates its reward straight away', function () {
        $referrer = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Closed Already',
            'lead_email' => 'closed-'.uniqid().'@acme.test',
            'status' => 'fulfilled',
        ]);

        expect($result['success'])->toBeTrue()
            ->and(ReferralReward::where('referral_id', $result['referral']->id)->where('status', 'due')->exists())->toBeTrue();
    });

    test('a pending referral earns nothing yet', function () {
        $referrer = makeAdminCrudReferrer();

        $result = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Still Pending',
            'lead_email' => 'pending-'.uniqid().'@acme.test',
        ]);

        expect(ReferralReward::where('referral_id', $result['referral']->id)->exists())->toBeFalse();
    });
});

describe('Deleting leaves the money trail intact', function () {
    test('deleting a referrer keeps their referrals and rewards on the books', function () {
        $referrer = makeAdminCrudReferrer();
        $dashboard = new ReferralAdminDashboard;

        $referral = $dashboard->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Kept Lead',
            'lead_email' => 'kept-'.uniqid().'@acme.test',
            'status' => 'fulfilled',
        ])['referral'];

        $referrer->delete();

        $reloaded = Referral::find($referral->id);

        expect($reloaded)->not->toBeNull()
            ->and($reloaded->referrer)->toBeNull()
            ->and(ReferralReward::where('referral_id', $referral->id)->exists())->toBeTrue();
    });

    test('deleting a referral keeps the reward it already earned', function () {
        $referrer = makeAdminCrudReferrer();

        $referral = (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Deleted Referral',
            'lead_email' => 'deleted-'.uniqid().'@acme.test',
            'status' => 'rewarded',
        ])['referral'];

        $referral->delete();

        expect(Referral::find($referral->id))->toBeNull()
            ->and(ReferralReward::where('referral_id', $referral->id)->exists())->toBeTrue();
    });
});

describe('The screens expose the create and delete controls', function () {
    afterEach(function () {
        unset($_GET['new'], $_GET['page'], $_GET['s'], $_GET['status'], $_GET['paged']);
    });

    test('the referrers screen offers Add Referrer and a delete control per row', function () {
        makeAdminCrudReferrer(['name' => 'Deletable Partner']);

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderReferrers();
        $html = ob_get_clean();

        expect($html)->toContain('Add Referrer')
            ->toContain('rl_action=delete_referrer')
            ->toContain('rl-btn-destructive')
            // The form itself stays closed until asked for.
            ->not->toContain('name="rl_action" value="create_referrer"');
    });

    test('the referrers screen opens the create form on ?new=1', function () {
        $_GET['new'] = '1';

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderReferrers();
        $html = ob_get_clean();

        expect($html)->toContain('name="rl_action" value="create_referrer"')
            ->toContain('name="referral_code"')
            ->toContain('shown once on the next screen');
    });

    test('the referrals screen offers Add Referral and a delete control per row', function () {
        $referrer = makeAdminCrudReferrer();
        (new ReferralAdminDashboard)->createReferral([
            'referrer_id' => $referrer->id,
            'lead_name' => 'Listed Lead',
            'lead_email' => 'listed-'.uniqid().'@acme.test',
        ]);

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderReferrals();
        $html = ob_get_clean();

        expect($html)->toContain('Add Referral')
            ->toContain('rl_action=delete_referral')
            ->not->toContain('name="rl_action" value="create_referral"');
    });

    test('the referrals screen opens the create form on ?new=1 and lists the referrers', function () {
        $referrer = makeAdminCrudReferrer(['name' => 'Selectable Partner']);
        $_GET['new'] = '1';

        $dashboard = new ReferralAdminDashboard;

        ob_start();
        $dashboard->renderReferrals();
        $html = ob_get_clean();

        expect($html)->toContain('name="rl_action" value="create_referral"')
            ->toContain('Selectable Partner ('.$referrer->referral_code.')');
    });
});
