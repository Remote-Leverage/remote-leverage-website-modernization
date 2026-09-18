<?php

declare(strict_types=1);

use App\Application\Http\Middleware\LegacyRedirectMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

describe('Application Routes', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        // Guard each route file on a name it actually defines. A single shared sentinel
        // is order-dependent: GatedDownloadTest registers api.php alone behind the same
        // `api.health` check, so when it ran first this block short-circuited and web.php
        // never loaded — every web-route assertion then failed, while the file still
        // passed in isolation. That made the suite intermittently red.
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
        }

        if (! Route::has('funnel.book-consultation')) {
            Route::middleware([])->group($baseDir.'/routes/web.php');
        }

        Route::getRoutes()->refreshNameLookups();
    });

    test('api routes are registered', function () {
        expect(Route::has('api.webhooks.stripe'))->toBeTrue()
            ->and(Route::has('api.webhooks.calendly'))->toBeTrue()
            ->and(Route::has('api.health'))->toBeTrue();
    });

    test('web routes are registered for standalone funnels', function () {
        expect(Route::has('funnel.book-consultation'))->toBeTrue()
            ->and(Route::has('live-call.connect'))->toBeTrue()
            ->and(Route::has('referrer.portal'))->toBeTrue()
            ->and(Route::has('referrer.register'))->toBeTrue()
            ->and(Route::has('referrer.dashboard.legacy'))->toBeTrue();
    });

    test('health check route returns healthy json response', function () {
        $request = Request::create('/api/health', 'GET');
        $response = Route::dispatch($request);

        expect($response->getStatusCode())->toBe(200);

        $payload = json_decode((string) $response->getContent(), true);
        expect($payload)->toHaveKey('status', 'healthy')
            ->and($payload)->toHaveKey('timestamp');
    });
});

