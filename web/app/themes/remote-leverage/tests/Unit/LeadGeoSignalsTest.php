<?php

declare(strict_types=1);

use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Services\LeadGeoSignals;
use Illuminate\Http\Request;

/*
 * Inferring a lead's country without asking, and without trusting the phone's dialling code.
 *
 * The case that matters is the disagreement: an IP in one country and a browser clock in
 * another is usually a VPN, and the timezone is the one a VPN does not move.
 */

function geoRequest(array $headers = [], array $cookies = []): Request
{
    $server = [];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return Request::create('https://remoteleverage.com/livewire-abc/update', 'POST', [], $cookies, [], $server);
}

describe('LeadGeoSignals', function () {
    test('agreeing IP and timezone are the confident case', function () {
        $geo = (new LeadGeoSignals)->collect(geoRequest(
            ['CloudFront-Viewer-Country' => 'PH'],
            ['rl_tz' => 'Asia/Manila', 'rl_lang' => 'en-US'],
        ));

        expect($geo)->toBe([
            'ip_country' => 'PH',
            'browser_timezone' => 'Asia/Manila',
            'browser_language' => 'en-US',
            'country' => 'PH',
            'country_source' => 'ip+timezone',
        ]);
    });

    test('the timezone wins over a disagreeing IP, and the disagreement stays visible', function () {
        $geo = (new LeadGeoSignals)->collect(geoRequest(
            ['CloudFront-Viewer-Country' => 'US'],
            ['rl_tz' => 'Europe/London'],
        ));

        expect($geo['country'])->toBe('GB')
            ->and($geo['ip_country'])->toBe('US')
            ->and($geo['country_source'])->toBe('timezone_over_ip');
    });

    test('falls back to Cloudflare, then to either signal alone, then to the language region', function () {
        $geo = new LeadGeoSignals;

        expect($geo->collect(geoRequest(['CF-IPCountry' => 'mx']))['country'])->toBe('MX')
            ->and($geo->collect(geoRequest([], ['rl_tz' => 'America/Bogota']))['country_source'])->toBe('timezone')
            ->and($geo->collect(geoRequest([], ['rl_lang' => 'en-AU']))['country'])->toBe('AU')
            ->and($geo->collect(geoRequest([], ['rl_lang' => 'en'])))->not->toHaveKey('country');
    });

    test('a zone with no country, a forged cookie and an edge placeholder are not locations', function () {
        $geo = (new LeadGeoSignals)->collect(geoRequest(
            ['CloudFront-Viewer-Country' => 'XX'],
            ['rl_tz' => 'Not/AZone', 'rl_lang' => '<script>'],
        ));

        expect($geo)->toBe([])
            ->and((new LeadGeoSignals)->timezoneCountry('UTC'))->toBeNull()
            ->and((new LeadGeoSignals)->timezoneCountry('Etc/GMT+5'))->toBeNull();
    });

    test('the signals land on the lead through attribution_named', function () {
        $geo = (new LeadGeoSignals)->collect(geoRequest(
            ['CloudFront-Viewer-Country' => 'CA'],
            ['rl_tz' => 'America/Toronto'],
        ));

        $lead = app(CaptureLeadAction::class)->execute(LeadCaptureData::fromArray([
            'name' => 'Geo Test',
            'email' => 'geo-'.uniqid().'@example.com',
            'attribution_named' => $geo,
        ]));

        $stored = Lead::query()->find($lead->id);

        expect($stored->country)->toBe('CA')
            ->and($stored->ip_country)->toBe('CA')
            ->and($stored->browser_timezone)->toBe('America/Toronto')
            ->and($stored->country_source)->toBe('ip+timezone');
    });
});
