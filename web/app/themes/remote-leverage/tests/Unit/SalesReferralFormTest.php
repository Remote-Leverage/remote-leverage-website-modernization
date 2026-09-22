<?php

declare(strict_types=1);

use App\Application\Livewire\Referrer\SalesReferralForm;
use App\Domains\Lead\Models\Lead;
use App\Domains\Referral\Actions\RegisterReferrerAction;
use App\Domains\Referral\Actions\SubmitReferredLeadAction;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Repositories\EloquentReferrerRepository;
use App\Domains\Referral\Repositories\ReferrerRepositoryInterface;
use App\Support\PageRobots;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * WR-126 — the sales team records a referral while the referrer is on the phone.
 *
 * The form is unauthenticated by decision, so what is pinned here is everything that bounds
 * that decision: it cannot invent a referrer, it cannot skip the field requirements the main
 * booking form sets, it cannot create anything beyond a `pending` referral, it is throttled,
 * and it is not indexable.
 */
beforeEach(function () {
    app()->bind(ReferrerRepositoryInterface::class, EloquentReferrerRepository::class);
    $GLOBALS['_test_request_ip'] = '203.0.113.'.random_int(1, 254);

    Referral::query()->delete();
    Referrer::query()->delete();
    Lead::query()->forceDelete();
    Cache::flush();
    PageRobots::clearForced();
});

afterEach(fn () => PageRobots::clearForced());

function salesReferrer(): Referrer
{
    return Referrer::query()->create([
        'name' => 'Nadia Okonkwo',
        'email' => 'nadia-'.uniqid().'@venture.com',
        'referral_code' => 'sales-'.uniqid(),
        'status' => 'active',
    ]);
}

function salesForm(Referrer $referrer, array $overrides = []): SalesReferralForm
{
    $form = new SalesReferralForm;

    $fields = array_merge([
        'referrerCode' => $referrer->referral_code,
        'firstName' => 'Priya',
        'lastName' => 'Raman',
        'email' => 'priya-'.uniqid().'@client.com',
        'phone' => '+1 555 0142',
        'revenue' => '$50k-$100k Per Month',
    ], $overrides);

    foreach ($fields as $property => $value) {
        $form->{$property} = $value;
    }

    return $form;
}

describe('it cannot invent a referrer', function () {
    test('an unknown referral code is refused', function () {
        $form = salesForm(salesReferrer(), ['referrerCode' => 'not-a-real-code']);

        $form->submit();

        expect($form->errorMessage)->toBe('That referral code does not match an active referrer.')
            ->and(Lead::query()->count())->toBe(0)
            ->and(Referral::query()->count())->toBe(0);
    });

    test('a blank referral code is refused', function () {
        $form = salesForm(salesReferrer(), ['referrerCode' => '']);

        $form->submit();

        expect($form->errorMessage)->toBe('That referral code does not match an active referrer.')
            ->and(Referral::query()->count())->toBe(0);
    });

    test('a referrer email is not accepted in place of the code', function () {
        /*
         * The portal's login accepts a code *or* an email. This form must not: it is filled in
         * by someone who is not the referrer, so an email lookup on an unauthenticated page
         * would answer "does this address have a referrer account?" for anyone who asked.
         */
        $referrer = salesReferrer();
        $form = salesForm($referrer, ['referrerCode' => $referrer->email]);

        $form->submit();

        expect($form->errorMessage)->toBe('That referral code does not match an active referrer.')
            ->and(Referral::query()->count())->toBe(0);
    });
});

