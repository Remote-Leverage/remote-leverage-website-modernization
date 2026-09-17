<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Referral\Actions\TrackReferralClickAction;
use App\Domains\Referral\Models\ReferralClick;
use App\Domains\Referral\Models\Referrer;
use App\Domains\Referral\Services\AttributionEngine;
use App\Domains\Referral\Services\ReferralSettingsService;
use App\Domains\Referral\Services\ReferralVisitorContext;
use App\Domains\Referral\Support\ReferralLink;
use App\Domains\Referral\Support\ReferralWelcomeNotice;

beforeEach(function () {
    $GLOBALS['_wp_mock_options']['rl_referral_settings'] = [
        'visitor_notice_enabled' => true,
        'visitor_discount_amount' => 500,
        'visitor_notice_template' => ReferralSettingsService::DEFAULT_VISITOR_NOTICE,
    ];

    // The context reads the request straight off the superglobals, because it runs on a
    // WordPress hook rather than inside Laravel's request lifecycle.
    $_GET = [];
    $_COOKIE = [];
});

function offerReferrer(array $overrides = []): Referrer
{
    return Referrer::query()->create(array_merge([
        'name' => 'Dana Whitfield',
        'email' => 'offer-'.uniqid().'@agency.com',
        'referral_code' => 'offer'.uniqid(),
        'status' => 'active',
    ], $overrides));
}

function visitorContext(): ReferralVisitorContext
{
    $engine = new AttributionEngine;

    return new ReferralVisitorContext($engine, new TrackReferralClickAction($engine));
}

describe('the shared link', function () {
    it('always points at the hire-va-4 landing page', function () {
        $url = ReferralLink::for('ABC123');

        expect($url)->toContain('/'.ReferralLink::DESTINATION_PATH.'/')
            ->and($url)->toEndWith('?via=ABC123');
    });

    it('encodes a code that would otherwise break the query string', function () {
        expect(ReferralLink::for('a b&c=d'))->toEndWith('?via=a%20b%26c%3Dd');
    });

    it('builds a link to any page on the curated list', function () {
        expect(ReferralLink::for('ABC123', 'stealing-jobs'))
            ->toContain('/stealing-jobs/?via=ABC123');
    });

    it('puts the homepage at an empty path rather than a literal slug', function () {
        $url = ReferralLink::for('ABC123', '');

        expect($url)->not->toContain('//?via=')
            ->and($url)->toEndWith('/?via=ABC123');
    });

    it('falls back to the default for a page that is not on the list', function () {
        // The path arrives from the browser, so an arbitrary one must never end up in the link
        // a referrer is then told to share.
        expect(ReferralLink::for('ABC123', 'wp-admin'))
            ->toContain('/'.ReferralLink::DESTINATION_PATH.'/')
            ->and(ReferralLink::isAllowed('wp-admin'))->toBeFalse();
    });

    it('offers the homepage first and the hiring page as the default', function () {
        $destinations = ReferralLink::destinations();

        expect($destinations[0]['path'])->toBe('')
            ->and(array_column($destinations, 'path'))->toContain(ReferralLink::DESTINATION_PATH)
            // Every tile renders an image and a name, so a missing one is a broken card.
            ->and(collect($destinations)->every(fn ($d) => $d['image'] !== '' && $d['name'] !== ''))->toBeTrue();
    });
});

