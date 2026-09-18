<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Lead\Export\LeadExportColumns;
use App\Domains\Lead\Export\LeadExportJob;
use App\Domains\Lead\Export\LeadExportJobStore;
use App\Domains\Lead\Export\LeadExportOptions;
use App\Domains\Lead\Export\LeadExportRunner;
use App\Domains\Lead\Services\LeadPlatform;
use App\Domains\Lead\Services\LeadStatus;
use App\Domains\Lead\Services\LeadSubmission;

/**
 * The CSV export modal on the leads list, and the endpoints behind it.
 *
 * Split out of LeadsAdminDashboard rather than added to it: that class is already past 2,400
 * lines, and this is a self-contained feature with its own endpoints, its own markup and its own
 * script. The dashboard calls two methods on it and owns none of this.
 *
 * The export runs as a series of short AJAX calls driven by the open tab — the same shape as
 * Settings → Environment Sync, and for the same reason: there is no queue worker here, and a
 * single request that walks the whole lead table is a request that dies on a timeout somewhere
 * between PHP, nginx and Cloudflare, having produced a truncated file that looks complete.
 */
class LeadExportPanel
{
    /** One nonce action for the three AJAX endpoints; the download has its own. */
    public const NONCE = 'rl_lead_export';

    public const DOWNLOAD_NONCE = 'rl_lead_export_download';

    public function __construct(
        private readonly LeadExportJobStore $store,
        private readonly LeadExportRunner $runner,
    ) {}

    public function register(): void
    {
        add_action('wp_ajax_rl_lead_export_count', [$this, 'ajaxCount']);
        add_action('wp_ajax_rl_lead_export_start', [$this, 'ajaxStart']);
        add_action('wp_ajax_rl_lead_export_step', [$this, 'ajaxStep']);
    }

    /**
     * How many leads the current filter selection matches, for the slider's range.
     *
     * Its own endpoint because the slider's maximum is meaningless without it: offering "10,000"
     * on a filter that matches 40 leads invites someone to wait for a file that was finished
     * before the bar appeared.
     */
    public function ajaxCount(): void
    {
        $this->authorize();

        $options = LeadExportOptions::fromRequest($this->input());

        wp_send_json_success([
            'total' => $options->query()->count(),
        ]);
    }

    public function ajaxStart(): void
    {
        $this->authorize();

        if (($running = $this->store->active()) !== null) {
            wp_send_json_error([
                'message' => 'An export is already running ('.$running->label().'). Wait for it to finish, or reload the page.',
            ]);
        }

        $job = $this->runner->start(LeadExportOptions::fromRequest($this->input()));

        wp_send_json_success($this->state($job));
    }

    public function ajaxStep(): void
    {
        $this->authorize();

        $job = $this->store->find(sanitize_text_field($_POST['job'] ?? ''));

        if ($job === null) {
            wp_send_json_error(['message' => 'That export is no longer on file. Start a new one.']);
        }

        wp_send_json_success($this->state($this->runner->step($job)));
    }

