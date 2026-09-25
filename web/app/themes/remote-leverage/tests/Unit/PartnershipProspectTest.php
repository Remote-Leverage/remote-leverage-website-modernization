<?php

declare(strict_types=1);

use App\Application\Livewire\Partner\PartnershipProspectForm;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\EmailValidationService;
use App\Domains\PartnerHub\Actions\SubmitPartnershipProspectAction;
use App\Domains\PartnerHub\Events\PartnershipProspectSubmitted;
use App\Domains\PartnerHub\Listeners\HandlePartnershipProspectForSlack;
use App\Domains\PartnerHub\Models\PartnershipProspect;
use App\Domains\PartnerHub\Support\PartnershipCallCalendar;
use App\Domains\PartnerHub\Support\PartnershipProspectOptions;
use App\Domains\PartnerHub\Support\PartnershipSettings;
use App\Domains\Referral\Models\Referrer;
use App\Infrastructure\Slack\SlackTransport;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Mechanisms\DataStore;

/**
 * Partnership prospects — the /become-a-partner/ form.
 *
 * The people filling it in are companies that might send us clients, not buyers and not yet
 * referrers. Most of what is pinned here is that distinction holding: the row lands in its own
 * table, nothing touches the lead or referral tables, and the Slack card goes to the partnerships
 * channel or nowhere — never to the sales channel an unset override would fall back to.
 */

/** An email gate with a fixed answer, so no test reaches ZeroBounce. */
function prospectEmailGate(bool $valid = true, string $message = 'Please use a valid business email address.'): EmailValidationService
{
    return new class($valid, $message) extends EmailValidationService
    {
        /** @var array<int, string> */
        public array $checked = [];

        public function __construct(private bool $valid, private string $refusal) {}

        public function validate(string $email, ?string $ipAddress = null): array
        {
            $this->checked[] = $email;

            return $this->valid
                ? ['valid' => true, 'reason' => null, 'message' => null, 'checked_by' => null]
                : ['valid' => false, 'reason' => 'blocked_domain', 'message' => $this->refusal, 'checked_by' => 'domain_validator'];
        }
    };
}

/** @return array<string, string> A submission that passes every rule. */
function prospectInput(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'email' => 'dana-'.uniqid().'@northwind-advisory.com',
        'company' => 'Northwind Advisory',
        'role' => 'Managing Partner',
        'organization_type' => 'consultancy',
        'monthly_revenue' => '250k_1m',
        'businesses_reached' => '250_1000',
        'message' => 'We advise 300 dental practices that keep asking about remote staff.',
    ], $overrides);
}

/** A transport that records what it would send instead of reaching Slack. */
function recordingProspectTransport(): object
{
    return new class extends SlackTransport
    {
        /** @var array<int, array{text: string, blocks: array, channel: ?string}> */
        public array $posted = [];

        public function post(
            string $text,
            array $blocks = [],
            ?string $color = null,
            ?string $threadTs = null,
            bool $broadcast = false,
            ?string $channel = null,
        ): ?array {
            $this->posted[] = ['text' => $text, 'blocks' => $blocks, 'channel' => $channel];

            return ['ts' => '1727200000.000100', 'channel' => 'C-PARTNERS'];
        }
    };
}

function makeProspect(array $attributes = []): PartnershipProspect
{
    return PartnershipProspect::query()->create(array_merge([
        'first_name' => 'Dana',
        'last_name' => 'Whitfield',
        'email' => 'dana@northwind-advisory.com',
        'company' => 'Northwind Advisory',
        'role' => 'Managing Partner',
        'organization_type' => 'consultancy',
        'monthly_revenue' => '250k_1m',
        'businesses_reached' => '250_1000',
        'message' => null,
        'landing_url' => 'https://remoteleverage.com/become-a-partner/?utm_source=linkedin',
    ], $attributes));
}

