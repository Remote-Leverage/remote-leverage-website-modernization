<?php

declare(strict_types=1);

use App\Domains\PartnerHub\Services\PartnerHubGlobalData;
use App\Domains\PartnerHub\Services\PartnerHubTabResolver;
use App\Infrastructure\WordPress\Admin\PartnerHubAdmin;

describe('PartnerHubTabResolver (WR-119)', function () {
    test('comarketing is enabled by default and only disabled by an explicit "0"', function () {
        expect(PartnerHubTabResolver::isComarketingEnabled(''))->toBeTrue()
            ->and(PartnerHubTabResolver::isComarketingEnabled('1'))->toBeTrue()
            ->and(PartnerHubTabResolver::isComarketingEnabled('0'))->toBeFalse();
    });

    test('resolveTabs includes comarketing only when enabled', function () {
        $withComarketing = PartnerHubTabResolver::resolveTabs(true);
        $withoutComarketing = PartnerHubTabResolver::resolveTabs(false);

        expect($withComarketing)->toHaveKey('comarketing');
        expect($withoutComarketing)->not->toHaveKey('comarketing');
    });

    test('resolveCurrentTab falls back to overview for an unknown or disabled tab', function () {
        $tabsWithoutComarketing = PartnerHubTabResolver::resolveTabs(false);

        expect(PartnerHubTabResolver::resolveCurrentTab('services', $tabsWithoutComarketing))->toBe('services')
            ->and(PartnerHubTabResolver::resolveCurrentTab('comarketing', $tabsWithoutComarketing))->toBe('overview')
            ->and(PartnerHubTabResolver::resolveCurrentTab('not-a-real-tab', $tabsWithoutComarketing))->toBe('overview');
    });
});

describe('PartnerHubAdmin metaboxes (WR-116/117/118/119)', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_post_meta'] = [];
        $_POST = [];
    });

    test('saveMetaBoxes persists branding, terms, and bidirectional referral fields', function () {
        $admin = new PartnerHubAdmin;
        $postId = 601;

        $_POST = [
            'rl_partner_meta_nonce' => 'valid',
            '_rl_partner_name' => 'Oyster',
            '_rl_partner_code' => 'RL-OYSTER',
            '_rl_partner_logo_url' => 'https://example.com/logo.png',
            '_rl_partnership_type' => 'Mutual Referral Partner',
            '_rl_territory' => 'Worldwide',
            '_rl_referral_form_url' => 'https://docs.google.com/forms/d/abc/viewform',
            '_rl_intro_email' => 'partnerships@remoteleverage.com',
            '_rl_partner_to_rl_fee' => '10% of net placement fee.',
            '_rl_partner_referral_label' => 'Refer a Client to Oyster',
            '_rl_partner_referral_email' => 'pending to define',
            '_rl_rl_to_partner_fee' => '10% of eligible subscription fees.',
        ];

        $admin->saveMetaBoxes($postId);

        expect(get_post_meta($postId, '_rl_partner_name', true))->toBe('Oyster')
            ->and(get_post_meta($postId, '_rl_partner_code', true))->toBe('RL-OYSTER')
            ->and(get_post_meta($postId, '_rl_partnership_type', true))->toBe('Mutual Referral Partner')
            ->and(get_post_meta($postId, '_rl_referral_form_url', true))->toBe('https://docs.google.com/forms/d/abc/viewform')
            ->and(get_post_meta($postId, '_rl_partner_referral_label', true))->toBe('Refer a Client to Oyster')
            ->and(get_post_meta($postId, '_rl_partner_referral_email', true))->toBe('pending to define')
            ->and(get_post_meta($postId, '_rl_rl_to_partner_fee', true))->toBe('10% of eligible subscription fees.');
    });

    test('saveMetaBoxes defaults co-marketing to disabled when the checkbox is unchecked', function () {
        $admin = new PartnerHubAdmin;
        $postId = 602;

        $_POST = ['rl_partner_meta_nonce' => 'valid'];
        $admin->saveMetaBoxes($postId);

        expect(get_post_meta($postId, '_rl_enable_comarketing', true))->toBe('0');

        $_POST = ['rl_partner_meta_nonce' => 'valid', '_rl_enable_comarketing' => '1'];
        $admin->saveMetaBoxes($postId);

        expect(get_post_meta($postId, '_rl_enable_comarketing', true))->toBe('1');
    });

    test('saveMetaBoxes sanitizes and persists per-section PDF attachments, dropping empty URLs', function () {
        $admin = new PartnerHubAdmin;
        $postId = 603;

        $_POST = [
            'rl_partner_meta_nonce' => 'valid',
            '_rl_section_attachments' => [
                'referral-program' => [
                    ['title' => 'Fee Sheet', 'url' => 'https://example.com/fee-sheet.pdf', 'size' => '1.2 MB'],
                    ['title' => 'Empty Row', 'url' => '', 'size' => ''],
                ],
            ],
        ];

        $admin->saveMetaBoxes($postId);

        $attachments = get_post_meta($postId, '_rl_section_attachments', true);

        expect($attachments['referral-program'])->toHaveCount(1)
            ->and($attachments['referral-program'][0]['title'])->toBe('Fee Sheet')
            ->and($attachments['referral-program'][0]['url'])->toBe('https://example.com/fee-sheet.pdf');
    });
});