describe('config/redirects.php targets resolve to something real', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::middleware([])->group($baseDir.'/routes/web.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    /*
     * A redirect target must be one of three things: a registered Laravel route, a real
     * WordPress page, or an absolute http(s):// URL on another site (see $externalTargets).
     * Tests have no database, so page-slug targets are declared here and verified by
     * hand against `wp post list --post_type=page`; the date below is that check.
     */
    $wordPressPageTargets = [
        // The fourteen 2026 role landing pages. Each renders patterns/<slug>-full.php off the
        // shared App\Support\RolePages map; created and verified 2026-09-16. The long tail of
        // role aliases now redirects to these rather than to /hire-va-4/.
        'admin-virtual-assistants' => 'page ID 1000195 — created and verified 2026-09-16',
        'executive-virtual-assistants' => 'page ID 1000196 — created and verified 2026-09-16',
        'customer-support-virtual-assistants' => 'page ID 1000197 — created and verified 2026-09-16',
        'sales-virtual-assistants' => 'page ID 1000198 — created and verified 2026-09-16',
        'lead-generation-virtual-assistants' => 'page ID 1000199 — created and verified 2026-09-16',
        'social-media-virtual-assistants' => 'page ID 1000200 — created and verified 2026-09-16',
        'marketing-virtual-assistants' => 'page ID 1000201 — created and verified 2026-09-16',
        'graphic-design-virtual-assistants' => 'page ID 1000202 — created and verified 2026-09-16',
        'medical-virtual-assistants' => 'page ID 1000203 — created and verified 2026-09-16',
        'legal-virtual-assistants' => 'page ID 1000204 — created and verified 2026-09-16',
        'insurance-virtual-assistants' => 'page ID 1000205 — created and verified 2026-09-16',
        'real-estate-virtual-assistants' => 'page ID 1000206 — created and verified 2026-09-16',
        'ecommerce-virtual-assistants' => 'page ID 1000207 — created and verified 2026-09-16',
        'bookkeeping-virtual-assistants' => 'page ID 1000208 — created and verified 2026-09-16',

        'vathankyou' => 'page ID 126, page-vathankyou.blade.php — verified 2026-09-14',
        'hire-va-4' => 'page ID 1000000, renders patterns/hire-va-4-full.php — created and verified 2026-09-14',

        // Targets of the ADR-0006 cutover map (the 164 discarded production URLs added to
        // config/redirects.php). All verified against `wp post list --post_type=page
        // --post_status=publish` on 2026-09-15.
        '' => 'the site root — front page, page ID 7 (slug "home"), front-page.blade.php — verified 2026-09-15',
        'blog' => 'page ID 799 — verified 2026-09-15',
        'vaonboardingguide' => 'page ID 1000078, renders patterns/vaonboardingguide.php — built and verified 2026-09-15. Distinct from /onboardingguide/ (page 1000077): production serves 3435px / 6 Vimeo embeds here against 10946px / 28 there.',
        'reviews' => 'page ID 292 — verified 2026-09-15',
        'vacalendar' => 'page ID 1000002 — verified 2026-09-15',
        'samples' => 'page ID 1000005 — verified 2026-09-15',
        'contractor-management' => 'page ID 1000006 — verified 2026-09-15',
        'contractor-payments' => 'page ID 1000007 — verified 2026-09-15',
        'hire-for-less' => 'page ID 1000031 — verified 2026-09-15',
        'vaonboardingform' => 'page ID 1000033 — verified 2026-09-15',
        'hire-va-6' => 'page ID 1000041 — verified 2026-09-15',
        'hire-virtual-assistants-from-latam-remote-leverage-isolated-form-fields-variant-b' => 'page ID 1000047 — verified 2026-09-15',
        'vastore5' => 'page ID 1000050 — verified 2026-09-15',
        '1monthonus' => 'page ID 1000052 — verified 2026-09-15',
        'hire-va-isolated-form' => 'page ID 1000053 — verified 2026-09-15',
        'hire-va' => 'page ID 1000066 — verified 2026-09-15',

        // Target of /partnership-program/ (604 lifetime hits), brought in by the legacy
        // redirect audit of 2026-09-18. Verified published in the v2 database that day.
        'referral-program' => 'published page — verified 2026-09-18',
    ];

    /*
     * A fifth valid target class: published content that is not a page — blog posts under
     * /blog/ and the case_study CPT under /case-study/. The legacy redirect audit of
     * 2026-09-18 brought in old permalinks whose correct destination is an article, not a
     * landing page, so declaring them as pages would have been a lie.
     *
     * Each was resolved against the v2 database rather than copied from the legacy row,
     * because the legacy targets carry pre-migration slugs. Re-verify with:
     *   wp post list --post_type=post,case_study --post_status=publish --field=post_name
     */
    $contentTargets = [
        'blog/athena-virtual-assistant' => 'post "athena-virtual-assistant" - verified against the v2 database 2026-09-18',
        'blog/best-medical-receptionist-services' => 'post "best-medical-receptionist-services" - verified against the v2 database 2026-09-18',
        'blog/executive-assistant-vs-virtual-assistant' => 'post "executive-assistant-vs-virtual-assistant" - verified against the v2 database 2026-09-18',
        'blog/marketing-virtual-assistant-vs-marketing-agency' => 'post "marketing-virtual-assistant-vs-marketing-agency" - verified against the v2 database 2026-09-18',
        'blog/patient-care-coordinator-cost' => 'post "patient-care-coordinator-cost" - verified against the v2 database 2026-09-18',
        'case-study' => 'case_study archive (has_archive => \'case-study\') - verified 2026-09-18',
        'case-study/anchorage-care-coordination' => 'case_study "anchorage-care-coordination" - verified against the v2 database 2026-09-18',
        'case-study/bench-accounting' => 'case_study "bench-accounting" - verified against the v2 database 2026-09-18',
        'case-study/conservice' => 'case_study "conservice" - verified against the v2 database 2026-09-18',
        'case-study/doran-industries' => 'case_study "doran-industries" - verified against the v2 database 2026-09-18',
        'case-study/fast-real-estate' => 'case_study "fast-real-estate" - verified against the v2 database 2026-09-18',
        'case-study/haus-of-her' => 'case_study "haus-of-her" - verified against the v2 database 2026-09-18',
        'case-study/hawaiian-philanthropy' => 'case_study "hawaiian-philanthropy" - verified against the v2 database 2026-09-18',
        'case-study/jeremis-auto-repair' => 'case_study "jeremis-auto-repair" - verified against the v2 database 2026-09-18',
        'case-study/mobile-mixologist' => 'case_study "mobile-mixologist" - verified against the v2 database 2026-09-18',
        'case-study/on-the-outskirt' => 'case_study "on-the-outskirt" - verified against the v2 database 2026-09-18',
        'case-study/smiley-injury-law' => 'case_study "smiley-injury-law" - verified against the v2 database 2026-09-18',
        'case-study/the-acre-hub' => 'case_study "the-acre-hub" - verified against the v2 database 2026-09-18',
        'case-study/watson-psychiatry' => 'case_study "watson-psychiatry" - verified against the v2 database 2026-09-18',
    ];

    /*
     * Absolute off-site destinations. These are a third valid target class: not a route and
     * not a page, but a real URL on another host. Declared here so adding one stays a
     * deliberate act — an unlisted external target still fails the test below.
     *
     * They are also the only hosts LegacyRedirectMiddleware will ever redirect off-site to,
     * because the allowlist it hands wp_safe_redirect() is derived from this same static map.
     */
    $externalTargets = [
        'https://anyshore.ai/' => 'Anyshore spun out onto its own domain; no v2 equivalent — 2026-09-15',
        'https://buy.stripe.com/cNi14nfrY18b7tX6DkfrW0E' => 'Production 301s /deposit/ to this Stripe payment link rather than serving a page; the /vastore5/ script uses it as the fallback deposit route — verified against production 2026-09-16',
        'https://form.jotform.com/252185178652665' => 'Production 301s /refund/ to this JostForm payment link rather than serving a page; verified against production 2026-09-17',

        /*
         * Vanity short links ported from the legacy GoDaddy install on 2026-09-18, after an
         * audit found two redirect stores there that had never been carried into v2 (the
         * `301-redirects` plugin and Yoast SEO Premium). These are operational links --
         * recruiting, Zoom rooms, Stripe, Calendly, assessment forms -- so the destination is
         * a third-party tool by design and there is nothing on-site to point them at. The hit
         * counts are lifetime totals read off the plugin's `last_count` column.
         * Full audit: docs/legacy-redirects-audit.md.
         */
        'https://remoteleveragejobs.com/?ref=https://remoteleverage.com/apply' => 'Vanity short link /apply/ ported from the GoDaddy 301-redirects plugin; 43636 lifetime hits - audited 2026-09-18',
        'https://calendly.com/pacific-recruiters/job-interview-remote-leverage-team-clone' => 'Vanity short link /recruitinginterview/ ported from the GoDaddy 301-redirects plugin; 19272 lifetime hits - audited 2026-09-18',
        'https://vimeo.com/1143608840/e4c8dc87eb?fl=tl&fe=ec' => 'Vanity short link /jobinterviewinstructions/ ported from the GoDaddy 301-redirects plugin; 7648 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/6153893236' => 'Vanity short link /recruiterszoom/ ported from the GoDaddy 301-redirects plugin; 5355 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/9794933436?pwd=dENpRHJLL2h2RVlTZjJaODRndVJnQT09' => 'Vanity short link /zoom/ ported from the GoDaddy 301-redirects plugin; 4897 lifetime hits - audited 2026-09-18',
        'https://forms.gle/rqHADHQk1M31TjGPA' => 'Vanity short link /submitvideo/ ported from the GoDaddy 301-redirects plugin; 3999 lifetime hits - audited 2026-09-18',
        'https://www.livechat.com/typing-speed-test/#/' => 'Vanity short link /typing/ ported from the GoDaddy 301-redirects plugin; 3367 lifetime hits - audited 2026-09-18',
        'https://forms.gle/3oUqzMLizn6bfPAS9' => 'Vanity short link /adminassessment/ ported from the GoDaddy 301-redirects plugin; 2104 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/9907147461?pwd=hwOW946MrYD3cpZYbIa9Fa6zkfpj1I.1' => 'Vanity short link /nyxzoom/ ported from the GoDaddy 301-redirects plugin; 1412 lifetime hits - audited 2026-09-18',
        'https://remoteleveragetech.atlassian.net/servicedesk/customer/portal/1/group/1/create/1' => 'Vanity short link /it/ ported from the GoDaddy 301-redirects plugin; 1235 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/7172910526?pwd=ryHFp0X4eFvSMerLFsae3LG2RArcno.1' => 'Vanity short link /zoom2/ ported from the GoDaddy 301-redirects plugin; 1231 lifetime hits - audited 2026-09-18',
        'https://calendly.com/claire-remoteleverage/job-interview-remote-leverage-team-clone' => 'Vanity short link /claireinterview/ ported from the GoDaddy 301-redirects plugin; 1049 lifetime hits - audited 2026-09-18',
        'https://forms.gle/Ntxhgn9hNfxBKjQN7' => 'Vanity short link /salesassessment/ ported from the GoDaddy 301-redirects plugin; 957 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/4055248514?pwd=XwiKkIbXlEoaaWjCP3wOqzratPxmqV.1' => 'Vanity short link /adminzoom/ ported from the GoDaddy 301-redirects plugin; 943 lifetime hits - audited 2026-09-18',
        'https://drive.google.com/file/d/1w1_Pi54QZBe5k2xxtbE3rCvClNaeWX0l/view?usp=sharing' => 'Vanity short link /w9/ ported from the GoDaddy 301-redirects plugin; 813 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/4469787983?pwd=iwK8oQenKMxgxGHSeEiteUs1G34KD8.1' => 'Vanity short link /interviewzoom/ ported from the GoDaddy 301-redirects plugin; 699 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/8438962979?pwd=BtNW4PHSBO1CXfkJ0kF46Kkzxrbonr.1' => 'Vanity short link /seifszoom/ ported from the GoDaddy 301-redirects plugin; 525 lifetime hits - audited 2026-09-18',
        'https://sso.online.tableau.com/public/idp/SSO' => 'Vanity short link /dashboards/ ported from the GoDaddy 301-redirects plugin; 493 lifetime hits - audited 2026-09-18',
        'https://calendly.com/eastern-recruiters-remoteleverage/job-interview-remote-leverage-team' => 'Vanity short link /estrecruitinginterview/ ported from the GoDaddy 301-redirects plugin; 447 lifetime hits - audited 2026-09-18',
        'https://anyshore.ai/blog/latin-american-va-salary-guide/' => 'Vanity short link /virtual-assistant-posts/salary-guide-for-businesses-hiring-virtual-assistants-guide/ ported from the GoDaddy 301-redirects plugin; 439 lifetime hits - audited 2026-09-18',
        'https://g.page/r/CTuB-J467qJwEAE/review' => 'Vanity short link /googlereview/ ported from the GoDaddy 301-redirects plugin; 336 lifetime hits - audited 2026-09-18',
        'https://recruitcrm.io/apply/17811178058780133835zgp' => 'Vanity short link /angelicagomez/ ported from the GoDaddy 301-redirects plugin; 279 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/4821528296?pwd=aG5Q6HhLv8Bwuf0b2Sr6xtqmDvzMaw.1' => 'Vanity short link /christinaszoom/ ported from the GoDaddy 301-redirects plugin; 278 lifetime hits - audited 2026-09-18',
        'https://calendly.com/d/cs4r-k2g-d2p/remote-leverage-testimonial-session' => 'Vanity short link /testimonial/ ported from the GoDaddy 301-redirects plugin; 276 lifetime hits - audited 2026-09-18',
        'https://recruitcrm.io/apply/17806985112870087768JmC' => 'Vanity short link /angie/ ported from the GoDaddy 301-redirects plugin; 184 lifetime hits - audited 2026-09-18',
        'https://recruitcrm.io/apply/17811401732160133835JtX' => 'Vanity short link /miry/ ported from the GoDaddy 301-redirects plugin; 144 lifetime hits - audited 2026-09-18',
        'https://docs.google.com/document/d/1UUGApiJ0W21RKI3GQ5QHncYJF1Wf1RraPjg1LG4un1Q/edit?usp=sharing' => 'Vanity short link /marketingmaterials/ ported from the GoDaddy 301-redirects plugin; 137 lifetime hits - audited 2026-09-18',
        'https://stats.uptimerobot.com/c7mpGQmbnC' => 'Vanity short link /uptime/ ported from the GoDaddy 301-redirects plugin; 76 lifetime hits - audited 2026-09-18',
        'https://get.deel.com/eotkl4au2m8w' => 'Vanity short link /deel/ ported from the GoDaddy 301-redirects plugin; 62 lifetime hits - audited 2026-09-18',
        'https://vimeo.com/1124026646/649fff7922?share=copy' => 'Vanity short link /interviewvideo/ ported from the GoDaddy 301-redirects plugin; 58 lifetime hits - audited 2026-09-18',
        'https://calendly.com/abbas-remoteleverage/30min' => 'Vanity short link /abbascalendar/ ported from the GoDaddy 301-redirects plugin; 55 lifetime hits - audited 2026-09-18',
        'https://forms.gle/8ZMPNRKwBveBa67M7' => 'Vanity short link /lead/ ported from the GoDaddy 301-redirects plugin; 46 lifetime hits - audited 2026-09-18',
        'https://form.jotform.com/260564755295164' => 'Vanity short link /ideas/ ported from the GoDaddy 301-redirects plugin; 36 lifetime hits - audited 2026-09-18',
        'https://form.jotform.com/252558126272155' => 'Vanity short link /fire/ ported from the GoDaddy 301-redirects plugin; 35 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/7543604107?pwd=cm1BQzRlOWNocklwOHNMTDBKODFRQT09' => 'Vanity short link /natasha/ ported from the GoDaddy 301-redirects plugin; 35 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleverage/client-va-onboarding-call' => 'Vanity short link /vaonboarding/ ported from the GoDaddy 301-redirects plugin; 34 lifetime hits - audited 2026-09-18',
        'https://calendly.com/d/cqnx-7z2-2rq/remote-leverage-onboarding-applicant-criteria' => 'Vanity short link /introcall/ ported from the GoDaddy 301-redirects plugin; 27 lifetime hits - audited 2026-09-18',
        'https://form.jotform.com/243466875000052' => 'Vanity short link /jobinvitation/ ported from the GoDaddy 301-redirects plugin; 24 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/6oEeWM0EUegh1qgdQW' => 'Vanity short link /join/ ported from the GoDaddy 301-redirects plugin; 19 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/3cIfZh1B8cQT29D0eWfrW0F' => 'Vanity short link /cordeposit/ ported from the GoDaddy 301-redirects plugin; 18 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleverage/job-interview-test' => 'Vanity short link /princessinterview/ ported from the GoDaddy 301-redirects plugin; 18 lifetime hits - audited 2026-09-18',
        'https://recruiting-helper.replit.app/' => 'Vanity short link /replit/ ported from the GoDaddy 301-redirects plugin; 7 lifetime hits - audited 2026-09-18',
        'https://recruitcrm.io/apply/17806984609920087768oJQ' => 'Vanity short link /laura/ ported from the GoDaddy 301-redirects plugin; 6 lifetime hits - audited 2026-09-18',
        'https://recruitcrm.io/apply/17811180148840133835icy' => 'Vanity short link /lina/ ported from the GoDaddy 301-redirects plugin; 6 lifetime hits - audited 2026-09-18',
        'https://form.jotform.com/243395973807471' => 'Vanity short link /splitpayment/ ported from the GoDaddy 301-redirects plugin; 6 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleverage/onboarding' => 'Vanity short link /onboardingmeeting/ ported from the GoDaddy 301-redirects plugin; 3 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleverage/coldcallingtraining' => 'Vanity short link /training/ ported from the GoDaddy 301-redirects plugin; 2 lifetime hits - audited 2026-09-18',
        'https://docs.google.com/document/d/1GoY3pWKRwPVmH7fyCWbNL-5PCeNDcxkX-eNp2mn91TA/edit?usp=sharing' => 'Vanity short link /vatraining/ ported from the GoDaddy 301-redirects plugin; 2 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/7sIg0QcnCa01d8Y5kB' => 'Vanity short link /12monthlyfee/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleverage/15-minute-meeting' => 'Vanity short link /15/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/fZe4i81IY6NP2uk00o' => 'Vanity short link /1500/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/7sIdSI3R6fklfh6cNd' => 'Vanity short link /2000/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/5kAbKAfzOdcdfh6aEU' => 'Vanity short link /6monthlyfee/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://calendly.com/cruzremoteleverage/virtual-assistant-hiring-consultation-clone' => 'Vanity short link /cruzcalendar/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://us02web.zoom.us/j/6990050267?pwd=RpNwxbjq22OrcMAe36gJJfxUNuI0Ha.1' => 'Vanity short link /estinterviewzoom/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/6oE2a01IY2xz7OE6oT' => 'Vanity short link /extendedguarantee/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/14kaGwcnC7RT4Cs8wL' => 'Vanity short link /followupmonthlyfee/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://buy.stripe.com/eVacOEafu5JL1qg28g' => 'Vanity short link /monthlyfee/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://calendly.com/remoteleveragesales/natasha-1-on-1-meeting' => 'Vanity short link /natashacalendar/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
        'https://forms.gle/jGL2PVu11C9189WN6' => 'Vanity short link /vaexam/ ported from the GoDaddy 301-redirects plugin; 0 lifetime hits - audited 2026-09-18',
    ];

    /*
     * Targets known to point at nothing, quarantined so the suite stays green while the
     * underlying content decision is open. Adding an entry should be deliberate — the
     * default is to fix the target, not to list it here.
     */
    $pendingTargets = [
        // Empty: every redirect target currently resolves. Add an entry only when a target is
        // knowingly dead while the content decision is open, and give the reason.
    ];

    test('every target is a registered route, a known page, a static file on disk, a declared external URL, or explicitly quarantined', function () use ($wordPressPageTargets, $contentTargets, $externalTargets, $pendingTargets) {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';
        $routeUris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => trim($route->uri(), '/'))
            ->all();

        // A fourth valid target class: a static asset served off disk, which is how the retired
        // rl-social-kit plugin's 29 downloads are preserved.
        //
        // This resolves against the TRACKED SOURCE, not the built file. `public/` is gitignored
        // and produced by `npm run build`, which CI runs in a separate job from Pest — checking
        // the built path passed locally and failed every CI run. Checking the source is also the
        // stronger assertion: it proves the asset is committed, where a built file could be
        // stale output from a source that has since been deleted.
        $themeRoot = dirname(__DIR__, 2);
        $isStaticFile = static function (string $to) use ($themeRoot): bool {
            $prefix = 'app/themes/remote-leverage/public/images/';

            if (! str_starts_with($to, $prefix)) {
                return false;
            }

            // themeImages() publishes resources/images/pages/** to public/images/**,
            // preserving directory structure and filenames exactly.
            $relative = substr($to, strlen($prefix));

            return is_file($themeRoot.'/resources/images/pages/'.$relative);
        };

        foreach ($config as $from => $to) {
            $resolves = in_array($to, $routeUris, true)
                || isset($wordPressPageTargets[$to])
                || isset($contentTargets[$to])
                || isset($externalTargets[$to])
                || $isStaticFile($to)
                || isset($pendingTargets[$to]);

            expect($resolves)->toBeTrue(
                "'{$from}' => '{$to}': target is not a registered route, not a declared "
                .'WordPress page, not declared published content, not a static file on disk, '
                .'not a declared external URL, and '
                .'not quarantined. Point it at something real, or add it to $pendingTargets '
                .'with a reason.'
            );
        }
    });

    test('every declared external target is a real absolute http(s) URL', function () use ($externalTargets) {
        foreach (array_keys($externalTargets) as $target) {
            expect(LegacyRedirectMiddleware::isExternalTarget($target))->toBeTrue(
                "'{$target}' is listed as an external target but is not an absolute http(s) URL."
            );
        }
    });

    test('every external target in the map is declared, and nothing else leaves the site', function () use ($externalTargets) {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        $inMap = array_values(array_filter(
            $config,
            fn (string $to): bool => LegacyRedirectMiddleware::isExternalTarget($to)
        ));

        foreach ($inMap as $target) {
            expect(array_key_exists($target, $externalTargets))->toBeTrue(
                "'{$target}' redirects off-site but is not declared in \$externalTargets."
            );
        }

        // The allowlist wp_safe_redirect() is given comes from the map, never from a request.
        expect(LegacyRedirectMiddleware::externalHosts($config))
            ->toBe(array_values(array_unique(array_map(
                fn (string $t): string => (string) parse_url($t, PHP_URL_HOST),
                $inMap
            ))));
    });

    test('no redirect key shadows a page being built in v2', function () {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        // Removed 2026-09-15 when each became a real v2 page. A key equal to a live page slug
        // would 301 that page away.
        foreach ([
            'contractoragreement', 'services', 'store', 'hire-va',
            // Freed 2026-09-15 when scope reopened for them: three internal ops tools whose
            // forms POST to live Zapier/n8n webhooks, the only SDR-vertical landing page, and
            // the 28-video client onboarding guide.
            'hmchecklists', 'recruiterchecklists', 'saleschecklists',
            'sales-talents', 'onboardingguide',
            // The 6-video VA-facing guide /services/ links to. Note it is NOT the same page as
            // 'onboardingguide' above despite the near-identical slug and title: production
            // serves 3435px / 6 Vimeo embeds here against 10946px / 28 there.
            'vaonboardingguide',
            // Freed 2026-09-16: the "Meeting With Hiring Manager" booking page. It was 301ing
            // to 'book-consultation', whose wizard resolves its Calendly event type from the
            // lead's revenue tier and so booked a different meeting than this page describes.
            'service-hiring',
        ] as $liveSlug) {
            expect(array_key_exists($liveSlug, $config))->toBeFalse(
                "'{$liveSlug}' is a live v2 page slug; a redirect key of the same name would 301 the page away."
            );
        }
    });

    test('quarantined targets are still dead, so fixed ones get removed from the list', function () use ($wordPressPageTargets, $pendingTargets) {
        $routeUris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => trim($route->uri(), '/'))
            ->all();

        // Asserted explicitly so the test is not silently vacuous when the list is empty,
        // which is the desired steady state.
        expect($pendingTargets)->toBeArray();

        foreach (array_keys($pendingTargets) as $target) {
            $nowResolves = in_array($target, $routeUris, true) || isset($wordPressPageTargets[$target]);

            expect($nowResolves)->toBeFalse(
                "'{$target}' now resolves — remove it from \$pendingTargets."
            );
        }
    });

    test('the partner-dashboard redirect points at the real portal route', function () {
        $config = require dirname(__DIR__, 2).'/config/redirects.php';

        expect($config['partner-dashboard'])->toBe('referrer-portal')
            ->and(Route::has('referrer.portal'))->toBeTrue();
    });
});