beforeEach(function () {
    /*
     * Livewire keeps a component's error bag in its DataStore, which it registers as a singleton.
     * The bare test container would build a fresh one on every lookup, so an error added under a
     * field would be gone by the time anything read it back.
     */
    if (! app()->bound(DataStore::class)) {
        app()->singleton(DataStore::class);
    }

    PartnershipProspect::query()->delete();
    Cache::flush();
    Event::forget(PartnershipProspectSubmitted::class);
    $GLOBALS['_wp_mock_options'] = [];
    $GLOBALS['_test_request_ip'] = '198.51.100.'.random_int(1, 254);

    config([
        'slack-notifications' => require __DIR__.'/../../config/slack-notifications.php',
        'services.slack.bot_token' => '',
    ]);
});

afterEach(function () {
    Event::forget(PartnershipProspectSubmitted::class);
    app()->forgetInstance(SubmitPartnershipProspectAction::class);
});

describe('the answer lists', function () {
    test('carry the three questions, in the order the form asks them', function () {
        expect(array_keys(PartnershipProspectOptions::all()))
            ->toBe(['organization_type', 'monthly_revenue', 'businesses_reached'])
            ->and(array_values(PartnershipProspectOptions::ORGANIZATION_TYPES))->toBe([
                'Consultancy or advisory firm',
                'Agency',
                'Technology / SaaS company',
                'Community or association',
                'Other',
            ])
            ->and(PartnershipProspectOptions::MONTHLY_REVENUE)->toHaveCount(5)
            ->and(PartnershipProspectOptions::BUSINESSES_REACHED)->toHaveCount(5)
            ->and(PartnershipProspectOptions::slugs('monthly_revenue'))
            ->toBe(['under_50k', '50k_250k', '250k_1m', '1m_5m', '5m_plus']);
    });

    test('store slugs and read back labels', function () {
        expect(PartnershipProspectOptions::label('businesses_reached', '1000_10000'))->toBe('1,000 – 10,000')
            ->and(PartnershipProspectOptions::label('monthly_revenue', '5m_plus'))->toBe('$5M+');
    });

    test('print an unknown slug as itself rather than as nothing', function () {
        // A row written before an answer was removed still says something, and an empty value
        // bound to a Slack text object costs the whole message.
        expect(PartnershipProspectOptions::label('organization_type', 'retired_answer'))->toBe('retired_answer')
            ->and(PartnershipProspectOptions::slugs('not_a_field'))->toBe([]);
    });
});

describe('submitting a prospect', function () {
    test('stores the row and announces it', function () {
        $announced = [];
        Event::listen(PartnershipProspectSubmitted::class, function ($event) use (&$announced) {
            $announced[] = $event->prospect;
        });

        $prospect = (new SubmitPartnershipProspectAction(prospectEmailGate()))->execute(
            prospectInput(['email' => '  Dana@Northwind-Advisory.com ']),
            [
                'landing_url' => 'https://remoteleverage.com/become-a-partner/',
                'utm_source' => 'linkedin',
                'utm_campaign' => str_repeat('c', 200),
                'ip_address' => '203.0.113.9',
                'context' => ['gclid' => 'abc123', 'fbclid' => '', 'source_form' => 'PartnershipProspectForm'],
            ],
        );

        $stored = PartnershipProspect::query()->find($prospect->id);

        expect($announced)->toHaveCount(1)
            ->and($announced[0]->id)->toBe($prospect->id)
            ->and($stored->email)->toBe('dana@northwind-advisory.com')
            ->and($stored->status)->toBe('new')
            ->and($prospect->status)->toBe('new')
            ->and($stored->organization_type)->toBe('consultancy')
            ->and($stored->utm_source)->toBe('linkedin')
            // Cut to its column rather than failing the insert on MySQL's strict mode.
            ->and(mb_strlen((string) $stored->utm_campaign))->toBe(150)
            ->and($stored->ip_address)->toBe('203.0.113.9')
            ->and($stored->context)->toBe(['gclid' => 'abc123', 'source_form' => 'PartnershipProspectForm'])
            ->and($stored->booked_at)->toBeNull();
    });

    test('is not a lead and not a referrer', function () {
        $leads = Lead::query()->count();
        $referrers = Referrer::query()->count();

        (new SubmitPartnershipProspectAction(prospectEmailGate()))->execute(prospectInput());

        expect(Lead::query()->count())->toBe($leads)
            ->and(Referrer::query()->count())->toBe($referrers)
            ->and(PartnershipProspect::query()->count())->toBe(1);
    });

    test('stores a blank message as null', function () {
        $prospect = (new SubmitPartnershipProspectAction(prospectEmailGate()))->execute(prospectInput(['message' => '   ']));

        expect($prospect->message)->toBeNull();
    });
});

