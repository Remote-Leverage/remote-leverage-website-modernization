<?php

declare(strict_types=1);

use App\Application\Http\Controllers\GatedDownloadController;
use App\Blocks\ImpactReportHeroBlock;
use App\Domains\Lead\Actions\CaptureLeadAction;
use App\Domains\Lead\Data\LeadCaptureData;
use App\Domains\Lead\Events\LeadCreated;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Lead\Services\GatedAssetResolver;
use App\Domains\Lead\Services\LeadActivityLogger;
use App\Domains\Lead\Services\PhoneValidationService;
use App\Domains\Referral\Services\AttributionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

/*
 * The gated download on /impact-report-2026/, which replaces Gravity Forms form 33
 * (retired by ADR-0008). The point of these tests is that the capture goes through the
 * Lead domain's one capture path — same Lead row, same dispatch log, same LeadCreated —
 * and that the file served is decided from config, never from what the browser posted.
 */

const GATED_PDF_URL = 'https://remoteleverage-v2.test/app/uploads/2026/09/Remote_Leverage-Impact_Report-2026-1.pdf';

/**
 * The real controller with the real collaborators behind it — same construction as
 * LeadDomainTest. Nothing that writes is doubled, because "the lead actually landed" is
 * what these tests are for.
 */
function gatedDownloadController(): GatedDownloadController
{
    return new GatedDownloadController(
        new CaptureLeadAction(new AttributionEngine, new PhoneValidationService, new LeadActivityLogger),
        new GatedAssetResolver,
    );
}

function gatedDownloadRequest(array $payload, bool $json = true): Request
{
    $request = Request::create('/api/leads/gated-download', 'POST', $payload);

    if ($json) {
        $request->headers->set('Accept', 'application/json');
    }

    return $request;
}

describe('GatedDownloadController', function () {
    beforeEach(function () {
        LeadActivityLog::truncate();
        Lead::truncate();

        // The registry the endpoint resolves against. `url` is pinned here because
        // MediaLibrary needs a booted WordPress ($wpdb) to look the file up by name,
        // which is exactly the fallback GatedAssetResolver::url() documents.
        config(['gated-assets' => [
            'impact-report-2026' => [
                'title' => 'The Remote Leverage 2026 Impact Report',
                'filename' => 'Remote_Leverage-Impact_Report-2026-1.pdf',
                'url' => GATED_PDF_URL,
            ],
        ]]);

    });

    test('captures the visitor as a Lead and returns the download url', function () {
        $created = [];
        Event::listen(LeadCreated::class, function ($e) use (&$created) {
            $created[] = $e;
        });

        $response = gatedDownloadController()->store(gatedDownloadRequest([
            'asset' => 'impact-report-2026',
            'name' => 'Pam Beesly',
            'email' => 'Pam.Beesly@DunderMifflin.com',
            'utm_source' => 'linkedin',
            'utm_campaign' => 'impact-report-2026',
        ]));

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode((string) $response->getContent(), true);
        expect($payload['ok'])->toBeTrue()
            ->and($payload['download_url'])->toBe(GATED_PDF_URL);

        $lead = Lead::query()->first();
        expect($lead)->not->toBeNull()
            ->and($lead->name)->toBe('Pam Beesly')
            ->and($lead->first_name)->toBe('Pam')
            ->and($lead->last_name)->toBe('Beesly')
            ->and($lead->email)->toBe('pam.beesly@dundermifflin.com')
            ->and($lead->utm_source)->toBe('linkedin')
            ->and($lead->utm_campaign)->toBe('impact-report-2026');

        // Same downstream fan-out as any other lead capture — this is the whole point of
        // routing through CaptureLeadAction instead of building a second lead path.
        expect(count($created))->toBe(1)
            ->and($created[0]->lead->id)->toBe($lead->id)
            ->and($created[0]->context['extra_data']['gated_asset'])->toBe('impact-report-2026')
            ->and($created[0]->context['extra_data']['source_form'])->toBe('ImpactReportHero');

        expect(LeadActivityLog::query()->where('lead_id', $lead->id)->where('event_type', 'LeadCreated')->exists())
            ->toBeTrue();
    });

    test('a plain form post redirects to the file', function () {
        $response = gatedDownloadController()->store(gatedDownloadRequest([
            'asset' => 'impact-report-2026',
            'name' => 'Jim Halpert',
            'email' => 'jim@dundermifflin.com',
        ], json: false));

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getTargetUrl())->toBe(GATED_PDF_URL)
            ->and(Lead::query()->count())->toBe(1);
    });

    test('an unregistered asset slug is refused and captures nothing', function () {
        $response = gatedDownloadController()->store(gatedDownloadRequest([
            'asset' => 'some-other-report',
            'name' => 'Dwight Schrute',
            'email' => 'dwight@dundermifflin.com',
        ]));

        expect($response->getStatusCode())->toBe(404)
            ->and(Lead::query()->count())->toBe(0);
    });

    test('a posted url is not a slug and gets nowhere', function () {
        // The browser must never be able to name the file. If this ever passes a URL
        // through, the endpoint has become an open file proxy.
        $response = gatedDownloadController()->store(gatedDownloadRequest([
            'asset' => 'https://example.com/evil.pdf',
            'name' => 'Dwight Schrute',
            'email' => 'dwight@dundermifflin.com',
        ]));

        expect($response->getStatusCode())->toBe(404);

        $payload = json_decode((string) $response->getContent(), true);
        expect($payload['ok'])->toBeFalse()
            ->and(json_encode($payload))->not->toContain('evil.pdf');
    });

    test('a missing or malformed email is rejected before anything is captured', function (array $payload) {
        $response = gatedDownloadController()->store(gatedDownloadRequest(array_merge([
            'asset' => 'impact-report-2026',
        ], $payload)));

        expect($response->getStatusCode())->toBe(422)
            ->and(Lead::query()->count())->toBe(0);
    })->with([
        'no email' => [['name' => 'Kevin Malone', 'email' => '']],
        'no name' => [['name' => '', 'email' => 'kevin@dundermifflin.com']],
        'malformed email' => [['name' => 'Kevin Malone', 'email' => 'kevin-at-dundermifflin']],
    ]);

    test('the visitor still gets the file when lead capture blows up', function () {
        $capture = new class(new AttributionEngine, new PhoneValidationService, new LeadActivityLogger) extends CaptureLeadAction
        {
            public function execute(LeadCaptureData $data): Lead
            {
                throw new RuntimeException('HubSpot is down');
            }
        };

        $controller = new GatedDownloadController($capture, new GatedAssetResolver);

        $response = $controller->store(gatedDownloadRequest([
            'asset' => 'impact-report-2026',
            'name' => 'Stanley Hudson',
            'email' => 'stanley@dundermifflin.com',
        ]));

        expect($response->getStatusCode())->toBe(200)
            ->and(json_decode((string) $response->getContent(), true)['download_url'])->toBe(GATED_PDF_URL);
    });
});

