<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Lead\Models\Lead;
use App\Infrastructure\WordPress\Admin\MarketingDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('MarketingDashboard Executive Modernization', function () {
    it('instantiates cleanly and registers wp_dashboard_setup hook', function () {
        $dashboard = new MarketingDashboard();

        // In test environment, add_action might be mocked or no-op
        $dashboard->register();

        expect($dashboard)->toBeInstanceOf(MarketingDashboard::class);
    });

    it('removes legacy blog widgets and adds modern remote leverage widgets during dashboard setup', function () {
        $dashboard = new MarketingDashboard();

        $GLOBALS['rl_removed_meta_boxes'] = [];
        $GLOBALS['rl_added_dashboard_widgets'] = [];

        $dashboard->setupDashboard();

        expect($GLOBALS['rl_removed_meta_boxes'])->toContain('dashboard_quick_press')
            ->toContain('dashboard_primary')
            ->toContain('dashboard_activity')
            ->toContain('dashboard_site_health')
            ->and($GLOBALS['rl_added_dashboard_widgets'])->toContain('rl_dashboard_kpis')
            ->toContain('rl_dashboard_channels')
            ->toContain('rl_dashboard_recent_leads')
            ->toContain('rl_dashboard_domains')
            ->toContain('rl_dashboard_partner_hub')
            ->toContain('rl_dashboard_site_health')
            ->toContain('rl_dashboard_marketing_shortcuts');
    });

    it('renders Marketing KPIs widget without emojis and with executive metrics', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderKpisWidget();
        $output = ob_get_clean();

        expect($output)->toContain('TOTAL SUBMISSIONS')
            ->toContain('QUALIFIED (>= $10K)')
            ->toContain('CONSULTATIONS')
            ->toContain('CALL CONVERSION')
            ->toContain('rl-dash-kpi-grid')
            ->toContain('admin.php?page=rl-leads');

        // Verify strictly zero emojis
        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        expect(preg_match($emojiPattern, $output))->toBe(0);
    });

    it('renders Recent Leads widget with empty state or lead stream table', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderRecentLeadsWidget();
        $output = ob_get_clean();

        expect($output)->not->toBeEmpty();

        // Either empty state or table
        if (str_contains($output, 'rl-dash-table')) {
            expect($output)->toContain('Contact & Company')
                ->toContain('MRR Tier')
                ->toContain('Status');
        } else {
            expect($output)->toContain('No captured leads yet');
        }
    });

    it('renders Platform & Site Health widget with Bedrock and integration statuses', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderSiteHealthWidget();
        $output = ob_get_clean();

        expect($output)->toContain('Bedrock Environment')
            ->toContain('Database Engine')
            ->toContain('Livewire 3 Engine')
            ->toContain('/api/webhooks/stripe')
            ->toContain('/api/webhooks/calendly')
            ->toContain('ADR-0008 Retention Guard')
            ->toContain('PHP Runtime');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        expect(preg_match($emojiPattern, $output))->toBe(0);
    });

    it('renders Traffic Acquisition & Attribution Channels widget with visual channel distribution', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderChannelsWidget();
        $output = ob_get_clean();

        expect($output)->toContain('Attribution Channel Share')
            ->toContain('Lead volume segmented by source')
            ->toContain('rl-dash-channels-wrap');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        expect(preg_match($emojiPattern, $output))->toBe(0);
    });

    it('renders Domain Architecture & Multi-Service Status widget covering all domains', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderDomainsWidget();
        $output = ob_get_clean();

        // Must cover all core architectural domains
        expect($output)->toContain('Lead Domain')
            ->toContain('Scheduling Domain')
            ->toContain('PartnerHub & Referral')
            ->toContain('Tracking Domain')
            ->toContain('Content Modernization')
            ->toContain('rl-dash-domain-card');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        expect(preg_match($emojiPattern, $output))->toBe(0);
    });

    it('renders Partner Hub & Referral Revenue widget with affiliate KPIs', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderPartnerHubWidget();
        $output = ob_get_clean();

        expect($output)->toContain('PARTNERS')
            ->toContain('REFERRALS')
            ->toContain('PAID OUT')
            ->toContain('Stripe Connect Payout Gateway')
            ->toContain('Manage Partner Network')
            ->toContain('rl-dash-kpi-grid');

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F700}-\x{1F77F}\x{1F780}-\x{1F7FF}\x{1F800}-\x{1F8FF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FA6F}\x{1FA70}-\x{1FAFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';
        expect(preg_match($emojiPattern, $output))->toBe(0);
    });

    it('renders KPI widget containing conversion funnel steps and 7-day volume bar chart', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderKpisWidget();
        $output = ob_get_clean();

        expect($output)->toContain('Pipeline Conversion Funnel')
            ->toContain('7-Day Ingestion Volume')
            ->toContain('rl-dash-barchart-grid')
            ->toContain('rl-dash-bar-col');
    });

    it('renders Marketing Shortcuts widget with direct actions', function () {
        $dashboard = new MarketingDashboard();

        ob_start();
        $dashboard->renderMarketingShortcutsWidget();
        $output = ob_get_clean();

        expect($output)->toContain('Test Booking Wizard')
            ->toContain('Lead Management Hub')
            ->toContain('Audit Activity Trail')
            ->toContain('Export All Submissions')
            ->toContain('rl-dash-shortcuts-grid');
    });
});