describe('it enforces the same fields as the booking form', function () {
    test('a missing phone is refused', function () {
        $form = salesForm(salesReferrer(), ['phone' => '']);
        $form->submit();

        expect($form->errorMessage)->toBe('Please enter the lead phone number.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a missing revenue band is refused', function () {
        $form = salesForm(salesReferrer(), ['revenue' => '']);
        $form->submit();

        expect($form->errorMessage)->toBe('Please select the lead monthly revenue band.')
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a revenue band outside the offered list is refused', function () {
        $form = salesForm(salesReferrer(), ['revenue' => 'lots']);
        $form->submit();

        expect($form->errorMessage)->toBe('Please select the lead monthly revenue band.');
    });

    test('a malformed email is refused', function () {
        $form = salesForm(salesReferrer(), ['email' => 'nope']);
        $form->submit();

        expect($form->errorMessage)->toBe('That does not look like a valid email address.');
    });

    test('the self-referral guard applies to the named referrer, not the rep', function () {
        // The rep is not the referrer, so "self-referral" here means the referrer's own
        // address arriving as the lead — which someone taking details over the phone is the
        // least able to notice.
        $referrer = salesReferrer();
        $form = salesForm($referrer, ['email' => strtoupper($referrer->email)]);

        $form->submit();

        expect($form->errorMessage)->toBe('You cannot submit yourself as a referred lead.')
            ->and(Referral::query()->count())->toBe(0);
    });
});

describe('a recorded referral', function () {
    test('captures the lead and credits the named referrer', function () {
        $referrer = salesReferrer();
        $form = salesForm($referrer, ['email' => 'recorded@client.com']);

        $form->submit();

        $lead = Lead::query()->where('email', 'recorded@client.com')->first();
        $referral = Referral::query()->first();

        expect($form->errorMessage)->toBeNull()
            ->and($lead)->not->toBeNull()
            ->and($lead->first_name)->toBe('Priya')
            ->and($lead->monthly_revenue)->toBe('$50k-$100k Per Month')
            ->and($referral)->not->toBeNull()
            ->and($referral->referrer_id)->toBe($referrer->id)
            ->and($referral->lead_id)->toBe($lead->id)
            ->and($referral->lead_name)->toBe('Priya Raman');
    });

    test('is pending, never qualified', function () {
        // Somebody saying they will book is not a booking. Only a completed booking promotes a
        // referral, and a rep typing this mid-call is the least appropriate place to shortcut
        // that — it is what would earn a reward.
        $form = salesForm(salesReferrer());
        $form->submit();

        expect(Referral::query()->first()->status)->toBe('pending');
    });

    test('is distinguishable from one the referrer filed themselves', function () {
        $form = salesForm(salesReferrer());
        $form->submit();

        expect(Referral::query()->first()->source)->toBe(SubmitReferredLeadAction::SOURCE_SALES)
            ->and(SubmitReferredLeadAction::SOURCE_SALES)->not->toBe(SubmitReferredLeadAction::SOURCE_PORTAL);
    });

    test('clears the lead but keeps the referrer code for the next one', function () {
        $referrer = salesReferrer();
        $form = salesForm($referrer);

        $form->submit();

        expect($form->errorMessage)->toBeNull()
            ->and($form->firstName)->toBe('')
            ->and($form->email)->toBe('')
            ->and($form->revenue)->toBe('')
            // One caller often names several people; retyping the code between them is how the
            // second referral gets credited to nobody.
            ->and($form->referrerCode)->toBe($referrer->referral_code);
    });

    test('reads back what was recorded, for confirming on the call', function () {
        $referrer = salesReferrer();
        $form = salesForm($referrer);

        $form->submit();

        expect($form->lastSubmission['lead_name'])->toBe('Priya Raman')
            ->and($form->lastSubmission['referral_code'])->toBe($referrer->referral_code);
    });
});

describe('it is bounded despite being unauthenticated', function () {
    test('submissions from one IP are throttled', function () {
        $referrer = salesReferrer();

        for ($i = 0; $i < SalesReferralForm::MAX_SUBMISSIONS; $i++) {
            salesForm($referrer, ['email' => "flood{$i}@client.com"])->submit();
        }

        $blocked = salesForm($referrer, ['email' => 'one-too-many@client.com']);
        $blocked->submit();

        expect($blocked->errorMessage)->toContain('Too many submissions')
            ->and(Lead::query()->where('email', 'one-too-many@client.com')->count())->toBe(0);
    });

    test('failed attempts count toward the throttle, so code guessing is bounded too', function () {
        $referrer = salesReferrer();

        for ($i = 0; $i < SalesReferralForm::MAX_SUBMISSIONS; $i++) {
            salesForm($referrer, ['referrerCode' => "guess-{$i}"])->submit();
        }

        $blocked = salesForm($referrer);
        $blocked->submit();

        // Without this, the throttle would only limit *successful* submissions and an attacker
        // could enumerate referral codes for free.
        expect($blocked->errorMessage)->toContain('Too many submissions');
    });
});

describe('it stays out of search results', function () {
    test('the route forces noindex, nofollow', function () {
        // A route has no post and no pattern, so PageRobots' `rl:noindex` marker cannot be
        // declared for one — without forceNoindex() the page is silently indexable.
        PageRobots::forceNoindex();

        $robots = PageRobots::filter(['index' => true, 'follow' => true]);

        expect($robots)->toHaveKey('noindex')
            ->and($robots['noindex'])->toBeTrue()
            ->and($robots)->toHaveKey('nofollow')
            ->and($robots)->not->toHaveKey('index');
    });

    test('the route actually calls it', function () {
        $routes = file_get_contents(__DIR__.'/../../routes/web.php');

        expect($routes)->toContain("Route::get('sales-referral'")
            ->and($routes)->toMatch('/sales-referral.*?PageRobots::forceNoindex\(\)/s');
    });

    test('it is not disallowed in robots.txt, which would hide the noindex', function () {
        /*
         * Deliberate, and the opposite of the obvious move. SiteRobotsTxt documents it: a
         * crawler told not to fetch a URL never reads the `noindex` on it, so a Disallow line
         * here would strand the URL in the index instead of keeping it out.
         */
        $robotsTxt = file_get_contents(__DIR__.'/../../app/Support/SiteRobotsTxt.php');

        expect($robotsTxt)->not->toContain('sales-referral');
    });
});

describe('registering a referrer who has no account yet', function () {
    test('creates an unclaimed referrer and fills in their code', function () {
        $form = new SalesReferralForm;
        $form->newReferrerName = 'Tomas Ferreira';
        $form->newReferrerEmail = 'Tomas@Bright.CO';

        $form->registerReferrer();

        $referrer = Referrer::query()->where('email', 'tomas@bright.co')->first();

        expect($form->registerError)->toBeNull()
            ->and($referrer)->not->toBeNull()
            // Unclaimed: no password, so no portal login until they sign up themselves.
            ->and($referrer->password)->toBeEmpty()
            ->and($referrer->status)->toBe('pending')
            // Email lower-cased, since the portal looks up on it.
            ->and($referrer->email)->toBe('tomas@bright.co')
            // The code lands in the referral form below, which is the whole point of the panel.
            ->and($form->referrerCode)->toBe($referrer->referral_code);
    });

    test('a referral can be credited to them immediately, unclaimed and pending', function () {
        // Attribution is the part with money attached; it must not wait on portal access.
        $form = new SalesReferralForm;
        $form->newReferrerName = 'Tomas Ferreira';
        $form->newReferrerEmail = 'tomas@bright.co';
        $form->registerReferrer();

        $form->firstName = 'Priya';
        $form->lastName = 'Raman';
        $form->email = 'priya@client.com';
        $form->phone = '+1 555 0142';
        $form->revenue = '$10k to $50k Per Month';
        $form->submit();

        $referrer = Referrer::query()->where('email', 'tomas@bright.co')->first();

        expect($form->errorMessage)->toBeNull()
            ->and(Referral::query()->where('referrer_id', $referrer->id)->count())->toBe(1);
    });

    test('an existing account is handed back, not duplicated or overwritten', function () {
        $existing = salesReferrer();

        $form = new SalesReferralForm;
        $form->newReferrerName = 'Someone Else Entirely';
        $form->newReferrerEmail = strtoupper($existing->email);
        $form->registerReferrer();

        expect(Referrer::query()->where('email', $existing->email)->count())->toBe(1)
            ->and($form->referrerCode)->toBe($existing->referral_code)
            // Worded differently on purpose: a rep about to read a code out should not be told
            // they just created an account that was already someone's.
            ->and($form->registerSuccess)->toContain('already has an account');
    });

    test('a bad name or email is refused', function () {
        $form = new SalesReferralForm;
        $form->newReferrerName = '';
        $form->newReferrerEmail = 'tomas@bright.co';
        $form->registerReferrer();

        expect($form->registerError)->toBe('Please enter the referrer full name.');

        $form->newReferrerName = 'Tomas Ferreira';
        $form->newReferrerEmail = 'not-an-email';
        $form->registerReferrer();

        expect($form->registerError)->toBe('Please enter a valid email address for the referrer.')
            ->and(Referrer::query()->count())->toBe(0);
    });

    test('an unresolved code opens the register panel instead of dead-ending', function () {
        $form = salesForm(salesReferrer(), ['referrerCode' => 'nobody-has-this']);

        $form->submit();

        expect($form->showRegisterPanel)->toBeTrue();
    });

    test('registering counts toward the throttle', function () {
        for ($i = 0; $i < SalesReferralForm::MAX_SUBMISSIONS; $i++) {
            $f = new SalesReferralForm;
            $f->newReferrerName = "Person {$i}";
            $f->newReferrerEmail = "person{$i}@bright.co";
            $f->registerReferrer();
        }

        $blocked = new SalesReferralForm;
        $blocked->newReferrerName = 'One Too Many';
        $blocked->newReferrerEmail = 'toomany@bright.co';
        $blocked->registerReferrer();

        expect($blocked->registerError)->toContain('Too many submissions')
            ->and(Referrer::query()->where('email', 'toomany@bright.co')->count())->toBe(0);
    });
});

describe('claiming a rep-created account', function () {
    test('signing up with the same email sets the password and activates it', function () {
        /*
         * The gap this closes. RegisterReferrerAction used to return an existing row *before*
         * checking the password, so someone signing up with an address a rep had already used
         * got handed that record with their chosen password silently discarded — locked out of
         * the one account holding their referrals, and there is no reset flow to recover with.
         */
        $form = new SalesReferralForm;
        $form->newReferrerName = 'Tomas Ferreira';
        $form->newReferrerEmail = 'tomas@bright.co';
        $form->registerReferrer();

        $unclaimed = Referrer::query()->where('email', 'tomas@bright.co')->first();
        $originalCode = $unclaimed->referral_code;

        $claimed = app(RegisterReferrerAction::class)->execute([
            'name' => 'Tomas Ferreira',
            'email' => 'tomas@bright.co',
            'password' => 'a-password-they-chose',
        ]);

        expect($claimed->id)->toBe($unclaimed->id)
            // Same account, so referrals recorded before the claim stay attached.
            ->and($claimed->referral_code)->toBe($originalCode)
            ->and($claimed->status)->toBe('active')
            ->and(password_verify('a-password-they-chose', $claimed->password))->toBeTrue();
    });

    test('an account that already has a password is never overwritten', function () {
        // Otherwise this is a way to take over somebody else's account by knowing their email.
        $referrer = salesReferrer();
        $referrer->forceFill(['password' => password_hash('their-real-password', PASSWORD_BCRYPT)])->save();

        $result = app(RegisterReferrerAction::class)->execute([
            'name' => 'Impostor',
            'email' => $referrer->email,
            'password' => 'attacker-chosen',
        ]);

        expect($result->id)->toBe($referrer->id)
            ->and(password_verify('their-real-password', $result->password))->toBeTrue()
            ->and(password_verify('attacker-chosen', $result->password))->toBeFalse();
    });
});

describe('the register panel and the code field are mutually exclusive', function () {
    /*
     * Source-level rather than rendered: this suite has no view factory bound, so `view()`
     * cannot resolve — which is why EcommerceBlocksTest checks Blade by compiling and linting
     * rather than rendering. The behaviour these guard is real though: `showRegisterPanel`
     * flipping is covered by the component tests above, and what is asserted here is that the
     * flag actually gates the field.
     */
    $view = static fn (): string => (string) file_get_contents(
        dirname(__DIR__, 2).'/resources/views/livewire/referrer/sales-referral-form.blade.php'
    );

    test('the code input is inside the panel-closed branch only', function () use ($view) {
        $source = $view();

        $open = strpos($source, '@if (! $showRegisterPanel)');
        $else = strpos($source, '@else', (int) $open);

        expect($open)->not->toBeFalse()
            ->and($else)->not->toBeFalse();

        $closedBranch = substr($source, (int) $open, (int) $else - (int) $open);

        // Registering generates the code, so offering the field at the same time presents a
        // choice that does not exist — and invites typing over a value about to be replaced.
        expect($closedBranch)->toContain('id="sr-code"')
            ->and($closedBranch)->not->toContain('id="sr-new-email"');
    });

    test('the registration fields are inside the panel-open branch only', function () use ($view) {
        $source = $view();

        $panel = strpos($source, '@if ($showRegisterPanel)');
        $openBranch = substr($source, (int) $panel);

        expect($openBranch)->toContain('id="sr-new-email"')
            ->and($openBranch)->not->toContain('id="sr-code"');
    });

    test('no Blade comment leaks into the page', function () use ($view) {
        /*
         * A malformed `{--` opener (one brace, not two) is not a comment: Blade emits it
         * verbatim, so the explanation rendered as visible copy beside the register button.
         * `php -l` on the compiled template does not catch it, because literal text is valid.
         */
        expect(preg_match('/(?<!\{)\{--/', $view()))->toBe(0)
            ->and(preg_match('/--\}(?!\})/', $view()))->toBe(0);
    });

    test('the view compiles to valid PHP', function () use ($view) {
        $compiler = new BladeCompiler(
            new Filesystem, sys_get_temp_dir()
        );

        $tmp = tempnam(sys_get_temp_dir(), 'rl-blade').'.php';
        file_put_contents($tmp, $compiler->compileString($view()));
        $lint = (string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
        unlink($tmp);

        expect($lint)->toContain('No syntax errors detected');
    });
});