describe('GatedAssetResolver', function () {
    beforeEach(function () {
        config(['gated-assets' => [
            'impact-report-2026' => [
                'title' => 'The Remote Leverage 2026 Impact Report',
                'filename' => 'Remote_Leverage-Impact_Report-2026-1.pdf',
                'url' => GATED_PDF_URL,
            ],
        ]]);
    });

    test('resolves a registered slug to its title and url', function () {
        expect((new GatedAssetResolver)->resolve('impact-report-2026'))->toBe([
            'slug' => 'impact-report-2026',
            'title' => 'The Remote Leverage 2026 Impact Report',
            'url' => GATED_PDF_URL,
        ]);
    });

    test('returns null for anything not in the registry', function (string $slug) {
        expect((new GatedAssetResolver)->resolve($slug))->toBeNull();
    })->with([
        'empty' => [''],
        'unknown slug' => ['not-a-report'],
        'a url' => ['https://example.com/evil.pdf'],
        'traversal' => ['../../wp-config.php'],
        'config traversal into a sibling key' => ['impact-report-2026.filename'],
    ]);

    test('a registered asset with no resolvable file yields null rather than a broken link', function () {
        // What a fresh install looks like before the PDF has been imported: the slug is
        // known, but nothing in the media library answers to it.
        config(['gated-assets' => [
            'impact-report-2026' => [
                'title' => 'The Remote Leverage 2026 Impact Report',
                'filename' => 'Remote_Leverage-Impact_Report-2026-1.pdf',
                'url' => '',
            ],
        ]]);

        expect((new GatedAssetResolver)->resolve('impact-report-2026'))->toBeNull();
    });

    test('the shipped config registers the impact report against the real production filename', function () {
        $config = require dirname(__DIR__, 2).'/config/gated-assets.php';

        expect($config)->toHaveKey('impact-report-2026')
            ->and($config['impact-report-2026']['filename'])->toBe('Remote_Leverage-Impact_Report-2026-1.pdf')
            // An override shipped in git would pin one environment's URL for every install.
            ->and($config['impact-report-2026']['url'])->toBe('');
    });
});

describe('the gated download endpoint is registered', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    test('routes/api.php exposes it under the api prefix', function () {
        expect(Route::has('api.leads.gated-download'))->toBeTrue();

        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'api.leads.gated-download');

        expect($route->uri())->toBe('api/leads/gated-download')
            ->and($route->methods())->toContain('POST');
    });

    test("the block's default form action matches the registered route", function () {
        // These drifting apart is precisely how the form ended up posting nowhere before.
        expect('/'.trim(ImpactReportHeroBlock::DEFAULT_FORM_ACTION, '/'))
            ->toBe('/api/leads/gated-download');
    });

    test('the hero view posts the asset slug as a hidden field', function () {
        $blade = (string) file_get_contents(
            dirname(__DIR__, 2).'/resources/views/blocks/impact-report-hero.blade.php'
        );

        expect($blade)->toContain('name="asset"')
            ->and($blade)->toContain('$assetSlug')
            ->and($blade)->toContain('action="{{ $formAction }}"');
    });
});