describe('every route() name referenced in code is registered (known-issues.md bug #1)', function () {
    beforeEach(function () {
        $baseDir = dirname(__DIR__, 2);
        if (! Route::has('api.health')) {
            Route::prefix('api')->group($baseDir.'/routes/api.php');
            Route::middleware([])->group($baseDir.'/routes/web.php');
            Route::getRoutes()->refreshNameLookups();
        }
    });

    /*
     * `redirect()->route('partner.portal')` shipped for months and threw on every click,
     * because nothing checked that the name existed. This scans routes and Blade views for
     * route() / redirect()->route() names and asserts each one is registered.
     */
    test('no code references an unregistered route name', function () {
        $baseDir = dirname(__DIR__, 2);
        $files = array_merge(
            glob($baseDir.'/routes/*.php') ?: [],
            glob($baseDir.'/resources/views/**/*.blade.php', GLOB_BRACE) ?: [],
            glob($baseDir.'/resources/views/*.blade.php') ?: [],
        );

        $referenced = [];
        foreach ($files as $file) {
            preg_match_all("/(?:->)?route\(\s*'([a-zA-Z0-9_.-]+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $name) {
                $referenced[$name][] = basename($file);
            }
        }

        expect($referenced)->not->toBeEmpty('scan found no route() calls — the pattern is wrong');

        foreach ($referenced as $name => $files) {
            expect(Route::has($name))->toBeTrue(
                "route('{$name}') is referenced in ".implode(', ', array_unique($files))
                .' but no route is registered under that name.'
            );
        }
    });

    test('the partner directory CTAs resolve to the referrer portal and registration', function () {
        expect(Route::has('referrer.portal'))->toBeTrue()
            ->and(Route::has('referrer.register'))->toBeTrue();

        $blade = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/archive-rl_partner.blade.php');

        expect($blade)->toContain("route('referrer.register')")
            ->and($blade)->toContain("route('referrer.portal')")
            ->and($blade)->not->toContain('partner-dashboard');
    });
});
