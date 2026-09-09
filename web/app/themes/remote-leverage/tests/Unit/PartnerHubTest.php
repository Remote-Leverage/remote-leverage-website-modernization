<?php

declare(strict_types=1);

use App\Domains\PartnerHub\Services\PartnerHubGlobalData;
use App\Domains\PartnerHub\Services\PartnerHubTabResolver;
use App\Fields\PartnerHubFields;
use App\Infrastructure\WordPress\Admin\PartnerHubAdmin;
use Log1x\AcfComposer\Field;

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

describe('PartnerHubAdmin list columns', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_post_meta'] = [];
    });

    test('addAdminColumns inserts Referral Code and Submission Form after the title column', function () {
        $admin = new PartnerHubAdmin;

        $columns = $admin->addAdminColumns(['cb' => '', 'title' => 'Title', 'date' => 'Date']);

        expect(array_keys($columns))->toBe(['cb', 'title', 'partner_code', 'referral_form', 'date']);
    });

    test('renderAdminColumns prints the referral code and a link when the form URL is set', function () {
        $admin = new PartnerHubAdmin;
        $postId = 701;
        update_post_meta($postId, '_rl_partner_code', 'RL-OYSTER');
        update_post_meta($postId, '_rl_referral_form_url', 'https://docs.google.com/forms/d/abc/viewform');

        ob_start();
        $admin->renderAdminColumns('partner_code', $postId);
        $partnerCodeOutput = ob_get_clean();

        ob_start();
        $admin->renderAdminColumns('referral_form', $postId);
        $referralFormOutput = ob_get_clean();

        expect($partnerCodeOutput)->toContain('RL-OYSTER')
            ->and($referralFormOutput)->toContain('https://docs.google.com/forms/d/abc/viewform');
    });

    test('renderAdminColumns shows a placeholder when no submission form is set', function () {
        $admin = new PartnerHubAdmin;
        $postId = 702;

        ob_start();
        $admin->renderAdminColumns('referral_form', $postId);
        $output = ob_get_clean();

        expect($output)->toContain('None set');
    });
});

describe('PartnerHubFields ACF field group (WR-121)', function () {
    test('PartnerHubFields extends the ACF Composer Field base and defines a fields() method', function () {
        expect(class_exists(PartnerHubFields::class))->toBeTrue();

        $reflection = new ReflectionClass(PartnerHubFields::class);

        expect($reflection->isSubclassOf(Field::class))->toBeTrue()
            ->and($reflection->hasMethod('fields'))->toBeTrue();
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