    /**
     * Send a finished export to the browser.
     *
     * Served through PHP rather than linked to directly in uploads, so the file is behind the
     * same capability check as the screen that made it. Called from the dashboard's admin_init
     * handler, before any output.
     */
    public function handleDownload(string $jobId): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You are not allowed to download lead exports.');
        }

        check_admin_referer(self::DOWNLOAD_NONCE);

        $job = $this->store->find($jobId);

        if ($job === null || $job->phase !== 'done' || ! is_file($job->path)) {
            wp_die('That export is no longer available. Exports are kept for the last '.LeadExportJobStore::RETENTION.' runs.');
        }

        // Anything already buffered would be prepended to the CSV and corrupt the first cell.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$job->filename.'"');
        header('Content-Length: '.(string) filesize($job->path));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($job->path);
        exit;
    }

    /** The toolbar button that opens the modal. */
    public function renderTrigger(): void
    {
        ?>
        <button type="button" class="rl-btn rl-btn-outline" id="rl-export-open">
            <?php echo $this->iconDownload(); ?> Export Leads (CSV)
        </button>
        <?php
    }

    /**
     * The modal itself. Rendered once per leads list, hidden until the button is pressed.
     *
     * @param  string  $search  The list's current search term, carried in so an export started
     *                          from a filtered list covers what the list was showing.
     * @param  string  $status  The list's current status filter, used as the modal's default.
     * @param  string  $platform  The list's current platform filter, same reason.
     * @param  string  $audience  The list's current possible-VA filter, same reason.
     * @param  string  $submission  The list's current partial/final filter, same reason.
     */
    public function renderModal(string $search = '', string $status = '', string $platform = '', string $audience = '', string $submission = ''): void
    {
        $groups = LeadExportColumns::groups();
        $recent = array_values(array_filter(
            $this->store->recent(),
            static fn ($job) => $job->phase === 'done',
        ));

        $config = wp_json_encode([
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'downloadBase' => wp_nonce_url(
                admin_url('admin.php?page=rl-leads&rl_action=download_export'),
                self::DOWNLOAD_NONCE,
            ),
        ]);

        $this->styles();
        ?>
        <div class="rl-export-scrim" id="rl-export-modal" hidden>
            <aside class="rl-export-flyout" role="dialog" aria-modal="true" aria-labelledby="rl-export-title">
                <div class="rl-export-head">
                    <div>
                        <h2 id="rl-export-title">Export leads to CSV</h2>
                        <p class="rl-export-sub">
                            Large exports are written in batches while this window stays open. You can
                            watch the progress and download the file when it finishes.
                        </p>
                    </div>
                    <button type="button" class="rl-export-close" id="rl-export-close" aria-label="Close">&times;</button>
                </div>

                <div class="rl-export-body" id="rl-export-form">
                    <fieldset class="rl-export-field">
                        <legend>Columns</legend>
                        <div class="rl-export-groups">
                            <?php foreach ($groups as $slug => $group) { ?>
                                <label class="rl-export-check<?php echo $group['always'] ? ' is-locked' : ''; ?>">
                                    <input type="checkbox" value="<?php echo esc_attr($slug); ?>"
                                           data-export-group
                                           <?php echo $group['always'] ? 'checked disabled' : 'checked'; ?> />
                                    <span>
                                        <strong><?php echo esc_html($group['label']); ?></strong>
                                        <em><?php echo esc_html($group['note']); ?></em>
                                    </span>
                                </label>
                            <?php } ?>
                        </div>
                    </fieldset>

                    <div class="rl-export-row">
                        <label class="rl-export-field">
                            <span class="rl-export-label">Status</span>
                            <select id="rl-export-status" class="rl-select">
                                <option value="">Any status</option>
                                <?php foreach (LeadStatus::options() as $slug => $label) { ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($status, $slug); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>

                        <label class="rl-export-field">
                            <span class="rl-export-label">Source type</span>
                            <select id="rl-export-source" class="rl-select">
                                <option value="">Any source</option>
                                <?php foreach (['organic', 'paid', 'referral_hub', 'partnership', 'direct'] as $option) { ?>
                                    <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                    </div>

                    <div class="rl-export-row">
                        <label class="rl-export-field">
                            <span class="rl-export-label">Platform</span>
                            <select id="rl-export-platform" class="rl-select">
                                <option value="">Any platform</option>
                                <?php foreach (LeadPlatform::options() as $slug => $label) { ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($platform, $slug); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>

                        <label class="rl-export-field">
                            <span class="rl-export-label">Submission</span>
                            <select id="rl-export-submission" class="rl-select">
                                <option value="">Any submission</option>
                                <?php foreach (LeadSubmission::options() as $slug => $label) { ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($submission, $slug); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>

                        <label class="rl-export-field">
                            <span class="rl-export-label">Audience</span>
                            <select id="rl-export-audience" class="rl-select">
                                <option value="">Everyone</option>
                                <option value="clients" <?php selected($audience, 'clients'); ?>>Exclude possible VAs</option>
                                <option value="va" <?php selected($audience, 'va'); ?>>Only possible VAs</option>
                            </select>
                        </label>
                    </div>

                    <div class="rl-export-row">
                        <label class="rl-export-field">
                            <span class="rl-export-label">Captured from</span>
                            <input type="date" id="rl-export-from" class="rl-input" />
                        </label>
                        <label class="rl-export-field">
                            <span class="rl-export-label">Captured to</span>
                            <input type="date" id="rl-export-to" class="rl-input" />
                        </label>
                    </div>

                    <label class="rl-export-check">
                        <input type="checkbox" id="rl-export-deleted" />
                        <span>
                            <strong>Include deleted leads</strong>
                            <em>Rows that were soft-deleted and are hidden from the list.</em>
                        </span>
                    </label>

                    <div class="rl-export-field">
                        <span class="rl-export-label">
                            How many leads
                            <strong id="rl-export-amount">All</strong>
                        </span>
                        <input type="range" id="rl-export-limit" min="0" max="1" value="1" step="1" />
                        <p class="rl-export-hint" id="rl-export-matched">Counting matching leads…</p>
                    </div>
                </div>

                <div class="rl-export-body" id="rl-export-progress" hidden>
                    <p class="rl-export-status" id="rl-export-status-text">Starting…</p>
                    <div class="rl-export-bar"><div class="rl-export-bar-fill" id="rl-export-bar-fill"></div></div>
                    <p class="rl-export-hint">
                        Keep this window open. Each batch is a separate request, so closing the tab
                        stops the export where it stands — it does not corrupt anything.
                    </p>
                </div>

                <?php if ($recent !== []) { ?>
                    <div class="rl-export-recent">
                        <span class="rl-export-label">Recent exports</span>
                        <?php foreach ($recent as $job) { ?>
                            <a href="<?php echo esc_url(add_query_arg('job', $job->id, wp_nonce_url(admin_url('admin.php?page=rl-leads&rl_action=download_export'), self::DOWNLOAD_NONCE))); ?>">
                                <?php echo esc_html($job->filename); ?>
                                <em><?php echo esc_html(number_format($job->written).' leads · '.$job->options->describe()); ?></em>
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>

                <div class="rl-export-foot">
                    <button type="button" class="rl-btn rl-btn-outline" id="rl-export-cancel">Cancel</button>
                    <button type="button" class="rl-btn rl-btn-primary" id="rl-export-run">Start export</button>
                    <a class="rl-btn rl-btn-primary" id="rl-export-download" hidden>Download CSV</a>
                </div>
            </aside>
        </div>
        <script>
        (function () {
            var cfg = <?php echo $config; ?>;
            var search = <?php echo wp_json_encode($search); ?>;

            var modal = document.getElementById('rl-export-modal');
            var form = document.getElementById('rl-export-form');
            var progress = document.getElementById('rl-export-progress');
            var statusText = document.getElementById('rl-export-status-text');
            var fill = document.getElementById('rl-export-bar-fill');
            var runBtn = document.getElementById('rl-export-run');
            var downloadBtn = document.getElementById('rl-export-download');
            var slider = document.getElementById('rl-export-limit');
            var amount = document.getElementById('rl-export-amount');
            var matched = document.getElementById('rl-export-matched');

            /*
             * The slider's stops are derived from how many leads actually match, so the scale
             * means something: on 40 matches it is "10, 20, 30, All", not nine dead positions
             * and an All at the end. The last stop is always All.
             */
            var stops = [0];

            function buildStops(total) {
                var candidates = [100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000];
                var usable = candidates.filter(function (n) { return n < total; });

                if (total <= 10) {
                    usable = [];
                } else if (usable.length === 0) {
                    // Small result sets get quarters rather than nothing to choose between.
                    usable = [Math.round(total / 4), Math.round(total / 2), Math.round(total * 3 / 4)]
                        .filter(function (n, i, a) { return n > 0 && a.indexOf(n) === i; });
                }

                stops = usable.concat([0]);           // 0 is "All", always last
                slider.max = String(stops.length - 1);
                slider.value = String(stops.length - 1);
                paintAmount();
            }

            function paintAmount() {
                var value = stops[Number(slider.value)];
                amount.textContent = value === 0 ? 'All' : 'Newest ' + value.toLocaleString();
            }

            function options() {
                var groups = [];
                form.querySelectorAll('[data-export-group]').forEach(function (box) {
                    if (box.checked) { groups.push(box.value); }
                });

                return {
                    groups: groups.join(','),
                    status: document.getElementById('rl-export-status').value,
                    source_type: document.getElementById('rl-export-source').value,
                    platform: document.getElementById('rl-export-platform').value,
                    audience: document.getElementById('rl-export-audience').value,
                    submission: document.getElementById('rl-export-submission').value,
                    from: document.getElementById('rl-export-from').value,
                    to: document.getElementById('rl-export-to').value,
                    include_deleted: document.getElementById('rl-export-deleted').checked ? '1' : '',
                    limit: String(stops[Number(slider.value)] || 0),
                    search: search
                };
            }

            function post(action, extra) {
                var body = new FormData();
                body.append('action', action);
                body.append('_wpnonce', cfg.nonce);

                var payload = Object.assign(options(), extra || {});
                Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });

                return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); });
            }

            var countTimer = null;

            function refreshCount() {
                window.clearTimeout(countTimer);
                countTimer = window.setTimeout(function () {
                    matched.textContent = 'Counting matching leads…';

                    post('rl_lead_export_count').then(function (res) {
                        if (!res.success) { matched.textContent = 'Could not count matching leads.'; return; }

                        var total = res.data.total;
                        matched.textContent = total.toLocaleString() + ' leads match these filters.';
                        buildStops(total);
                    }).catch(function () {
                        matched.textContent = 'Could not count matching leads.';
                    });
                }, 250);
            }

            function paint(state) {
                statusText.textContent = state.label;
                fill.style.width = state.percent + '%';

                if (state.phase === 'failed') { fill.classList.add('is-bad'); }
                if (state.phase === 'done') { fill.classList.add('is-ok'); }
            }

            function step(jobId) {
                post('rl_lead_export_step', { job: jobId }).then(function (res) {
                    if (!res.success) {
                        statusText.textContent = (res.data && res.data.message) || 'The export failed.';
                        fill.classList.add('is-bad');
                        return;
                    }

                    paint(res.data);

                    if (res.data.finished) {
                        if (res.data.phase === 'done') {
                            downloadBtn.href = cfg.downloadBase + '&job=' + encodeURIComponent(jobId);
                            downloadBtn.hidden = false;
                        }
                        return;
                    }

                    step(jobId);
                }).catch(function (e) {
                    statusText.textContent = 'Request failed: ' + e + '. Reopen the export to start again.';
                    fill.classList.add('is-bad');
                });
            }

            /*
             * `hidden` cannot be animated away — an element that is display:none on the frame the
             * class lands never transitions. So the panel is shown first, then opened on the next
             * frame, and on the way out it keeps its box until the slide has finished.
             */
            var closeTimer = null;

            function open() {
                window.clearTimeout(closeTimer);
                modal.hidden = false;
                window.requestAnimationFrame(function () { modal.classList.add('is-open'); });
                refreshCount();
            }

            function close() {
                modal.classList.remove('is-open');
                closeTimer = window.setTimeout(function () { modal.hidden = true; }, 260);
            }

            document.getElementById('rl-export-open').addEventListener('click', open);
            document.getElementById('rl-export-close').addEventListener('click', close);
            document.getElementById('rl-export-cancel').addEventListener('click', close);

            modal.addEventListener('click', function (e) {
                if (e.target === modal) { close(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.hidden) { close(); }
            });

            slider.addEventListener('input', paintAmount);

            form.addEventListener('change', function (e) {
                // The column choice changes the file's shape, not which rows match, so it is the
                // one control that does not need a recount.
                if (!e.target.hasAttribute('data-export-group')) { refreshCount(); }
            });

            runBtn.addEventListener('click', function () {
                runBtn.disabled = true;
                form.hidden = true;
                progress.hidden = false;
                statusText.textContent = 'Starting…';

                post('rl_lead_export_start').then(function (res) {
                    if (!res.success) {
                        statusText.textContent = (res.data && res.data.message) || 'The export could not be started.';
                        fill.classList.add('is-bad');
                        return;
                    }

                    paint(res.data);

                    if (res.data.finished) {
                        downloadBtn.href = cfg.downloadBase + '&job=' + encodeURIComponent(res.data.job);
                        downloadBtn.hidden = false;
                        return;
                    }

                    step(res.data.job);
                }).catch(function (e) {
                    statusText.textContent = 'Request failed: ' + e;
                    fill.classList.add('is-bad');
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * What the browser needs to paint one tick of the progress bar.
     *
     * @return array<string, mixed>
     */
    private function state(LeadExportJob $job): array
    {
        return [
            'job' => $job->id,
            'label' => $job->label(),
            'percent' => $job->percent(),
            'written' => $job->written,
            'total' => $job->total,
            'phase' => $job->phase,
            'finished' => $job->isFinished(),
        ];
    }

    /**
     * Every endpoint here reads the whole lead table. Both checks, on all three.
     */
    private function authorize(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You are not allowed to export leads.'], 403);
        }

        check_ajax_referer(self::NONCE, '_wpnonce');
    }

    /** @return array<string, mixed> */
    private function input(): array
    {
        return [
            'groups' => sanitize_text_field($_POST['groups'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'source_type' => sanitize_text_field($_POST['source_type'] ?? ''),
            'from' => sanitize_text_field($_POST['from'] ?? ''),
            'to' => sanitize_text_field($_POST['to'] ?? ''),
            'include_deleted' => sanitize_text_field($_POST['include_deleted'] ?? ''),
            'limit' => absint($_POST['limit'] ?? 0),
            'search' => sanitize_text_field($_POST['search'] ?? ''),
        ];
    }

    private function iconDownload(): string
    {
        return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
    }

    private function styles(): void
    {
        ?>
        <style>
            /*
             * A flyout off the right edge rather than a centred dialog.
             *
             * The form is long — eleven column groups, four filters and a slider — and a centred
             * box that tall either scrolls the page behind it or has to shrink its own contents.
             * Anchored full height, the head and the footer buttons stay put while only the
             * options scroll, and the leads list stays visible beside it, which is what someone
             * checks when they are deciding what to export.
             */
            .rl-export-scrim {
                position: fixed;
                inset: 0;
                z-index: 100050;
                background: rgba(9, 9, 11, 0);
                display: flex;
                justify-content: flex-end;
                transition: background 0.25s ease;
            }
            .rl-export-scrim[hidden] { display: none; }
            .rl-export-scrim.is-open { background: rgba(9, 9, 11, 0.45); }
            .rl-export-flyout {
                background: #ffffff;
                border-left: 1px solid #e4e4e7;
                box-shadow: -18px 0 50px rgba(9, 9, 11, 0.18);
                width: 100%;
                max-width: 520px;
                height: 100%;
                display: flex;
                flex-direction: column;
                transform: translateX(100%);
                transition: transform 0.25s ease;
            }
            .rl-export-scrim.is-open .rl-export-flyout { transform: translateX(0); }

            /* Only the options scroll; the title and the buttons are always reachable. */
            .rl-export-flyout .rl-export-body { overflow-y: auto; }
            .rl-export-flyout .rl-export-head,
            .rl-export-flyout .rl-export-recent,
            .rl-export-flyout .rl-export-foot { flex: 0 0 auto; }

            @media (prefers-reduced-motion: reduce) {
                .rl-export-scrim,
                .rl-export-flyout { transition: none; }
            }
            .rl-export-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 16px;
                padding: 20px 22px 14px;
                border-bottom: 1px solid #f4f4f5;
            }
            .rl-export-head h2 { margin: 0; font-size: 16px; font-weight: 600; color: #09090b; }
            .rl-export-sub { margin: 6px 0 0; font-size: 12px; color: #71717a; max-width: 46ch; }
            .rl-export-close {
                background: none; border: none; cursor: pointer;
                font-size: 22px; line-height: 1; color: #71717a; padding: 0 4px;
            }
            .rl-export-body { padding: 18px 22px; display: flex; flex-direction: column; gap: 16px; }
            .rl-export-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
            .rl-export-field { display: flex; flex-direction: column; gap: 6px; border: none; margin: 0; padding: 0; }
            .rl-export-field legend,
            .rl-export-label { font-size: 12px; font-weight: 600; color: #09090b; padding: 0; }
            .rl-export-label strong { font-weight: 600; color: #6b21a8; margin-left: 4px; }
            /*
             * One column, not two: the flyout trades width for height, and each group carries a
             * line of explanation that wraps to three lines in half of 476px.
             */
            .rl-export-groups {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
                margin-top: 8px;
            }
            .rl-export-check { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; }
            .rl-export-check.is-locked { opacity: 0.65; }
            .rl-export-check span { display: flex; flex-direction: column; }
            .rl-export-check strong { font-weight: 500; color: #09090b; }
            .rl-export-check em { font-style: normal; font-size: 11px; color: #71717a; }
            .rl-export-hint { margin: 0; font-size: 11px; color: #71717a; }
            .rl-export-flyout input[type="range"] { width: 100%; accent-color: #6b21a8; }
            .rl-export-status { margin: 0; font-size: 13px; font-weight: 500; color: #09090b; }
            .rl-export-bar {
                height: 8px;
                border-radius: 9999px;
                background: #f4f4f5;
                overflow: hidden;
            }
            .rl-export-bar-fill {
                height: 100%;
                width: 0;
                background: #6b21a8;
                transition: width 0.25s ease;
            }
            .rl-export-bar-fill.is-ok { background: #10b981; }
            .rl-export-bar-fill.is-bad { background: #dc2626; }
            .rl-export-recent {
                padding: 14px 22px;
                border-top: 1px solid #f4f4f5;
                display: flex;
                flex-direction: column;
                gap: 6px;
            }
            .rl-export-recent a { font-size: 12px; text-decoration: none; color: #6b21a8; }
            .rl-export-recent a em { display: block; font-style: normal; font-size: 11px; color: #71717a; }
            .rl-export-foot {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                padding: 14px 22px 18px;
                border-top: 1px solid #f4f4f5;
            }
            .rl-export-foot [hidden] { display: none; }

            @media screen and (max-width: 782px) {
                .rl-export-flyout { max-width: 100%; }
            }

            @media screen and (max-width: 600px) {
                .rl-export-row,
                .rl-export-groups { grid-template-columns: 1fr; }
            }
        </style>
        <?php
    }
}