describe('validation', function () {
    /** @return array<string, array<int, string>> */
    function prospectErrors(array $input, ?EmailValidationService $gate = null): array
    {
        try {
            (new SubmitPartnershipProspectAction($gate ?? prospectEmailGate()))->execute($input);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    }

    test('requires every field but the message', function () {
        $errors = prospectErrors([]);

        expect(array_keys($errors))->toEqualCanonicalizing([
            'first_name', 'last_name', 'email', 'company', 'role',
            'organization_type', 'monthly_revenue', 'businesses_reached',
        ])
            ->and($errors['first_name'][0])->toBe('Please enter your first name.')
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('refuses an answer that is not on the list', function () {
        // The values arrive from the browser and are stored verbatim.
        $errors = prospectErrors(prospectInput([
            'organization_type' => 'Agency',
            'monthly_revenue' => '$100k+ Per Month',
            'businesses_reached' => '1000000_plus',
        ]));

        expect(array_keys($errors))->toEqualCanonicalizing(['organization_type', 'monthly_revenue', 'businesses_reached'])
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('refuses a malformed address before spending an email check on it', function () {
        $gate = prospectEmailGate();
        $errors = prospectErrors(prospectInput(['email' => 'not-an-address']), $gate);

        expect($errors['email'][0])->toBe('Please enter a valid email address.')
            ->and($gate->checked)->toBe([]);
    });

    test('applies the same email gate as the booking form', function () {
        $announced = 0;
        Event::listen(PartnershipProspectSubmitted::class, function () use (&$announced) {
            $announced++;
        });

        $gate = prospectEmailGate(false, 'Please use a valid business email address.');
        $errors = prospectErrors(prospectInput(['email' => 'someone@cuvox.de']), $gate);

        expect($errors)->toBe(['email' => ['Please use a valid business email address.']])
            ->and($gate->checked)->toBe(['someone@cuvox.de'])
            ->and($announced)->toBe(0)
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('caps the message', function () {
        expect(prospectErrors(prospectInput(['message' => str_repeat('a', 2001)])))->toHaveKey('message');
    });
});

describe('the Slack card', function () {
    test('is not posted when no partnerships channel is configured', function () {
        // Falling back to the default channel would put a prospect in the sales stream.
        config(['services.slack.bot_token' => 'xoxb-test']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect()));

        expect($transport->posted)->toBe([]);
    });

    test('is not posted without a bot token, because a webhook cannot honour the channel', function () {
        partnershipSettings(['slack_channel' => 'C0PARTNERS']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect()));

        expect($transport->posted)->toBe([]);
    });

    test('goes to the partnerships channel, with the answers as words', function () {
        partnershipSettings(['slack_channel' => 'C0PARTNERS']);
        config(['services.slack.bot_token' => 'xoxb-test']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect([
            'message' => "We run a network of dental practices.\nKeen to talk.",
        ])));

        $encoded = json_encode($transport->posted[0]['blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect($transport->posted)->toHaveCount(1)
            ->and($transport->posted[0]['channel'])->toBe('C0PARTNERS')
            ->and($transport->posted[0]['text'])->toContain('Dana Whitfield')
            ->and($encoded)->toContain('New partnership prospect')
            ->and($encoded)->toContain('Managing Partner at Northwind Advisory')
            ->and($encoded)->toContain('Consultancy or advisory firm')
            ->and($encoded)->toContain('$250k – $1M')
            ->and($encoded)->toContain('250 – 1,000')
            ->and($encoded)->not->toContain('250k_1m')
            ->and($encoded)->toContain('>We run a network of dental practices.\\n>Keen to talk.')
            ->and($encoded)->toContain('edit.php?post_type=rl_partner&page=rl-partnership-prospects')
            ->and($encoded)->toContain('remoteleverage.com/become-a-partner/');
    });

    test('drops the message block when there is no message', function () {
        partnershipSettings(['slack_channel' => 'C0PARTNERS']);
        config(['services.slack.bot_token' => 'xoxb-test']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect()));

        $sections = array_filter($transport->posted[0]['blocks'], fn ($block) => ($block['type'] ?? '') === 'section' && isset($block['text']));

        expect($sections)->toHaveCount(1);
    });

    test('escapes what the visitor typed, so it cannot ping the channel', function () {
        partnershipSettings(['slack_channel' => 'C0PARTNERS']);
        config(['services.slack.bot_token' => 'xoxb-test']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect([
            'company' => 'Acme <!here>',
            'message' => 'Hello <!channel> & <https://evil.example|click me>',
        ])));

        $encoded = json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_SLASHES);

        expect($encoded)->not->toContain('<!channel>')
            ->and($encoded)->not->toContain('<!here>')
            ->and($encoded)->not->toContain('<https://evil.example')
            ->and($encoded)->toContain('&lt;!channel&gt;');
    });

    test('carries no emoji', function () {
        partnershipSettings(['slack_channel' => 'C0PARTNERS']);
        config(['services.slack.bot_token' => 'xoxb-test']);

        $transport = recordingProspectTransport();
        (new HandlePartnershipProspectForSlack($transport))->handle(new PartnershipProspectSubmitted(makeProspect(['message' => 'Hi'])));

        $payload = $transport->posted[0]['text'].json_encode($transport->posted[0]['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect(preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{25A0}-\x{25FF}]/u', $payload))->toBe(0);
    });
});

describe('the partnership calendar', function () {
    test('is off until an https calendly.com page is configured', function () {
        expect(PartnershipCallCalendar::url())->toBeNull();

        foreach (['http://calendly.com/rl/partners', 'https://evil.example/rl', 'https://calendly.com/', 'not a url'] as $bad) {
            partnershipSettings(['calendly_url' => $bad]);
            expect(PartnershipCallCalendar::url())->toBeNull();
        }

        partnershipSettings(['calendly_url' => 'https://calendly.com/remoteleverage/partnership-call']);
        expect(PartnershipCallCalendar::url())->toBe('https://calendly.com/remoteleverage/partnership-call');
    });

    test('embeds the page prefilled, keeping any query the link already had', function () {
        $url = PartnershipCallCalendar::embedUrl(
            'https://calendly.com/remoteleverage/partnership-call?a1=Partner',
            'remoteleverage.com',
            ['first_name' => 'Dana', 'last_name' => 'Whitfield', 'email' => 'dana@northwind-advisory.com', 'utm_source' => 'linkedin', 'utm_medium' => ''],
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        expect(strtok($url, '?'))->toBe('https://calendly.com/remoteleverage/partnership-call')
            ->and($query)->toMatchArray([
                'a1' => 'Partner',
                'embed_domain' => 'remoteleverage.com',
                'embed_type' => 'Inline',
                'name' => 'Dana Whitfield',
                'first_name' => 'Dana',
                'email' => 'dana@northwind-advisory.com',
                'utm_source' => 'linkedin',
            ])
            ->and($query)->not->toHaveKey('utm_medium');
    });

    test('only trusts booking URIs in the shapes Calendly issues', function () {
        expect(PartnershipCallCalendar::isScheduledEventUri('https://api.calendly.com/scheduled_events/ABC-123'))->toBeTrue()
            ->and(PartnershipCallCalendar::isScheduledEventUri('https://evil.example/scheduled_events/ABC'))->toBeFalse()
            ->and(PartnershipCallCalendar::isInviteeUri('https://api.calendly.com/scheduled_events/ABC-123/invitees/XYZ-9'))->toBeTrue()
            ->and(PartnershipCallCalendar::isInviteeUri('https://api.calendly.com/scheduled_events/ABC-123'))->toBeFalse();
    });
});

describe('the form component', function () {
    function prospectForm(array $overrides = []): PartnershipProspectForm
    {
        app()->instance(SubmitPartnershipProspectAction::class, new SubmitPartnershipProspectAction(prospectEmailGate()));

        $form = new PartnershipProspectForm;

        $fields = array_merge([
            'firstName' => 'Dana',
            'lastName' => 'Whitfield',
            'email' => 'dana-'.uniqid().'@northwind-advisory.com',
            'company' => 'Northwind Advisory',
            'role' => 'Managing Partner',
            'organizationType' => 'agency',
            'monthlyRevenue' => '1m_5m',
            'businessesReached' => '50_250',
            'message' => '',
        ], $overrides);

        foreach ($fields as $property => $value) {
            $form->{$property} = $value;
        }

        return $form;
    }

    test('defaults its button to the comp wording', function () {
        expect((new PartnershipProspectForm)->buttonText)->toBe('Book a Partnership Call');
    });

    test('saves the prospect and moves on from the form', function () {
        $form = prospectForm();
        $form->submit();

        $prospect = PartnershipProspect::query()->first();

        expect($form->submitted)->toBeTrue()
            ->and($form->prospectId)->toBe($prospect->id)
            ->and($prospect->organization_type)->toBe('agency')
            ->and($prospect->context)->toBe(['source_form' => 'PartnershipProspectForm']);
    });

    test('puts each error under the field it belongs to', function () {
        $form = prospectForm(['lastName' => '', 'businessesReached' => 'lots']);
        $form->submit();

        expect($form->submitted)->toBeFalse()
            ->and($form->getErrorBag()->has('lastName'))->toBeTrue()
            ->and($form->getErrorBag()->has('businessesReached'))->toBeTrue()
            ->and($form->getErrorBag()->has('last_name'))->toBeFalse()
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('is throttled per IP', function () {
        for ($i = 0; $i < PartnershipProspectForm::MAX_SUBMISSIONS; $i++) {
            prospectForm(['firstName' => ''])->submit();
        }

        $form = prospectForm();
        $form->submit();

        expect($form->submitted)->toBeFalse()
            ->and($form->errorMessage)->toContain('Too many submissions')
            ->and(PartnershipProspect::query()->count())->toBe(0);
    });

    test('shows the thank-you when no calendar is configured, and the calendar when one is', function () {
        $form = prospectForm();
        $form->submit();

        expect($form->calendarUrl())->toBeNull();

        partnershipSettings(['calendly_url' => 'https://calendly.com/remoteleverage/partnership-call']);

        expect($form->calendarUrl())->toStartWith('https://calendly.com/remoteleverage/partnership-call?')
            ->and($form->calendarUrl())->toContain('email='.rawurlencode($form->email));
    });

    test('records a booking once, and only in the shape Calendly reports it', function () {
        $form = prospectForm();
        $form->submit();

        $form->recordBooking('https://evil.example/x', 'https://evil.example/y');
        expect(PartnershipProspect::query()->find($form->prospectId)->booked_at)->toBeNull();

        $event = 'https://api.calendly.com/scheduled_events/EVT-1';
        $invitee = 'https://api.calendly.com/scheduled_events/EVT-1/invitees/INV-1';
        $form->recordBooking($event, $invitee);

        $prospect = PartnershipProspect::query()->find($form->prospectId);
        $first = $prospect->booked_at;

        $form->recordBooking('https://api.calendly.com/scheduled_events/EVT-2', 'https://api.calendly.com/scheduled_events/EVT-2/invitees/INV-2');

        expect($first)->not->toBeNull()
            ->and($prospect->calendly_event_uri)->toBe($event)
            ->and(PartnershipProspect::query()->find($form->prospectId)->calendly_event_uri)->toBe($event)
            ->and($form->booked)->toBeTrue();
    });

    test('ignores a booking report before anything was submitted', function () {
        $form = prospectForm();
        $form->recordBooking('https://api.calendly.com/scheduled_events/EVT-1', 'https://api.calendly.com/scheduled_events/EVT-1/invitees/INV-1');

        expect($form->booked)->toBeFalse();
    });

    test('its view compiles', function () {
        $compiler = new BladeCompiler(new Filesystem, sys_get_temp_dir());
        $source = file_get_contents(__DIR__.'/../../resources/views/livewire/partner/partnership-prospect-form.blade.php');

        $tmp = tempnam(sys_get_temp_dir(), 'rl-blade').'.php';
        file_put_contents($tmp, $compiler->compileString($source));
        $lint = (string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
        unlink($tmp);

        expect($lint)->toContain('No syntax errors detected')
            // The comp's labels and placeholders, in its order.
            ->and($source)->toContain("['firstName', 'First Name', 'First Name'")
            ->and($source)->toContain("['email', 'Business Email', 'name@company.com'")
            ->and($source)->toContain('<option value="">Select one</option>')
            ->and($source)->toContain('Tell us briefly where you see an opportunity to work together (optional)')
            ->and($source)->toContain("ctaPillClasses('w-full");
    });
});

describe('the embedded calendar in the browser', function () {
    /** Run resources/js/partnership-prospect-form.js under node, the way BookingCalendarJsTest does. */
    function runPartnershipJs(string $script): mixed
    {
        $module = realpath(__DIR__.'/../../resources/js/partnership-prospect-form.js');
        $source = 'import * as form from '.json_encode('file://'.$module).";\n"
            .'const out = (() => { '.$script." })();\n"
            .'process.stdout.write(JSON.stringify(out));';

        $process = proc_open(['node', '--input-type=module', '-e', $source], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        expect($process)->not->toBeFalse('node could not be started; it is required for this test');

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        expect(proc_close($process))->toBe(0, "node failed:\n".$stderr);

        return json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
    }

    test('acts on Calendly booking and height messages, from Calendly only', function () {
        $out = runPartnershipJs(<<<'JS'
            const origin = 'https://calendly.com';
            const scheduled = { event: 'calendly.event_scheduled', payload: {
                event: { uri: 'https://api.calendly.com/scheduled_events/EVT-1' },
                invitee: { uri: 'https://api.calendly.com/scheduled_events/EVT-1/invitees/INV-1' },
            } };
            return {
                booked: form.calendlyMessage({ origin, data: scheduled }, origin),
                height: form.calendlyMessage({ origin, data: { event: 'calendly.page_height', payload: { height: '1180px' } } }, origin),
                forged: form.calendlyMessage({ origin: 'https://evil.example', data: scheduled }, origin),
                other: form.calendlyMessage({ origin, data: { event: 'calendly.date_and_time_selected' } }, origin),
                junk: form.calendlyMessage({ origin, data: 'hello' }, origin),
            };
        JS);

        expect($out['booked'])->toBe([
            'type' => 'scheduled',
            'event' => 'https://api.calendly.com/scheduled_events/EVT-1',
            'invitee' => 'https://api.calendly.com/scheduled_events/EVT-1/invitees/INV-1',
        ])
            ->and($out['height'])->toBe(['type' => 'height', 'height' => 1180])
            ->and($out['forged'])->toBeNull()
            ->and($out['other'])->toBeNull()
            ->and($out['junk'])->toBeNull();
    });

    test('keeps its script out of the markup, where wptexturize would curl its quotes', function () {
        $view = file_get_contents(__DIR__.'/../../resources/views/livewire/partner/partnership-prospect-form.blade.php');

        preg_match_all('/x-data="([^"]*)"/', $view, $matches);

        expect($matches[1])->toBe([
            'rlPartnershipForm()',
            'rlPartnershipCalendar(@js(\App\Domains\PartnerHub\Support\PartnershipCallCalendar::ORIGIN))',
        ])
            ->and(file_get_contents(__DIR__.'/../../resources/js/app.js'))
            ->toContain("Alpine.data('rlPartnershipForm', rlPartnershipForm)")
            ->toContain("Alpine.data('rlPartnershipCalendar', rlPartnershipCalendar)");
    });
});

describe('Partnership Settings', function () {
    test('are all off until saved, and read a malformed option as off', function () {
        expect(PartnershipSettings::all())->toBe(['slack_channel' => '', 'calendly_url' => '', 'calendly_event_type' => '']);

        update_option(PartnershipSettings::OPTION, 'not an array');

        expect(PartnershipSettings::slackChannel())->toBe('')
            ->and(PartnershipCallCalendar::url())->toBeNull();
    });

    test('keep a channel id as typed and store a name with its #, lower-cased', function () {
        expect(PartnershipSettings::sanitize(['slack_channel' => 'C0123ABCDEF'])['settings']['slack_channel'])->toBe('C0123ABCDEF')
            ->and(PartnershipSettings::sanitize(['slack_channel' => 'Partnerships'])['settings']['slack_channel'])->toBe('#partnerships')
            ->and(PartnershipSettings::sanitize(['slack_channel' => '#partner-leads'])['settings']['slack_channel'])->toBe('#partner-leads');
    });

    test('refuse a bad value, keep what was saved, and say why', function () {
        $current = [
            'slack_channel' => '#partnerships',
            'calendly_url' => 'https://calendly.com/remoteleverage/partnership-call',
            'calendly_event_type' => 'https://api.calendly.com/event_types/abc-123',
        ];

        $result = PartnershipSettings::sanitize([
            'slack_channel' => 'not a channel!',
            'calendly_url' => 'https://evil.example/calendly.com/x',
            'calendly_event_type' => 'https://calendly.com/remoteleverage/partnership-call',
        ], $current);

        expect($result['settings'])->toBe($current)
            ->and($result['errors'])->toHaveCount(3);
    });

    test('accept blanks as switching a value off, and trim a trailing slash off the event type', function () {
        $result = PartnershipSettings::sanitize([
            'slack_channel' => '',
            'calendly_url' => '',
            'calendly_event_type' => 'https://api.calendly.com/event_types/abc-123/',
        ], ['slack_channel' => '#partnerships', 'calendly_url' => 'https://calendly.com/x/y', 'calendly_event_type' => '']);

        expect($result['errors'])->toBe([])
            ->and($result['settings'])->toBe([
                'slack_channel' => '',
                'calendly_url' => '',
                'calendly_event_type' => 'https://api.calendly.com/event_types/abc-123',
            ]);
    });

    test('are what the listener and the calendar read', function () {
        partnershipSettings([
            'slack_channel' => '#partnerships',
            'calendly_url' => 'https://calendly.com/remoteleverage/partnership-call',
        ]);

        expect(PartnershipSettings::slackChannel())->toBe('#partnerships')
            ->and(PartnershipCallCalendar::url())->toBe('https://calendly.com/remoteleverage/partnership-call');
    });
});
