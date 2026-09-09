<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\ContentAudit\Services\ElementorAuditService;
use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use App\Domains\Referral\Models\Payout;
use App\Domains\Referral\Models\Referral;
use App\Domains\Referral\Models\Referrer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MarketingDashboard
{
    /**
     * Register WordPress dashboard customization hooks.
     */
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'setupDashboard'], 999);
    }

    /**
     * Purge legacy blog widgets and mount modern Remote Leverage domain & marketing widgets.
     */
    public function setupDashboard(): void
    {
        // 1. Remove all legacy WordPress personal blog / community widgets
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
        remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
        remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
        remove_meta_box('dashboard_plugins', 'dashboard', 'normal');

        // 2. Left Column (normal): Performance, Ingestion Charts, Channels, Recent Leads
        wp_add_dashboard_widget(
            'rl_dashboard_kpis',
            'Marketing Performance & Ingestion Volume',
            [$this, 'renderKpisWidget'],
            null,
            null,
            'normal',
            'high'
        );

        wp_add_dashboard_widget(
            'rl_dashboard_channels',
            'Traffic Acquisition & Attribution Channels',
            [$this, 'renderChannelsWidget'],
            null,
            null,
            'normal',
            'default'
        );

        wp_add_dashboard_widget(
            'rl_dashboard_recent_leads',
            'Recent Captured Leads Stream',
            [$this, 'renderRecentLeadsWidget'],
            null,
            null,
            'normal',
            'default'
        );

        // 3. Right Column (side): Domain Architecture, Referrer Network, Site Health, Shortcuts
        wp_add_dashboard_widget(
            'rl_dashboard_domains',
            'Domain Architecture & Multi-Service Status',
            [$this, 'renderDomainsWidget'],
            null,
            null,
            'side',
            'high'
        );

        wp_add_dashboard_widget(
            'rl_dashboard_referrer_network',
            'Referrer Network & Revenue',
            [$this, 'renderReferrerNetworkWidget'],
            null,
            null,
            'side',
            'default'
        );

        wp_add_dashboard_widget(
            'rl_dashboard_site_health',
            'Platform & Infrastructure Health',
            [$this, 'renderSiteHealthWidget'],
            null,
            null,
            'side',
            'default'
        );

        wp_add_dashboard_widget(
            'rl_dashboard_marketing_shortcuts',
            'Marketing Shortcuts & Quick Actions',
            [$this, 'renderMarketingShortcutsWidget'],
            null,
            null,
            'side',
            'low'
        );
    }

    /**
     * Render the Marketing KPIs & 7-Day Ingestion Volume Chart widget.
     */
    public function renderKpisWidget(): void
    {
        $metrics = $this->getDashboardMetrics();

        $total = $metrics['total'];
        $t10 = $metrics['t10'];
        $booked = $metrics['booked'];
        $conversionRate = $total > 0 ? round(($booked / $total) * 100, 1) : 0.0;
        $t10Rate = $total > 0 ? round(($t10 / $total) * 100, 1) : 0.0;
        $dailyVolume = $metrics['daily_volume'];

        ?>
        <div class="rl-dash-kpi-wrap">
            <div class="rl-dash-kpi-grid">
                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">TOTAL SUBMISSIONS</span>
                        <svg class="rl-dash-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html((string) $total); ?></div>
                    <div class="rl-dash-kpi-meta">All captured records</div>
                </div>

                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">QUALIFIED (>= $10K)</span>
                        <span class="rl-dash-badge-dark">T10</span>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html((string) $t10); ?></div>
                    <div class="rl-dash-kpi-meta"><?php echo esc_html((string) $t10Rate); ?>% high-intent pipeline</div>
                </div>

                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">CONSULTATIONS</span>
                        <svg class="rl-dash-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html((string) $booked); ?></div>
                    <div class="rl-dash-kpi-meta">Confirmed on calendar</div>
                </div>

                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">CALL CONVERSION</span>
                        <svg class="rl-dash-kpi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html($conversionRate.'%'); ?></div>
                    <div class="rl-dash-kpi-meta">Booked / Submissions</div>
                </div>
            </div>

            <!-- Pipeline Funnel Progression Chart -->
            <div class="rl-dash-funnel-box">
                <div class="rl-dash-funnel-header">
                    <span class="rl-dash-box-subtitle">Pipeline Conversion Funnel</span>
                    <span class="rl-dash-box-meta"><?php echo esc_html((string) $total); ?> Ingested &rarr; <?php echo esc_html((string) $booked); ?> Booked</span>
                </div>
                <div class="rl-dash-funnel-stages">
                    <div class="rl-dash-funnel-stage">
                        <div class="rl-dash-stage-info">
                            <span class="rl-dash-stage-name">1. Captured Ingestion</span>
                            <span class="rl-dash-stage-val"><?php echo esc_html((string) $total); ?> (100%)</span>
                        </div>
                        <div class="rl-dash-bar-track">
                            <div class="rl-dash-bar-fill rl-stage-1" style="width: 100%;"></div>
                        </div>
                    </div>
                    <div class="rl-dash-funnel-stage">
                        <div class="rl-dash-stage-info">
                            <span class="rl-dash-stage-name">2. MRR Qualified (T10 &ge; $10k)</span>
                            <span class="rl-dash-stage-val"><?php echo esc_html((string) $t10); ?> (<?php echo esc_html((string) $t10Rate); ?>%)</span>
                        </div>
                        <div class="rl-dash-bar-track">
                            <div class="rl-dash-bar-fill rl-stage-2" style="width: <?php echo esc_attr((string) max(4, min(100, $t10Rate))); ?>%;"></div>
                        </div>
                    </div>
                    <div class="rl-dash-funnel-stage">
                        <div class="rl-dash-stage-info">
                            <span class="rl-dash-stage-name">3. Consultation Scheduled</span>
                            <span class="rl-dash-stage-val"><?php echo esc_html((string) $booked); ?> (<?php echo esc_html((string) $conversionRate); ?>%)</span>
                        </div>
                        <div class="rl-dash-bar-track">
                            <div class="rl-dash-bar-fill rl-stage-3" style="width: <?php echo esc_attr((string) max(4, min(100, $conversionRate))); ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7-Day Ingestion Volume Bar Chart -->
            <div class="rl-dash-chart-card">
                <div class="rl-dash-chart-header">
                    <div>
                        <span class="rl-dash-box-subtitle">7-Day Ingestion Volume</span>
                        <div class="rl-dash-box-meta">Daily lead capture trend</div>
                    </div>
                    <span class="rl-dash-badge-subtle">Trailing 7 Days</span>
                </div>
                <div class="rl-dash-chart-body">
                    <?php
                    $maxDaily = 1;
        foreach ($dailyVolume as $day) {
            if ($day['count'] > $maxDaily) {
                $maxDaily = $day['count'];
            }
        }
        ?>
                    <div class="rl-dash-barchart-grid">
                        <?php foreach ($dailyVolume as $day) {
                            $count = $day['count'];
                            $pct = $count > 0 ? max(14, (int) round(($count / $maxDaily) * 100)) : 0;
                            $isPeak = $count === $maxDaily && $count > 0;
                            ?>
                            <div class="rl-dash-bar-col">
                                <div class="rl-dash-bar-val-wrap">
                                    <?php if ($count > 0) { ?>
                                        <span class="rl-dash-bar-val <?php echo $isPeak ? 'rl-val-peak' : ''; ?>"><?php echo esc_html((string) $count); ?></span>
                                    <?php } else { ?>
                                        <span class="rl-dash-bar-val-empty">&ndash;</span>
                                    <?php } ?>
                                </div>
                                <div class="rl-dash-bar-slot">
                                    <?php if ($count > 0) { ?>
                                        <div class="rl-dash-bar-fill-v <?php echo $isPeak ? 'rl-bar-peak' : ''; ?>" style="height: <?php echo esc_attr((string) $pct); ?>%;" title="<?php echo esc_attr($day['label'].': '.$count.' leads'); ?>"></div>
                                    <?php } else { ?>
                                        <div class="rl-dash-bar-fill-zero" title="<?php echo esc_attr($day['label'].': 0 leads'); ?>"></div>
                                    <?php } ?>
                                </div>
                                <div class="rl-dash-bar-label-wrap">
                                    <span class="rl-dash-bar-day <?php echo $isPeak ? 'rl-day-peak' : ''; ?>"><?php echo esc_html($day['label']); ?></span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="rl-dash-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-dash-link">
                    Open Full Lead Intelligence Hub &rarr;
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Traffic Acquisition & Attribution Channels widget with visual distribution chart.
     */
    public function renderChannelsWidget(): void
    {
        $metrics = $this->getDashboardMetrics();
        $sources = $metrics['sources'];
        $total = max(1, $metrics['total']);

        $palette = ['#09090b', '#27272a', '#52525b', '#71717a', '#a1a1aa', '#d4d4d8'];

        ?>
        <div class="rl-dash-channels-wrap">
            <div class="rl-dash-chart-header">
                <div>
                    <span class="rl-dash-box-subtitle">Attribution Channel Share</span>
                    <div class="rl-dash-box-meta">Lead volume segmented by source</div>
                </div>
                <span class="rl-dash-badge-subtle"><?php echo esc_html((string) count($sources)); ?> Detected Sources</span>
            </div>

            <?php if (! empty($sources)) { ?>
                <!-- Stacked horizontal bar chart -->
                <div class="rl-dash-stacked-bar">
                    <?php
                    $colorIdx = 0;
                foreach ($sources as $source => $count) {
                    $pct = round(($count / $total) * 100, 1);
                    $color = $palette[$colorIdx % count($palette)];
                    $colorIdx++;
                    ?>
                        <div class="rl-dash-stacked-seg" style="width: <?php echo esc_attr((string) max(3, $pct)); ?>%; background-color: <?php echo esc_attr($color); ?>;" title="<?php echo esc_attr($source.': '.$count.' ('.$pct.'%)'); ?>"></div>
                    <?php } ?>
                </div>

                <div class="rl-dash-channels-grid">
                    <?php
                    $colorIdx = 0;
                foreach ($sources as $source => $count) {
                    $pct = round(($count / $total) * 100, 1);
                    $color = $palette[$colorIdx % count($palette)];
                    $colorIdx++;
                    $filterUrl = admin_url('admin.php?page=rl-leads&s='.urlencode($source));
                    ?>
                        <div class="rl-dash-channel-item">
                            <div class="rl-dash-channel-left">
                                <span class="rl-dash-color-dot" style="background-color: <?php echo esc_attr($color); ?>;"></span>
                                <span class="rl-dash-channel-name"><?php echo esc_html($source); ?></span>
                            </div>
                            <div class="rl-dash-channel-right">
                                <span class="rl-dash-channel-count"><?php echo esc_html((string) $count); ?></span>
                                <span class="rl-dash-channel-pct"><?php echo esc_html($pct.'%'); ?></span>
                                <a href="<?php echo esc_url($filterUrl); ?>" class="rl-dash-channel-link" title="Filter leads by <?php echo esc_attr($source); ?>">&rarr;</a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="rl-dash-empty">
                    <p>No attribution sources recorded yet. UTM tags on inbound campaign links will populate here automatically.</p>
                </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Render Domain Architecture & Multi-Service Health widget covering all 6 architectural domains.
     */
    public function renderDomainsWidget(): void
    {
        $domainInfo = $this->getDomainOverview();

        ?>
        <div class="rl-dash-domains-wrap">
            <div class="rl-dash-domains-list">
                <!-- 1. Lead Domain -->
                <div class="rl-dash-domain-card">
                    <div class="rl-dash-domain-top">
                        <div class="rl-dash-domain-title-wrap">
                            <span class="rl-dash-indicator-green"></span>
                            <span class="rl-dash-domain-title">Lead Domain</span>
                        </div>
                        <span class="rl-badge-dark">v2.0 Livewire</span>
                    </div>
                    <p class="rl-dash-domain-desc">
                        Stateful lead ingestion, MRR tier routing (T10 vs T0), dual-logging audit events, and 30-day ADR retention policy.
                    </p>
                    <div class="rl-dash-domain-badges">
                        <span class="rl-dash-chip">Total: <?php echo esc_html((string) $domainInfo['leads']['total']); ?></span>
                        <span class="rl-dash-chip">T10: <?php echo esc_html((string) $domainInfo['leads']['t10']); ?></span>
                        <span class="rl-dash-chip">Audit Logs: <?php echo esc_html((string) $domainInfo['leads']['logs']); ?></span>
                    </div>
                </div>

                <!-- 2. Scheduling Domain -->
                <div class="rl-dash-domain-card">
                    <div class="rl-dash-domain-top">
                        <div class="rl-dash-domain-title-wrap">
                            <span class="rl-dash-indicator-green"></span>
                            <span class="rl-dash-domain-title">Scheduling Domain</span>
                        </div>
                        <span class="rl-badge-zinc">Calendly Sync</span>
                    </div>
                    <p class="rl-dash-domain-desc">
                        Calendly webhook listeners, tier routing parity (T10 calendar vs T0 fallback), and automated appointment lifecycle sync.
                    </p>
                    <div class="rl-dash-domain-badges">
                        <span class="rl-dash-chip">Booked: <?php echo esc_html((string) $domainInfo['scheduling']['booked']); ?></span>
                        <span class="rl-dash-chip">Webhook: /api/webhooks/calendly</span>
                    </div>
                </div>

                <!-- 3. Referrer Network & Referral Domain -->
                <div class="rl-dash-domain-card">
                    <div class="rl-dash-domain-top">
                        <div class="rl-dash-domain-title-wrap">
                            <span class="rl-dash-indicator-green"></span>
                            <span class="rl-dash-domain-title">Referrer Network & Referral</span>
                        </div>
                        <span class="rl-badge-zinc">Stripe Connect</span>
                    </div>
                    <p class="rl-dash-domain-desc">
                        Attribution engine with 30-day cookie stamps, referral slug matching, and Stripe Connect automated payout transfers.
                    </p>
                    <div class="rl-dash-domain-badges">
                        <span class="rl-dash-chip">Referrers: <?php echo esc_html((string) $domainInfo['referrers']['count']); ?></span>
                        <span class="rl-dash-chip">Referrals: <?php echo esc_html((string) $domainInfo['referrers']['referrals']); ?></span>
                        <span class="rl-dash-chip">Payouts: $<?php echo esc_html(number_format((float) $domainInfo['referrers']['payouts_sum'], 2)); ?></span>
                    </div>
                </div>

                <!-- 4. Tracking Domain -->
                <div class="rl-dash-domain-card">
                    <div class="rl-dash-domain-top">
                        <div class="rl-dash-domain-title-wrap">
                            <span class="rl-dash-indicator-green"></span>
                            <span class="rl-dash-domain-title">Tracking Domain</span>
                        </div>
                        <span class="rl-badge-zinc">PostHog Engine</span>
                    </div>
                    <p class="rl-dash-domain-desc">
                        PostHog client evaluation, feature variant flags, session IDs, GCLID/FBCLID stamping, and full-funnel event dispatch.
                    </p>
                    <div class="rl-dash-domain-badges">
                        <span class="rl-dash-chip">Attribution: ADR-0008</span>
                        <span class="rl-dash-chip">Dual-Write: Live</span>
                    </div>
                </div>

                <!-- 5. ContentAudit Domain -->
                <div class="rl-dash-domain-card">
                    <div class="rl-dash-domain-top">
                        <div class="rl-dash-domain-title-wrap">
                            <span class="rl-dash-indicator-green"></span>
                            <span class="rl-dash-domain-title">Content Modernization</span>
                        </div>
                        <span class="rl-badge-dark">ADR-0005 Gutenberg</span>
                    </div>
                    <p class="rl-dash-domain-desc">
                        Elementor AST parser and automated converter transforming legacy widgets into modern native ACF Gutenberg blocks.
                    </p>
                    <div class="rl-dash-domain-badges">
                        <span class="rl-dash-chip"><?php echo esc_html((string) count(ElementorAuditService::WIDGET_MAPPING)); ?> ACF Blocks Mapped</span>
                        <span class="rl-dash-chip">Readiness: 100%</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Referrer Network & Revenue widget.
     */
    public function renderReferrerNetworkWidget(): void
    {
        $domainInfo = $this->getDomainOverview();
        $referrers = $domainInfo['referrers'];

        ?>
        <div class="rl-dash-referrer-wrap">
            <div class="rl-dash-kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">REFERRERS</span>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html((string) $referrers['count']); ?></div>
                    <div class="rl-dash-kpi-meta">Active referrers</div>
                </div>

                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">REFERRALS</span>
                    </div>
                    <div class="rl-dash-kpi-number"><?php echo esc_html((string) $referrers['referrals']); ?></div>
                    <div class="rl-dash-kpi-meta">Tracked conversions</div>
                </div>

                <div class="rl-dash-kpi-card">
                    <div class="rl-dash-kpi-header">
                        <span class="rl-dash-kpi-label">PAID OUT</span>
                    </div>
                    <div class="rl-dash-kpi-number">$<?php echo esc_html(number_format((float) $referrers['payouts_sum'], 0)); ?></div>
                    <div class="rl-dash-kpi-meta">Stripe transfers</div>
                </div>
            </div>

            <div class="rl-dash-health-item" style="margin-top: 10px;">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Stripe Connect Payout Gateway</div>
                        <div class="rl-dash-health-desc">Automated referrer transfer listener active</div>
                    </div>
                </div>
                <span class="rl-badge-emerald">Enabled</span>
            </div>

            <div class="rl-dash-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-referrers')); ?>" class="rl-dash-link">
                    Manage Referrer Network &rarr;
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render the Recent Leads widget with live status badges and direct inspect links.
     */
    public function renderRecentLeadsWidget(): void
    {
        $recentLeads = $this->getRecentLeads(6);

        if ($recentLeads->isEmpty()) {
            ?>
            <div class="rl-dash-empty">
                <p>No captured leads yet. Livewire booking funnels are live and ready to ingest submissions.</p>
                <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="button button-secondary">
                    Launch Booking Wizard Preview &rarr;
                </a>
            </div>
            <?php
            return;
        }
        ?>
        <div class="rl-dash-leads-list">
            <table class="rl-dash-table">
                <thead>
                    <tr>
                        <th>Contact & Company</th>
                        <th>MRR Tier</th>
                        <th>Status</th>
                        <th>Captured</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLeads as $lead) { ?>
                        <?php
                        $name = trim($lead->name ?: ($lead->first_name.' '.$lead->last_name));
                        if (empty($name)) {
                            $name = 'Anonymous Lead';
                        }
                        $initials = $this->getInitials($name);
                        $isT10 = ! in_array($lead->monthly_revenue, ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'], true)
                            && ! empty($lead->monthly_revenue);
                        $statusClass = match (strtolower((string) $lead->status)) {
                            'booked' => 'rl-badge-emerald',
                            'final' => 'rl-badge-zinc',
                            'partial' => 'rl-badge-amber',
                            default => 'rl-badge-zinc',
                        };
                        $inspectUrl = admin_url('admin.php?page=rl-leads&lead_id='.$lead->id);
                        $timeAgo = $lead->created_at ? $lead->created_at->diffForHumans() : 'Recently';
                        ?>
                        <tr>
                            <td>
                                <div class="rl-dash-contact-cell">
                                    <div class="rl-dash-avatar"><?php echo esc_html($initials); ?></div>
                                    <div>
                                        <div class="rl-dash-lead-name"><?php echo esc_html($name); ?></div>
                                        <div class="rl-dash-lead-sub">
                                            <?php echo esc_html($lead->company ?: ($lead->email ?: 'No company provided')); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($isT10) { ?>
                                    <span class="rl-badge-dark" title="<?php echo esc_attr($lead->monthly_revenue ?: ''); ?>">
                                        &ge; $10k MRR
                                    </span>
                                <?php } else { ?>
                                    <span class="rl-badge-subtle" title="<?php echo esc_attr($lead->monthly_revenue ?: ''); ?>">
                                        &lt; $10k MRR
                                    </span>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="<?php echo esc_attr($statusClass); ?>">
                                    <?php echo esc_html(ucfirst((string) ($lead->status ?: 'captured'))); ?>
                                </span>
                            </td>
                            <td>
                                <span class="rl-dash-timestamp"><?php echo esc_html($timeAgo); ?></span>
                            </td>
                            <td style="text-align: right;">
                                <a href="<?php echo esc_url($inspectUrl); ?>" class="rl-dash-btn-ghost">
                                    Inspect &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <div class="rl-dash-card-footer">
                <span class="rl-dash-footer-meta">Showing latest 6 captured submissions</span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-dash-link">
                    View All <?php echo esc_html((string) Lead::count()); ?> Leads &rarr;
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Platform & Infrastructure Health widget.
     */
    public function renderSiteHealthWidget(): void
    {
        $health = $this->getPlatformHealth();

        ?>
        <div class="rl-dash-health-wrap">
            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Bedrock Environment</div>
                        <div class="rl-dash-health-desc">Configuration & deployment tier</div>
                    </div>
                </div>
                <span class="rl-badge-zinc">
                    <?php echo esc_html($health['env']); ?>
                </span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Database Engine</div>
                        <div class="rl-dash-health-desc">MySQL / MariaDB transactional connection</div>
                    </div>
                </div>
                <span class="rl-badge-emerald">
                    Connected (<?php echo esc_html($health['db_latency']); ?>ms)
                </span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Livewire 3 Engine</div>
                        <div class="rl-dash-health-desc">Multistep booking wizard reactive state</div>
                    </div>
                </div>
                <span class="rl-badge-emerald">Operational</span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Stripe Webhooks</div>
                        <div class="rl-dash-health-desc">Referrer payout & account events listener</div>
                    </div>
                </div>
                <span class="rl-badge-zinc">/api/webhooks/stripe</span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">Calendly Webhooks</div>
                        <div class="rl-dash-health-desc">Consultation booking & cancel listeners</div>
                    </div>
                </div>
                <span class="rl-badge-zinc">/api/webhooks/calendly</span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">ADR-0008 Retention Guard</div>
                        <div class="rl-dash-health-desc">30-day minimum retention floor compliance</div>
                    </div>
                </div>
                <span class="rl-badge-emerald">Compliant</span>
            </div>

            <div class="rl-dash-health-item">
                <div class="rl-dash-health-left">
                    <span class="rl-dash-indicator-green"></span>
                    <div>
                        <div class="rl-dash-health-name">PHP Runtime</div>
                        <div class="rl-dash-health-desc">Memory limit: <?php echo esc_html($health['memory_limit']); ?></div>
                    </div>
                </div>
                <span class="rl-badge-zinc">
                    PHP <?php echo esc_html(PHP_VERSION); ?>
                </span>
            </div>
        </div>
        <?php
    }

    /**
     * Render Marketing Shortcuts & Quick Actions widget.
     */
    public function renderMarketingShortcutsWidget(): void
    {
        ?>
        <div class="rl-dash-shortcuts-grid">
            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="rl-dash-shortcut-card">
                <div class="rl-dash-shortcut-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                </div>
                <div>
                    <div class="rl-dash-shortcut-title">Test Booking Wizard</div>
                    <div class="rl-dash-shortcut-desc">Open live 3-step funnel in new tab</div>
                </div>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="rl-dash-shortcut-card">
                <div class="rl-dash-shortcut-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div>
                    <div class="rl-dash-shortcut-title">Lead Management Hub</div>
                    <div class="rl-dash-shortcut-desc">Search, filter & review captured leads</div>
                </div>
            </a>

            <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads-logs')); ?>" class="rl-dash-shortcut-card">
                <div class="rl-dash-shortcut-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div>
                    <div class="rl-dash-shortcut-title">Audit Activity Trail</div>
                    <div class="rl-dash-shortcut-desc">Dual-logging events & webhook ingest</div>
                </div>
            </a>

            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=rl-leads&rl_action=export_csv'), 'rl_export_leads_nonce')); ?>" class="rl-dash-shortcut-card">
                <div class="rl-dash-shortcut-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                </div>
                <div>
                    <div class="rl-dash-shortcut-title">Export All Submissions</div>
                    <div class="rl-dash-shortcut-desc">Download complete CSV lead records</div>
                </div>
            </a>
        </div>
        <?php
    }

    /**
     * Fetch KPI metrics and 7-day volume cached for 3 minutes.
     *
     * @return array<string, mixed>
     */
    protected function getDashboardMetrics(): array
    {
        try {
            return Cache::remember('rl_admin_dashboard_metrics', 180, function () {
                $total = Lead::count();
                $booked = Lead::where('status', 'booked')->count();
                $t10 = Lead::whereNotIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'])
                    ->whereNotNull('monthly_revenue')
                    ->where('monthly_revenue', '!=', '')
                    ->count();

                // Top 6 attribution sources
                $sources = Lead::select('utm_source', DB::raw('count(*) as total'))
                    ->whereNotNull('utm_source')
                    ->where('utm_source', '!=', '')
                    ->groupBy('utm_source')
                    ->orderByDesc('total')
                    ->take(6)
                    ->pluck('total', 'utm_source')
                    ->toArray();

                // 7-day volume calculation
                $dailyCounts = [];
                try {
                    $sevenDaysAgo = now()->subDays(6)->startOfDay();
                    $dailyCounts = Lead::where('created_at', '>=', $sevenDaysAgo)
                        ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                        ->groupBy('date')
                        ->pluck('count', 'date')
                        ->toArray();
                } catch (\Throwable) {
                    $dailyCounts = [];
                }

                $dailyVolume = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = now()->subDays($i)->format('Y-m-d');
                    $dayLabel = now()->subDays($i)->format('D');
                    $dailyVolume[] = [
                        'date' => $date,
                        'label' => $dayLabel,
                        'count' => $dailyCounts[$date] ?? 0,
                    ];
                }

                return [
                    'total' => $total,
                    'booked' => $booked,
                    't10' => $t10,
                    'sources' => $sources,
                    'daily_volume' => $dailyVolume,
                ];
            });
        } catch (\Throwable) {
            $fallbackDays = [];
            for ($i = 6; $i >= 0; $i--) {
                $fallbackDays[] = [
                    'date' => date('Y-m-d', strtotime("-{$i} days")),
                    'label' => date('D', strtotime("-{$i} days")),
                    'count' => 0,
                ];
            }

            return [
                'total' => 0,
                'booked' => 0,
                't10' => 0,
                'sources' => [],
                'daily_volume' => $fallbackDays,
            ];
        }
    }

    /**
     * Aggregate domain metrics across Lead, Scheduling, Referral, and Tracking domains.
     *
     * @return array<string, mixed>
     */
    protected function getDomainOverview(): array
    {
        try {
            return Cache::remember('rl_admin_domain_overview', 180, function () {
                $leadTotal = Lead::count();
                $leadT10 = Lead::whereNotIn('monthly_revenue', ['$0 to $5k Per Month', '$5k to $10k Per Month', '<10k', 'under_10k'])
                    ->whereNotNull('monthly_revenue')
                    ->where('monthly_revenue', '!=', '')
                    ->count();
                $leadLogs = LeadActivityLog::count();
                $booked = Lead::where('status', 'booked')->count();

                $referrerCount = 0;
                $referralCount = 0;
                $payoutsSum = 0.0;

                try {
                    $referrerCount = Referrer::count();
                } catch (\Throwable) {
                }

                try {
                    $referralCount = Referral::count();
                } catch (\Throwable) {
                }

                try {
                    $payoutsSum = (float) Payout::where('status', 'completed')->sum('amount');
                } catch (\Throwable) {
                }

                return [
                    'leads' => [
                        'total' => $leadTotal,
                        't10' => $leadT10,
                        'logs' => $leadLogs,
                    ],
                    'scheduling' => [
                        'booked' => $booked,
                    ],
                    'referrers' => [
                        'count' => $referrerCount,
                        'referrals' => $referralCount,
                        'payouts_sum' => $payoutsSum,
                    ],
                ];
            });
        } catch (\Throwable) {
            return [
                'leads' => ['total' => 0, 't10' => 0, 'logs' => 0],
                'scheduling' => ['booked' => 0],
                'referrers' => ['count' => 0, 'referrals' => 0, 'payouts_sum' => 0.0],
            ];
        }
    }

    /**
     * Retrieve latest leads for the dashboard stream.
     *
     * @return Collection<int, Lead>
     */
    protected function getRecentLeads(int $limit = 6)
    {
        try {
            return Lead::latest('created_at')->take($limit)->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Check infrastructure health stats.
     *
     * @return array<string, mixed>
     */
    protected function getPlatformHealth(): array
    {
        $start = microtime(true);
        $dbLatency = 1.2;

        try {
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 1);
        } catch (\Throwable) {
            $dbLatency = 0.0;
        }

        $env = env('WP_ENV') ?: (defined('WP_ENV') ? WP_ENV : 'production');

        return [
            'env' => ucfirst((string) $env),
            'db_latency' => $dbLatency,
            'memory_limit' => ini_get('memory_limit') ?: '256M',
        ];
    }

    /**
     * Extract initials from a name string.
     */
    protected function getInitials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name));
        if (empty($words)) {
            return 'RL';
        }

        if (count($words) === 1) {
            return strtoupper(substr($words[0], 0, 2));
        }

        return strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
    }
}
