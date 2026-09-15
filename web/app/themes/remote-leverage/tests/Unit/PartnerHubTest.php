<?php

declare(strict_types=1);

use App\Domains\PartnerHub\Actions\FetchPartnersAction;
use App\Domains\PartnerHub\Actions\QueryPartnersAction;
use App\Domains\PartnerHub\Models\PartnerProfile;
use App\Domains\PartnerHub\Services\PartnerHubContentResolver;
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

describe('PartnerHubContentResolver per-partner overrides', function () {
    test('resolveRows falls back to the global default for an untouched or emptied repeater', function () {
        $default = [['name' => 'Direct-Hire Recruitment']];

        expect(PartnerHubContentResolver::resolveRows(false, $default))->toBe($default)
            ->and(PartnerHubContentResolver::resolveRows([], $default))->toBe($default)
            ->and(PartnerHubContentResolver::resolveRows(null, $default))->toBe($default);
    });

    test('resolveRows replaces the default wholesale rather than merging into it', function () {
        $default = [['name' => 'A'], ['name' => 'B'], ['name' => 'C']];
        $override = [['name' => 'Only This One']];

        expect(PartnerHubContentResolver::resolveRows($override, $default))->toBe($override);
    });

    test('resolveRows reindexes a sparse ACF repeater so @foreach order is stable', function () {
        $override = [2 => ['name' => 'Second'], 0 => ['name' => 'First']];

        expect(PartnerHubContentResolver::resolveRows($override, []))
            ->toBe([['name' => 'Second'], ['name' => 'First']]);
    });

    test('resolveText falls back for blank and whitespace-only overrides', function () {
        expect(PartnerHubContentResolver::resolveText('', 'default'))->toBe('default')
            ->and(PartnerHubContentResolver::resolveText('   ', 'default'))->toBe('default')
            ->and(PartnerHubContentResolver::resolveText(false, 'default'))->toBe('default')
            ->and(PartnerHubContentResolver::resolveText('  custom  ', 'default'))->toBe('custom');
    });

    test('resolveList splits a textarea override on newlines and drops blank lines', function () {
        $override = "Law Firms\n\n  Accounting Firms  \r\nHealthcare\n";

        expect(PartnerHubContentResolver::resolveList($override, ['Default']))
            ->toBe(['Law Firms', 'Accounting Firms', 'Healthcare']);
    });

    test('resolveList still accepts the array shape the legacy plugin wrote', function () {
        expect(PartnerHubContentResolver::resolveList(['Law Firms', 'SaaS'], ['Default']))
            ->toBe(['Law Firms', 'SaaS']);
    });

    test('resolveList falls back when the override holds nothing usable', function () {
        expect(PartnerHubContentResolver::resolveList('', ['Default']))->toBe(['Default'])
            ->and(PartnerHubContentResolver::resolveList("\n \n", ['Default']))->toBe(['Default'])
            ->and(PartnerHubContentResolver::resolveList(false, ['Default']))->toBe(['Default']);
    });
});

describe('PartnerHubGlobalData callouts ported from the legacy plugin', function () {
    test('getValueProposition and getTargetFit expose a title and body', function () {
        expect(PartnerHubGlobalData::getValueProposition())->toHaveKeys(['title', 'desc'])
            ->and(PartnerHubGlobalData::getTargetFit())->toHaveKeys(['title', 'desc']);
    });

    test('getServicesDesc returns the default services lead paragraph', function () {
        expect(PartnerHubGlobalData::getServicesDesc())->toContain('direct-hire recruitment');
    });
});