describe('previewing a page from the portal', function () {
    it('signs the preview link per referrer', function () {
        $url = ReferralLink::preview('CODE-A');

        expect($url)->toContain('&'.ReferralLink::PREVIEW_PARAM.'=');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $token = $query[ReferralLink::PREVIEW_PARAM];

        expect(ReferralLink::isValidPreviewToken('CODE-A', $token))->toBeTrue()
            // A token lifted from one referrer's preview must not silence another's click.
            ->and(ReferralLink::isValidPreviewToken('CODE-B', $token))->toBeFalse()
            ->and(ReferralLink::isValidPreviewToken('CODE-A', 'guessed'))->toBeFalse()
            ->and(ReferralLink::isValidPreviewToken('CODE-A', ''))->toBeFalse();
    });

    it('does not record a click or set the cookie when previewing', function () {
        $referrer = offerReferrer();

        parse_str((string) parse_url(ReferralLink::preview($referrer->referral_code), PHP_URL_QUERY), $query);

        $_GET['via'] = $referrer->referral_code;
        $_GET[ReferralLink::PREVIEW_PARAM] = $query[ReferralLink::PREVIEW_PARAM];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $context = visitorContext();

        expect($context->isPreview())->toBeTrue()
            ->and($context->referrer()?->id)->toBe($referrer->id)
            // Otherwise a referrer checking their own links inflates the reach figure their
            // own dashboard reports back to them.
            ->and(ReferralClick::where('referrer_id', $referrer->id)->count())->toBe(0)
            // And would be attributed to themselves for the next 60 days.
            ->and($_COOKIE[AttributionEngine::COOKIE_NAME] ?? null)->toBeNull();
    });

    it('still records a click when the preview token is forged', function () {
        $referrer = offerReferrer();

        $_GET['via'] = $referrer->referral_code;
        $_GET[ReferralLink::PREVIEW_PARAM] = 'not-a-real-token';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $context = visitorContext();

        expect($context->isPreview())->toBeFalse()
            ->and(ReferralClick::where('referrer_id', $referrer->id)->count())->toBe(1);
    });
});

describe('arriving on a referral link', function () {
    it('resolves the referrer, records the click and remembers the code', function () {
        $referrer = offerReferrer();
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $context = visitorContext();

        expect($context->referrer()?->id)->toBe($referrer->id)
            ->and($context->arrivedFromLink())->toBeTrue()
            ->and(ReferralClick::where('referrer_id', $referrer->id)->count())->toBe(1)
            // Written back onto the request so anything resolving later in the same render
            // sees it, rather than waiting a round trip for the browser to send it back.
            ->and($_COOKIE[AttributionEngine::COOKIE_NAME] ?? null)->toBe($referrer->referral_code);
    });

    it('treats a returning visitor holding only the cookie as attributed but not arriving', function () {
        $referrer = offerReferrer();
        $_COOKIE[AttributionEngine::COOKIE_NAME] = $referrer->referral_code;

        $context = visitorContext();

        // Still attributed — their referral survives navigation — but no second click is
        // recorded and the welcome notice does not follow them around the site.
        expect($context->referrer()?->id)->toBe($referrer->id)
            ->and($context->arrivedFromLink())->toBeFalse()
            ->and(ReferralClick::where('referrer_id', $referrer->id)->count())->toBe(0);
    });

    it('ignores a code that belongs to no referrer', function () {
        $_GET['via'] = 'not-a-real-code-'.uniqid();

        $context = visitorContext();

        expect($context->referrer())->toBeNull()
            ->and($context->arrivedFromLink())->toBeFalse();
    });
});

