<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Lead\Models\Lead;
use App\Domains\Lead\Models\LeadActivityLog;
use Illuminate\Support\Facades\Http;

class LeadsAdminDashboard
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            page_title: 'Leads',
            menu_title: 'Leads',
            capability: 'manage_options',
            menu_slug: 'rl-leads',
            callback: [$this, 'renderDashboard'],
            icon_url: 'dashicons-groups',
            position: 30
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'Submissions & Activity Logs',
            menu_title: 'All Leads',
            capability: 'manage_options',
            menu_slug: 'rl-leads',
            callback: [$this, 'renderDashboard']
        );

        add_submenu_page(
            parent_slug: 'rl-leads',
            page_title: 'API & Integration Diagnostics',
            menu_title: 'Diagnostics',
            capability: 'manage_options',
            menu_slug: 'rl-leads-diagnostics',
            callback: [$this, 'renderDiagnostics']
        );
    }

    public function enqueueAdminStyles(string $hook): void
    {
        if (! str_contains($hook, 'rl-leads')) {
            return;
        }

        wp_add_inline_style('wp-admin', '
            .rl-admin-wrap { max-width: 1280px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; }
            .rl-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
            .rl-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
            .rl-card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 6px; }
            .rl-card-value { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; }
            .rl-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
            .rl-badge-booked { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
            .rl-badge-qualified { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
            .rl-badge-partial { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; }
            .rl-badge-abandoned { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
            .rl-badge-succeeded { background: #dcfce7; color: #15803d; }
            .rl-badge-failed { background: #fee2e2; color: #b91c1c; }
            .rl-badge-dispatched { background: #e0f2fe; color: #0369a1; }
            .rl-table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-top: 16px; }
            .rl-lead-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
            .rl-lead-table th { background: #f8fafc; padding: 12px 16px; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
            .rl-lead-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b; vertical-align: top; }
            .rl-lead-table tr:hover td { background: #f8fafc; }
            .rl-lead-row-click { cursor: pointer; transition: background 0.15s; }
            .rl-timeline { position: relative; margin-left: 20px; padding-left: 24px; border-left: 2px solid #e2e8f0; }
            .rl-timeline-item { position: relative; margin-bottom: 24px; }
            .rl-timeline-dot { position: absolute; left: -31px; top: 3px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; background: #6366f1; box-shadow: 0 0 0 2px #e2e8f0; }
            .rl-timeline-dot.dot-failed { background: #ef4444; box-shadow: 0 0 0 2px #fecaca; }
            .rl-timeline-dot.dot-succeeded { background: #10b981; box-shadow: 0 0 0 2px #a7f3d0; }
            .rl-timeline-content { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; }
            .rl-timeline-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 11px; }
            .rl-json-box { background: #0f172a; color: #e2e8f0; padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 11px; overflow-x: auto; max-height: 250px; margin-top: 8px; white-space: pre-wrap; }
            .rl-filter-bar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        ');
    }

    public function renderDashboard(): void
    {
        $viewLeadId = isset($_GET['view_lead']) ? absint($_GET['view_lead']) : 0;

        if ($viewLeadId > 0) {
            $this->renderLeadDetail($viewLeadId);

            return;
        }

        $search = sanitize_text_field($_GET['s'] ?? '');
        $statusFilter = sanitize_text_field($_GET['status'] ?? '');

        $query = Lead::query()->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('company', 'LIKE', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $totalLeads = Lead::count();
        $bookedLeads = Lead::where('status', 'booked')->count();
        $qualifiedLeads = Lead::where('status', 'qualified')->count();
        $partialLeads = Lead::where('status', 'partial')->count();

        $leads = $query->paginate(20);

        ?>
        <div class="wrap rl-admin-wrap">
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 16px;">
                Remote Leverage &mdash; Submissions & Activity Logs
            </h1>

            <div class="rl-stats-grid">
                <div class="rl-card">
                    <div class="rl-card-title">Total Submissions</div>
                    <div class="rl-card-value"><?php echo esc_html((string) $totalLeads); ?></div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-title">Booked Consultations</div>
                    <div class="rl-card-value" style="color: #15803d;"><?php echo esc_html((string) $bookedLeads); ?></div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-title">Qualified Leads</div>
                    <div class="rl-card-value" style="color: #4338ca;"><?php echo esc_html((string) $qualifiedLeads); ?></div>
                </div>
                <div class="rl-card">
                    <div class="rl-card-title">Partial Entries</div>
                    <div class="rl-card-value" style="color: #b45309;"><?php echo esc_html((string) $partialLeads); ?></div>
                </div>
            </div>

            <div class="rl-filter-bar">
                <form method="get" action="" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="page" value="rl-leads" />
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search name, email, phone..." style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 260px;" />
                    <select name="status" style="padding: 5px 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                        <option value="">All Statuses</option>
                        <option value="booked" <?php selected($statusFilter, 'booked'); ?>>Booked</option>
                        <option value="qualified" <?php selected($statusFilter, 'qualified'); ?>>Qualified</option>
                        <option value="partial" <?php selected($statusFilter, 'partial'); ?>>Partial</option>
                    </select>
                    <button type="submit" class="button button-primary">Filter</button>
                    <?php if ($search || $statusFilter) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" class="button">Reset</a>
                    <?php endif; ?>
                </form>

                <div style="font-size: 12px; color: #64748b;">
                    Showing <?php echo esc_html((string) count($leads->items())); ?> entries
                </div>
            </div>

            <div class="rl-table-container">
                <table class="rl-lead-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Lead Contact</th>
                            <th>Monthly Revenue (MRR)</th>
                            <th>Status</th>
                            <th>Traffic Source / UTM</th>
                            <th>Activity Events</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leads->isEmpty()) : ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 32px; color: #94a3b8;">
                                    No submissions found matching your filters.
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($leads as $lead) : ?>
                                <tr class="rl-lead-row-click" onclick="window.location.href='<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)); ?>'">
                                    <td>
                                        <strong><?php echo esc_html($lead->created_at->format('M j, Y')); ?></strong><br>
                                        <span style="font-size: 11px; color: #64748b;"><?php echo esc_html($lead->created_at->format('g:i A')); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($lead->name ?: 'N/A'); ?></strong><br>
                                        <span style="color: #6366f1;"><?php echo esc_html($lead->email); ?></span>
                                        <?php if ($lead->phone) : ?>
                                            <br><span style="font-size: 11px; color: #64748b;"><?php echo esc_html($lead->phone); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: #0f172a;"><?php echo esc_html($lead->monthly_revenue ?: '—'); ?></span>
                                        <?php if ($lead->role_needed) : ?>
                                            <br><span style="font-size: 11px; color: #64748b;">Role: <?php echo esc_html($lead->role_needed); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="rl-badge rl-badge-<?php echo esc_attr($lead->status); ?>">
                                            <?php echo esc_html($lead->status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($lead->utm_source || $lead->utm_campaign) : ?>
                                            <strong><?php echo esc_html($lead->utm_source ?: 'direct'); ?></strong>
                                            <?php if ($lead->utm_campaign) : ?>
                                                <br><span style="font-size: 11px; color: #64748b;">cmp: <?php echo esc_html($lead->utm_campaign); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($lead->source_type) : ?>
                                            <span style="color: #64748b; font-size: 11px;"><?php echo esc_html($lead->source_type); ?></span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8; font-style: italic; font-size: 11px;">Direct / Organic</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: #475569;"><?php echo esc_html((string) $lead->activityLogs()->count()); ?> logs</span>
                                    </td>
                                    <td style="text-align: right;" onclick="event.stopPropagation();">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads&view_lead='.$lead->id)); ?>" class="button button-small">
                                            View Timeline &rarr;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function renderLeadDetail(int $leadId): void
    {
        $lead = Lead::with(['activityLogs' => fn ($q) => $q->orderBy('created_at', 'asc')])->find($leadId);

        if (! $lead) {
            echo '<div class="wrap"><div class="notice notice-error"><p>Lead not found.</p></div></div>';

            return;
        }

        ?>
        <div class="wrap rl-admin-wrap">
            <div style="margin-bottom: 16px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rl-leads')); ?>" style="text-decoration: none; font-weight: 600; color: #6366f1;">
                    &larr; Back to all submissions
                </a>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0;">
                        <?php echo esc_html($lead->name ?: 'Lead #'.$lead->id); ?>
                    </h1>
                    <div style="color: #64748b; font-size: 13px;">
                        Submitted on <?php echo esc_html($lead->created_at->format('F j, Y \a\t g:i:s A')); ?> &bull; Lead UUID: <code><?php echo esc_html($lead->uuid); ?></code>
                    </div>
                </div>
                <span class="rl-badge rl-badge-<?php echo esc_attr($lead->status); ?>" style="font-size: 13px; padding: 4px 12px;">
                    <?php echo esc_html(strtoupper($lead->status)); ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
                <!-- Lead Attributes Overview -->
                <div>
                    <div class="rl-card" style="margin-bottom: 16px;">
                        <h3 style="margin-top: 0; font-size: 14px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                            Contact & Qualification
                        </h3>
                        <table style="width: 100%; font-size: 12px; line-height: 1.8;">
                            <tr><td style="color: #64748b; width: 120px;">Email:</td><td><strong><?php echo esc_html($lead->email); ?></strong></td></tr>
                            <tr><td style="color: #64748b;">Phone:</td><td><?php echo esc_html($lead->phone ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Company:</td><td><?php echo esc_html($lead->company ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Monthly Revenue:</td><td><strong><?php echo esc_html($lead->monthly_revenue ?: '—'); ?></strong></td></tr>
                            <tr><td style="color: #64748b;">Role Needed:</td><td><?php echo esc_html($lead->role_needed ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Weekly Hours:</td><td><?php echo esc_html($lead->weekly_hours ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Start Timeline:</td><td><?php echo esc_html($lead->start_date ?: '—'); ?></td></tr>
                            <?php if ($lead->notes) : ?>
                                <tr><td style="color: #64748b; vertical-align: top;">Notes:</td><td><?php echo nl2br(esc_html($lead->notes)); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="rl-card">
                        <h3 style="margin-top: 0; font-size: 14px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                            Attribution & UTM Data
                        </h3>
                        <table style="width: 100%; font-size: 12px; line-height: 1.8;">
                            <tr><td style="color: #64748b; width: 120px;">Source:</td><td><?php echo esc_html($lead->utm_source ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Medium:</td><td><?php echo esc_html($lead->utm_medium ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Campaign:</td><td><?php echo esc_html($lead->utm_campaign ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Term / Content:</td><td><?php echo esc_html(trim(($lead->utm_term ?: '').' '.($lead->utm_content ?: '')) ?: '—'); ?></td></tr>
                            <tr><td style="color: #64748b;">Source Type:</td><td><?php echo esc_html($lead->source_type ?: 'organic'); ?></td></tr>
                            <?php if ($lead->referral_code) : ?>
                                <tr><td style="color: #64748b;">Referral Code:</td><td><code><?php echo esc_html($lead->referral_code); ?></code></td></tr>
                            <?php endif; ?>
                            <?php if ($lead->landing_url) : ?>
                                <tr><td style="color: #64748b;">Landing URL:</td><td style="word-break: break-all; font-size: 11px;"><?php echo esc_html($lead->landing_url); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Timeline of Activity Logs -->
                <div>
                    <div class="rl-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                            <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #0f172a;">
                                Execution & Integration Timeline
                            </h3>
                            <span style="font-size: 12px; color: #64748b;">
                                <?php echo esc_html((string) $lead->activityLogs->count()); ?> logged events
                            </span>
                        </div>

                        <?php if ($lead->activityLogs->isEmpty()) : ?>
                            <p style="color: #94a3b8; font-size: 13px;">No activity logged yet for this lead.</p>
                        <?php else : ?>
                            <div class="rl-timeline">
                                <?php foreach ($lead->activityLogs as $log) : ?>
                                    <div class="rl-timeline-item">
                                        <div class="rl-timeline-dot dot-<?php echo esc_attr($log->outcome); ?>"></div>
                                        <div class="rl-timeline-content">
                                            <div class="rl-timeline-meta">
                                                <span style="font-weight: 700; color: #0f172a;"><?php echo esc_html($log->actor_domain); ?></span>
                                                <span class="rl-badge rl-badge-<?php echo esc_attr($log->outcome); ?>"><?php echo esc_html($log->outcome); ?></span>
                                                <span style="color: #64748b;"><?php echo esc_html(strtoupper($log->stage)); ?></span>
                                                <span style="color: #94a3b8; margin-left: auto;"><?php echo esc_html($log->created_at->format('H:i:s')); ?></span>
                                            </div>
                                            <div style="font-size: 13px; font-weight: 500; color: #334155; margin-bottom: 4px;">
                                                <?php echo esc_html($log->description); ?>
                                            </div>
                                            <?php if (! empty($log->payload)) : ?>
                                                <div class="rl-json-box"><?php echo esc_html(json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderDiagnostics(): void
    {
        $apiKey = config('services.calendly.api_key');
        $t10 = config('services.calendly.t10_event_type');
        $t0 = config('services.calendly.t0_event_type');

        $t10Status = 'Unknown';
        $t10Name = '';
        $t0Status = 'Unknown';
        $t0Name = '';

        if ($apiKey) {
            try {
                $r1 = Http::withToken($apiKey)->get($t10);
                $t10Status = $r1->successful() ? 'Active (200 OK)' : 'Error ('.$r1->status().')';
                $t10Name = $r1->json('resource.name', '');

                $r2 = Http::withToken($apiKey)->get($t0);
                $t0Status = $r2->successful() ? 'Active (200 OK)' : 'Error ('.$r2->status().')';
                $t0Name = $r2->json('resource.name', '');
            } catch (\Throwable $e) {
                $t10Status = 'Connection error: '.$e->getMessage();
            }
        }

        ?>
        <div class="wrap rl-admin-wrap">
            <h1 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 16px;">
                Remote Leverage &mdash; System Diagnostics
            </h1>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="rl-card">
                    <h3 style="margin-top: 0; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        Calendly Direct API Connection
                    </h3>
                    <table style="width: 100%; font-size: 12px; line-height: 2;">
                        <tr>
                            <td style="color: #64748b; width: 140px;">API Key Configured:</td>
                            <td><?php echo $apiKey ? '<span class="rl-badge rl-badge-succeeded">Configured (PAT)</span>' : '<span class="rl-badge rl-badge-failed">Missing</span>'; ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">T10 Event Type (&ge;$10k MRR):</td>
                            <td>
                                <strong><?php echo esc_html($t10Name ?: 'VA Hiring Consultation T10 (A)'); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b;">Status: <?php echo esc_html($t10Status); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">T0 Event Type (&lt;$10k MRR):</td>
                            <td>
                                <strong><?php echo esc_html($t0Name ?: 'VA Hiring Consultation T0 (A)'); ?></strong><br>
                                <span style="font-size: 11px; color: #64748b;">Status: <?php echo esc_html($t0Status); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="rl-card">
                    <h3 style="margin-top: 0; font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                        Webhook & CRM Integrations
                    </h3>
                    <table style="width: 100%; font-size: 12px; line-height: 2;">
                        <tr>
                            <td style="color: #64748b; width: 140px;">HubSpot CRM Sync:</td>
                            <td><?php echo config('services.hubspot.api_key') ? '<span class="rl-badge rl-badge-succeeded">Active</span>' : '<span class="rl-badge" style="background:#f1f5f9; color:#475569;">Simulated Fallback</span>'; ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Slack Notifications:</td>
                            <td><?php echo config('services.slack.webhook_url') ? '<span class="rl-badge rl-badge-succeeded">Active</span>' : '<span class="rl-badge" style="background:#f1f5f9; color:#475569;">Simulated Fallback</span>'; ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Outgoing Webhooks:</td>
                            <td><?php echo config('services.webhooks.default_endpoint') ? '<span class="rl-badge rl-badge-succeeded">Active</span>' : '<span class="rl-badge" style="background:#f1f5f9; color:#475569;">Simulated Fallback</span>'; ?></td>
                        </tr>
                        <tr>
                            <td style="color: #64748b;">Database Audit Storage:</td>
                            <td><span class="rl-badge rl-badge-succeeded">Active (`rl_leads`, `rl_lead_activity_logs`)</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}