describe('PartnerProfile::fromPost (CPT-backed directory, replaced Notion 2026-09-15)', function () {
    beforeEach(function () {
        $GLOBALS['_wp_mock_post_meta'] = [];
    });

    test('maps the CPT meta a directory card renders', function () {
        $postId = 810;
        update_post_meta($postId, '_rl_partner_name', 'Oyster');
        update_post_meta($postId, '_rl_partner_website', 'https://oysterhr.com');
        update_post_meta($postId, '_rl_partner_code', 'RL-OYSTER');
        update_post_meta($postId, '_rl_directory_category', 'Software & Tech');
        update_post_meta($postId, '_rl_directory_description', 'Global employment platform.');
        update_post_meta($postId, '_rl_directory_perk', 'Preferred onboarding for RL clients');
        update_post_meta($postId, '_rl_directory_featured', '1');
        update_post_meta($postId, '_rl_partner_logo_url', 'https://cdn.example.com/oyster.png');

        $card = PartnerProfile::fromPost(new WP_Post([
            'ID' => $postId,
            'post_title' => 'Remote Leverage × Oyster',
            'post_name' => 'oyster',
        ]))->toArray();

        expect($card['name'])->toBe('Oyster')
            ->and($card['slug'])->toBe('oyster')
            ->and($card['category'])->toBe('Software & Tech')
            ->and($card['description'])->toBe('Global employment platform.')
            ->and($card['perk_description'])->toBe('Preferred onboarding for RL clients')
            ->and($card['featured'])->toBeTrue()
            ->and($card['logo_url'])->toBe('https://cdn.example.com/oyster.png')
            ->and($card['website_url'])->toBe('https://oysterhr.com')
            ->and($card['tags'])->toBe(['RL-OYSTER']);
    });

    test('falls back to the post title when the partner name meta is unset', function () {
        $card = PartnerProfile::fromPost(new WP_Post([
            'ID' => 811,
            'post_title' => 'Remote Leverage × Lexgo',
            'post_name' => 'lexgo',
        ]))->toArray();

        expect($card['name'])->toBe('Remote Leverage × Lexgo')
            ->and($card['category'])->toBe('Staffing & HR')
            ->and($card['featured'])->toBeFalse()
            ->and($card['logo_url'])->toBeNull()
            ->and($card['perk_description'])->toBeNull();
    });

    test('emits the slug the card links to, rather than leaving it to be guessed from the name', function () {
        $card = PartnerProfile::fromPost(new WP_Post([
            'ID' => 812,
            'post_title' => 'Remote Leverage × Lano',
            'post_name' => 'lano',
        ]))->toArray();

        expect($card)->toHaveKey('slug')
            ->and($card['slug'])->toBe('lano');
    });

    test('every stored category is one the directory grid has a filter pill for', function () {
        $postId = 813;
        update_post_meta($postId, '_rl_directory_category', 'Finance & Legal');

        $card = PartnerProfile::fromPost(new WP_Post(['ID' => $postId, 'post_name' => 'x']))->toArray();

        expect(PartnerHubGlobalData::getDirectoryCategories())->toContain($card['category']);
    });
});

describe('QueryPartnersAction filtering', function () {
    $fetcherReturning = function (array $partners) {
        return new class($partners) extends FetchPartnersAction
        {
            public function __construct(private array $partners) {}

            public function execute(): array
            {
                return $this->partners;
            }
        };
    };

    $partners = [
        ['name' => 'Oyster', 'category' => 'Software & Tech', 'description' => 'Global employment platform', 'perk_description' => null, 'featured' => true],
        ['name' => 'Lexgo', 'category' => 'Finance & Legal', 'description' => 'Immigration counsel', 'perk_description' => 'Discounted visa review', 'featured' => false],
        ['name' => 'Lano', 'category' => 'Finance & Legal', 'description' => 'Contractor payments', 'perk_description' => null, 'featured' => false],
    ];

    test('returns every partner when nothing is filtered', function () use ($fetcherReturning, $partners) {
        $action = new QueryPartnersAction($fetcherReturning($partners));

        expect($action->execute())->toHaveCount(3);
    });

    test('filters by category, ignoring the grid\'s "All" pseudo-category', function () use ($fetcherReturning, $partners) {
        $action = new QueryPartnersAction($fetcherReturning($partners));

        expect($action->execute(category: 'Finance & Legal'))->toHaveCount(2)
            ->and($action->execute(category: 'All'))->toHaveCount(3);
    });

    test('featuredOnly narrows to flagged partners', function () use ($fetcherReturning, $partners) {
        $action = new QueryPartnersAction($fetcherReturning($partners));

        $result = $action->execute(featuredOnly: true);

        expect($result)->toHaveCount(1)
            ->and($result[0]['name'])->toBe('Oyster');
    });

    test('search matches name, description, and perk case-insensitively', function () use ($fetcherReturning, $partners) {
        $action = new QueryPartnersAction($fetcherReturning($partners));

        expect($action->execute(search: 'LANO'))->toHaveCount(1)
            ->and($action->execute(search: 'payments'))->toHaveCount(1)
            ->and($action->execute(search: 'visa'))->toHaveCount(1)
            ->and($action->execute(search: 'nothing here'))->toHaveCount(0);
    });

    test('a reindexed list is returned so the grid @foreach stays contiguous', function () use ($fetcherReturning, $partners) {
        $action = new QueryPartnersAction($fetcherReturning($partners));

        expect(array_keys($action->execute(category: 'Finance & Legal')))->toBe([0, 1]);
    });
});