describe('the welcome notice', function () {
    it('names the referrer and the discount', function () {
        $referrer = offerReferrer(['name' => 'Dana Whitfield']);
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $notice = new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService);

        expect($notice->shouldShow())->toBeTrue()
            ->and($notice->message())->toBe('Dana Whitfield is giving you a $500 discount with Remote Leverage!')
            ->and($notice->formattedAmount())->toBe('$500');
    });

    it('emphasises the referrer name and the amount', function () {
        $referrer = offerReferrer(['name' => 'Dana Whitfield']);
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $html = (new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService))->messageHtml();

        expect($html)->toContain('<strong class="font-bold">Dana Whitfield</strong>')
            ->and($html)->toContain('<strong class="font-bold">$500</strong>')
            ->and($html)->toContain('is giving you a');
    });

    it('escapes an administrator-authored template rather than executing it', function () {
        $GLOBALS['_wp_mock_options']['rl_referral_settings']['visitor_notice_template'] =
            '{referrer} <script>alert(1)</script> gives {amount}';

        $referrer = offerReferrer(['name' => 'Dana Whitfield']);
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $html = (new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService))->messageHtml();

        // The markup is echoed unescaped in the template, so this is the only thing between
        // the settings screen and stored XSS on a public landing page.
        expect($html)->not->toContain('<script>')
            ->and($html)->toContain('&lt;script&gt;')
            ->and($html)->toContain('<strong class="font-bold">Dana Whitfield</strong>');
    });

    it('escapes a hostile referrer name', function () {
        $referrer = offerReferrer(['name' => '<img src=x onerror=alert(1)>']);
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $html = (new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService))->messageHtml();

        // Referrers self-register, so the name is attacker-controlled input reaching a public page.
        expect($html)->not->toContain('<img')
            ->and($html)->toContain('&lt;img');
    });

    it('shows nothing to ordinary traffic', function () {
        $notice = new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService);

        expect($notice->shouldShow())->toBeFalse()
            ->and($notice->message())->toBeNull();
    });

    it('stays hidden when the offer is switched off', function () {
        $GLOBALS['_wp_mock_options']['rl_referral_settings']['visitor_notice_enabled'] = false;

        $referrer = offerReferrer();
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $notice = new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService);

        expect($notice->shouldShow())->toBeFalse();
    });

    it('formats a larger figure with a thousands separator', function () {
        $GLOBALS['_wp_mock_options']['rl_referral_settings']['visitor_discount_amount'] = 1500;

        $referrer = offerReferrer();
        $_GET['via'] = $referrer->referral_code;
        $_SERVER['REMOTE_ADDR'] = '203.0.113.'.random_int(1, 254);

        $notice = new ReferralWelcomeNotice(visitorContext(), new ReferralSettingsService);

        expect($notice->formattedAmount())->toBe('$1,500')
            ->and($notice->message())->toContain('$1,500');
    });
});

describe('recording what the prospect was promised', function () {
    it('stamps the discount on a lead that came through a referral', function () {
        $referrer = offerReferrer();

        $lead = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Referred Prospect',
            'email' => 'promised-'.uniqid().'@client.com',
            'referral_code' => $referrer->referral_code,
        ]));

        // Nothing applies the discount automatically, so this record is the only thing that
        // tells the sales team an offer was made at all.
        expect($lead->source_type)->toBe('referral_hub')
            ->and($lead->attribution['referral_discount_offered'] ?? null)->toBe(500);
    });

    it('stamps nothing on organic traffic', function () {
        $lead = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Organic Prospect',
            'email' => 'organic-'.uniqid().'@client.com',
        ]));

        expect($lead->source_type)->toBe('organic')
            ->and($lead->attribution['referral_discount_offered'] ?? null)->toBeNull();
    });

    it('keeps the amount promised at acquisition when the setting later changes', function () {
        $referrer = offerReferrer();

        $first = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Returning Prospect',
            'email' => 'first-touch-'.uniqid().'@client.com',
            'referral_code' => $referrer->referral_code,
        ]));

        expect($first->attribution['referral_discount_offered'] ?? null)->toBe(500);

        $GLOBALS['_wp_mock_options']['rl_referral_settings']['visitor_discount_amount'] = 50;

        // Re-submitting against the SAME lead row is the only path that updates rather than
        // inserts — CaptureLeadAction creates a new Lead for every other submission, with
        // identity grouped afterwards by LeadProfile.
        $updated = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Returning Prospect',
            'email' => $first->email,
            'referral_code' => $referrer->referral_code,
            'extra_data' => ['lead_id' => $first->id],
        ]));

        // The offer they were shown is the offer owed, even after the setting is cut.
        expect($updated->id)->toBe($first->id)
            ->and($updated->attribution['referral_discount_offered'] ?? null)->toBe(500);
    });

    it('stamps a later lead at the amount in force when that lead arrived', function () {
        $referrer = offerReferrer();
        $GLOBALS['_wp_mock_options']['rl_referral_settings']['visitor_discount_amount'] = 750;

        $lead = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Later Prospect',
            'email' => 'later-'.uniqid().'@client.com',
            'referral_code' => $referrer->referral_code,
        ]));

        // Each new lead is stamped at the current offer; only the row already carrying a
        // promise is protected from a change.
        expect($lead->attribution['referral_discount_offered'] ?? null)->toBe(750);
    });
});