describe('PartnerHub Global Default Content Library (WR-120)', function () {
    test('getServices returns exactly 10 services with expected keys', function () {
        $services = PartnerHubGlobalData::getServices();

        expect($services)->toHaveCount(10);

        foreach ($services as $service) {
            expect($service)->toHaveKeys(['key', 'name', 'best_for', 'desc']);
        }
    });

    test('getCaseStudies returns exactly 6 case studies with challenge/solution/outcome', function () {
        $caseStudies = PartnerHubGlobalData::getCaseStudies();

        expect($caseStudies)->toHaveCount(6);

        foreach ($caseStudies as $caseStudy) {
            expect($caseStudy)->toHaveKeys(['industry', 'client', 'challenge', 'solution', 'outcome'])
                ->and($caseStudy['challenge'])->toBeArray()
                ->and($caseStudy['solution'])->toBeArray()
                ->and($caseStudy['outcome'])->toBeArray();
        }
    });

    test('getFaqs returns exactly 14 FAQs', function () {
        $faqs = PartnerHubGlobalData::getFaqs();

        expect($faqs)->toHaveCount(14);

        foreach ($faqs as $faq) {
            expect($faq)->toHaveKeys(['q', 'a']);
        }
    });

    test('getComparisonMatrix returns 8 rows comparing RL, in-house, and agency', function () {
        $matrix = PartnerHubGlobalData::getComparisonMatrix();

        expect($matrix)->toHaveCount(8);

        foreach ($matrix as $row) {
            expect($row)->toHaveKeys(['feature', 'rl', 'inhouse', 'agency']);
        }
    });

    test('getDefaultLifecycleStages returns the 6-stage referral pipeline in order', function () {
        $stages = PartnerHubGlobalData::getDefaultLifecycleStages();

        expect($stages)->toHaveCount(6)
            ->and($stages[0]['key'])->toBe('submitted')
            ->and($stages[5]['key'])->toBe('commission_paid');
    });

    test('getDefaultReferralRules returns 6 default eligibility rules', function () {
        expect(PartnerHubGlobalData::getDefaultReferralRules())->toHaveCount(6);
    });

    test('getDefaultComarketing returns lead text, approval note, and 4 opportunities', function () {
        $comarketing = PartnerHubGlobalData::getDefaultComarketing();

        expect($comarketing)->toHaveKeys(['lead_text', 'approval_note', 'opportunities'])
            ->and($comarketing['opportunities'])->toHaveCount(4);
    });

    test('getCoreValues and getWhyChooseRl return the expected counts', function () {
        expect(PartnerHubGlobalData::getCoreValues())->toHaveCount(5);
        expect(PartnerHubGlobalData::getWhyChooseRl())->toHaveCount(7);
    });

    test('getTargetIndustries and getGeographicMarkets return the expected counts', function () {
        expect(PartnerHubGlobalData::getTargetIndustries())->toHaveCount(16);
        expect(PartnerHubGlobalData::getGeographicMarkets())->toHaveCount(5);
    });
});
