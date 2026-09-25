<?php

declare(strict_types=1);

use App\Application\Livewire\Booking\MultistepBookingWizard;
use Illuminate\Http\Request;

/**
 * The wizard's attribution must belong to the visitor who submits it.
 *
 * mount() runs on the page render, and logged-out pages come from an HTML cache that ignores the
 * query string. So the snapshot a visitor hydrates carries whoever filled the cache — their
 * fbclid, IP, user agent and session id — and from 2026-09-22 that was ~75-80% of Meta-sourced
 * leads, sent to the Conversions API as somebody else's click. See
 * docs/booking-rate-diagnostic-2026-09-25.md.
 */
afterEach(function () {
    // The container is shared across the run; a bound request would leak into later tests.
    app()->forgetInstance('request');
});

/** A wizard as the cache served it: every attribution field set to the cache-filler's values. */
function staleWizard(): MultistepBookingWizard
{
    $wizard = new MultistepBookingWizard;
    $wizard->utmSource = 'facebook';
    $wizard->utmCampaign = 'someone-elses-campaign';
    $wizard->fbclid = 'CACHEFILLERCLICK';
    $wizard->gclid = 'CACHEFILLERGCLID';
    $wizard->ipAddress = '2a03:2880:18ff:69::';
    $wizard->attribution = ['user_agent' => 'facebookexternalhit/1.1'];
    $wizard->attributionNamed = ['fbclid' => 'CACHEFILLERCLICK', 'fbc' => 'fb.1.1.CACHEFILLERCLICK'];
    $wizard->landingUrl = 'https://remoteleverage.com/hire-va-4/?fbclid=CACHEFILLERCLICK';
    $wizard->sessionId = 'shared-session-id';
    $wizard->referralCode = 'someone-elses-code';

    return $wizard;
}

/** The visitor's own Livewire submit: never cached, so its headers and cookies are theirs. */
function bindLivewireSubmit(array $cookies = [], array $server = []): void
{
    app()->instance('request', Request::create('https://remoteleverage.com/livewire-0faf9a78/update', 'POST', [], $cookies, [], array_merge([
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 130.176.0.1',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) FBAN/FBIOS',
    ], $server)));
}

function refreshContext(MultistepBookingWizard $wizard): void
{
    (fn () => $this->refreshVisitorContext())->call($wizard);
}

describe('refreshVisitorContext', function () {
    test('replaces every cached value with the submitting visitor\'s own', function () {
        bindLivewireSubmit(['_fbc' => 'fb.1.1727000000000.MYOWNCLICK']);

        $wizard = staleWizard();
        $wizard->clientPageUrl = 'https://remoteleverage.com/hire-va-4/?utm_source=facebook&utm_campaign=my-campaign&utm_id=120237164660760522&fbclid=MYOWNCLICK';
        $wizard->clientReferrer = 'https://l.facebook.com/';

        refreshContext($wizard);

        expect($wizard->fbclid)->toBe('MYOWNCLICK')
            ->and($wizard->utmCampaign)->toBe('my-campaign')
            ->and($wizard->gclid)->toBe('')
            ->and($wizard->attributionNamed['fbclid'])->toBe('MYOWNCLICK')
            ->and($wizard->attributionNamed['fbc'])->toBe('fb.1.1727000000000.MYOWNCLICK')
            ->and($wizard->attributionNamed['utm_id'])->toBe('120237164660760522')
            ->and($wizard->ipAddress)->toBe('203.0.113.7')
            ->and($wizard->attribution['user_agent'])->toBe('Mozilla/5.0 (iPhone) FBAN/FBIOS')
            ->and($wizard->landingUrl)->toBe($wizard->clientPageUrl)
            ->and($wizard->referrerUrl)->toBe('https://l.facebook.com/')
            ->and($wizard->referralCode)->toBeNull()
            ->and($wizard->sessionId)->not->toBe('shared-session-id');
    });

    test('falls back to the Referer the same-origin XHR carries when the browser sent no page URL', function () {
        bindLivewireSubmit([], ['HTTP_REFERER' => 'https://remoteleverage.com/hire-va-4/?fbclid=FROMREFERER&via=partner42']);

        $wizard = staleWizard();

        refreshContext($wizard);

        expect($wizard->fbclid)->toBe('FROMREFERER')
            ->and($wizard->referralCode)->toBe('partner42')
            ->and($wizard->landingUrl)->toBe('https://remoteleverage.com/hire-va-4/?fbclid=FROMREFERER&via=partner42');
    });

    test('drops a borrowed click id rather than keeping it when the visitor has none', function () {
        bindLivewireSubmit();

        $wizard = staleWizard();
        $wizard->clientPageUrl = 'https://remoteleverage.com/vacalendar/';

        refreshContext($wizard);

        expect($wizard->fbclid)->toBe('')
            ->and($wizard->utmSource)->toBe('')
            ->and($wizard->attributionNamed)->not->toHaveKey('fbc');
    });

    test('keeps one session id for the whole visit', function () {
        bindLivewireSubmit();

        $wizard = staleWizard();
        refreshContext($wizard);
        $first = $wizard->sessionId;

        refreshContext($wizard);

        expect($wizard->sessionId)->toBe($first)
            ->and($wizard->visitorContextRefreshed)->toBeTrue();
    });

    test('ignores a page URL that is not an absolute http(s) URL', function () {
        bindLivewireSubmit([], ['HTTP_REFERER' => 'https://remoteleverage.com/hire-va/?fbclid=REAL']);

        $wizard = staleWizard();
        $wizard->clientPageUrl = 'javascript:alert(1)';

        refreshContext($wizard);

        expect($wizard->fbclid)->toBe('REAL');
    });

    test('runs before step 1 reads anything, and again on the booking submit', function () {
        $php = file_get_contents(__DIR__.'/../../app/Application/Livewire/Booking/MultistepBookingWizard.php');

        $step1 = strpos($php, 'if ($this->currentStep === 1) {');
        expect(strpos($php, '$this->refreshVisitorContext();', $step1))
            ->toBeLessThan(strpos($php, 'EmailValidationService::class', $step1));

        $submit = strpos($php, 'public function submitBooking(): void');
        expect(strpos($php, '$this->refreshVisitorContext();', $submit))
            ->toBeLessThan(strpos($php, "trackStepEvent('booking_request_sent'", $submit));
    });

    test('the browser pushes its own page URL and referrer, and never an _fbc as a click id', function () {
        $blade = file_get_contents(__DIR__.'/../../resources/views/livewire/booking/multistep-booking-wizard.blade.php');

        expect($blade)
            ->toContain("\$wire.set('clientPageUrl', window.location.href, false)")
            ->toContain("\$wire.set('clientReferrer', document.referrer || '', false)")
            ->not->toContain("getCookie('fbclid') || getCookie('_fbc')");
    });
});
