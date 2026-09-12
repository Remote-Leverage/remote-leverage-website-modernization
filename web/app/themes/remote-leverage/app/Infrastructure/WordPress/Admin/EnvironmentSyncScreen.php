<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\DatasetPurger;
use App\Domains\Sync\Transfer\Pull\PullJobStore;
use App\Domains\Sync\Transfer\Push\PushJobStore;
use App\Domains\Sync\Transfer\SessionStore;

/**
 * Renders Settings → Environment Sync.
 *
 * Split out from EnvironmentSyncAdmin, which had grown to do hook wiring,
 * request handling and ~450 lines of markup at once. The controller decides
 * what happened; this decides what it looks like.
 *
 * Layout follows what the screen is actually for: at any moment you either have
 * a transfer running (in which case that is the only thing you care about), or
 * you are choosing one to start. So a running transfer takes the top of the page
 * as a panel in normal page flow — deliberately NOT a .notice, because
 * WordPress hoists those out of the content area and they end up in the admin
 * notice stack where a live progress bar cannot be watched. Everything else is
 * behind tabs, and setup is folded away once it is done.
 */
class EnvironmentSyncScreen
{
    public const SLUG = EnvironmentSyncAdmin::SLUG;

    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly SyncCredentialProvisioner $provisioner,
        private readonly SessionStore $sessions,
        private readonly PushJobStore $pushJobs,
        private readonly PullJobStore $pullJobs,
        private readonly DatasetPurger $purger,
    ) {}

    /**
     * @param  array{user_login: string, password: string}|null  $issuedCredentials
     */
    public function render(?array $issuedCredentials, bool $autostartPush, bool $autostartPull): void
    {
        $push = $this->pushJobs->active();
        $pull = $this->pullJobs->active();
        $running = $push !== null || $pull !== null;

        echo '<div class="wrap rl-sync">';
        $this->styles();
        $this->header();

        if ($issuedCredentials !== null) {
            $this->issuedCredentials($issuedCredentials);
        }

        if ($push !== null) {
            $this->progressPanel(
                $push->toStatusArray(),
                'Pushing to '.$push->target,
                'rl_sync_push_step',
                'push',
                $autostartPush,
            );
        }

        if ($pull !== null) {
            $this->progressPanel(
                $pull->toStatusArray(),
                'Pulling from '.$pull->source,
                'rl_sync_pull_step',
                'pull',
                $autostartPull,
            );
        }

        $this->tabs($running);
        $this->history();
        $this->credentials();

        echo '</div>';
    }

    /**
     * A short, honest summary of where you are and what this screen talks to.
     */
    private function header(): void
    {
        $targets = $this->configuredEnvironments();

        echo '<h1>Environment Sync</h1>';
        echo '<p class="rl-sync-sub">';
        printf(
            'This environment is <code>%s</code>. ',
            esc_html(SyncEnvironment::current()),
        );

        echo $targets === []
            ? '<strong>No remotes configured.</strong> Add the <code>*_SYNC_*</code> values to this '
                .'environment&rsquo;s <code>.env</code> to connect one.'
            : 'Connected to '.esc_html(implode(', ', $targets)).'.';

        echo ' Never available in production.</p>';
    }

    /**
     * The running transfer. Plain page markup, not a notice — see the class
     * docblock for why that distinction matters here.
     *
     * @param  array<string, mixed>  $status
     */
    private function progressPanel(
        array $status,
        string $title,
        string $action,
        string $kind,
        bool $autostart,
    ): void {
        $percent = $status['percent'];
        $elementId = 'rl-sync-'.$kind;

        echo '<div class="rl-sync-panel">';
        printf('<h2>%s</h2>', esc_html($title));

        printf(
            '<div class="rl-sync-bar"><div id="%s-fill" class="rl-sync-bar-fill%s" style="width:%s"></div></div>',
            esc_attr($elementId),
            $percent === null ? ' is-indeterminate' : '',
            $percent === null ? '100%' : (int) $percent.'%',
        );

        printf(
            '<p class="rl-sync-status"><span id="%s">%s</span></p>',
            esc_attr($elementId),
            esc_html((string) $status['label']),
        );

        if (! $autostart) {
            echo '<p class="rl-sync-hint">This transfer was left unfinished. '
                .'Resume it, or cancel it to start something else.</p>';
        }

        echo '<p class="rl-sync-actions">';

        if (! $autostart) {
            printf(
                '<button type="button" class="button button-primary" onclick="rlSyncResume_%s()">Resume</button> ',
                esc_attr($kind),
            );
        }

        $this->form('cancel_job', 'Cancel transfer', 'button', [
            'job_id' => (string) $status['id'],
        ], 'Cancel this transfer? Anything already written stays until you roll the session back.');

        echo '</p></div>';

        $this->progressScript((string) $status['id'], $action, $elementId, $kind, $autostart);
    }

    private function tabs(bool $running): void
    {
        if ($this->configuredEnvironments() === []) {
            return;
        }

        $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'push';
        $current = in_array($requested, ['push', 'pull', 'maintenance'], true) ? $requested : 'push';

        echo '<h2 class="nav-tab-wrapper rl-sync-tabs">';

        foreach (['push' => 'Push', 'pull' => 'Pull', 'maintenance' => 'Maintenance'] as $tab => $label) {
            printf(
                '<a href="%s" class="nav-tab%s">%s</a>',
                esc_url(admin_url('options-general.php?page='.self::SLUG.'&tab='.$tab)),
                $tab === $current ? ' nav-tab-active' : '',
                esc_html($label),
            );
        }

        echo '</h2>';

        if ($running) {
            echo '<div class="rl-sync-panel rl-sync-panel--muted"><p>A transfer is running. '
                .'Cancel it above before starting another.</p></div>';

            return;
        }

        match ($current) {
            'pull' => $this->pullForm(),
            'maintenance' => $this->maintenanceForm(),
            default => $this->pushForm(),
        };
    }

    private function pushForm(): void
    {
        echo '<form method="post" class="rl-sync-form" '
            .'onsubmit="return confirm(\'This overwrites data on the target. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="push">';

        echo '<p class="rl-sync-lede">Send this environment&rsquo;s data to a remote.</p>';

        $this->environmentSelect('target');
        $this->datasetChoices('datasets', true);
        $this->exclusionFields('excluded');

        echo '<p><button type="submit" class="button button-primary">Start push</button></p>';
        echo '</form>';
    }

    private function pullForm(): void
    {
        echo '<form method="post" class="rl-sync-form" '
            .'onsubmit="return confirm(\'This overwrites data in THIS environment. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="pull">';

        echo '<p class="rl-sync-lede">Bring a remote&rsquo;s data into this environment, '
            .'overwriting what is here. Undoable from Recent transfers.</p>';

        $this->environmentSelect('source');
        $this->datasetChoices('pull_datasets', false);
        $this->exclusionFields('pull_excluded');

        echo '<p><button type="submit" class="button button-primary">Start pull</button></p>';
        echo '</form>';
    }

    private function maintenanceForm(): void
    {
        echo '<form method="post" class="rl-sync-form">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="purge">';

        echo '<p class="rl-sync-lede">Empty a dataset that is never copied between environments. '
            .'<strong>This cannot be undone</strong> — there is no rollback for a purge.</p>';

        echo '<p><label class="rl-sync-label">Dataset</label><select name="purge_dataset">';

        foreach ($this->registry->purgeable() as $dataset) {
            $counts = $this->purger->preview($dataset->key);
            $total = array_sum(array_filter($counts, fn (int $c) => $c >= 0));
            printf(
                '<option value="%s">%s &mdash; %d rows here</option>',
                esc_attr($dataset->key),
                esc_html($dataset->label),
                $total,
            );
        }

        echo '</select></p>';

        echo '<p><label class="rl-sync-label">Where</label><select name="purge_where">';
        printf('<option value="local">this environment (%s)</option>', esc_html(SyncEnvironment::current()));

        foreach ($this->configuredEnvironments() as $env) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $env));
        }

        echo '</select></p>';

        echo '<p><label class="rl-sync-label">Confirm</label>'
            .'<input type="text" name="purge_confirm" class="regular-text" autocomplete="off" '
            .'placeholder="type the environment name">'
            .'<span class="rl-sync-hint">Naming the environment is what proves which one you meant. '
            .'A mismatch deletes nothing.</span></p>';

        echo '<p><button type="submit" class="button button-link-delete">Purge</button></p>';
        echo '</form>';
    }

    private function environmentSelect(string $name): void
    {
        printf('<p><label class="rl-sync-label">%s</label><select name="%s">',
            esc_html($name === 'source' ? 'Source' : 'Target'),
            esc_attr($name),
        );

        foreach ($this->configuredEnvironments() as $env) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $env));
        }

        echo '</select></p>';
    }

    private function datasetChoices(string $field, bool $withClean): void
    {
        echo '<p class="rl-sync-label">Datasets</p><ul class="rl-sync-datasets">';

        foreach ($this->registry->transferable() as $dataset) {
            $this->datasetChoice($dataset, $field, $withClean);
        }

        echo '</ul>';
        echo '<p class="rl-sync-hint">Leads, referrals, scheduling and users are never transferred. '
            .'Content without media leaves posts pointing at files the other side does not have.</p>';
    }

    private function datasetChoice(Dataset $dataset, string $field, bool $withClean): void
    {
        printf(
            '<li><label><input type="checkbox" name="%s[]" value="%s"%s> <strong>%s</strong></label>',
            esc_attr($field),
            esc_attr($dataset->key),
            $dataset->defaultSelected ? ' checked' : '',
            esc_html($dataset->label),
        );

        if ($withClean) {
            printf(
                ' <label class="rl-sync-clean"><input type="checkbox" name="clean[]" value="%s"> '
                .'empty on target first</label>',
                esc_attr($dataset->key),
            );
        }

        printf('<span class="rl-sync-hint">%s</span></li>', esc_html($dataset->description));
    }

    private function exclusionFields(string $prefix): void
    {
        printf(
            '<p><label class="rl-sync-label">Skip post types</label>'
            .'<input type="text" name="%s_post_types" class="regular-text" placeholder="case_study, page">'
            .'<span class="rl-sync-hint">Comma separated. Optional.</span></p>',
            esc_attr($prefix),
        );

        printf(
            '<p><label class="rl-sync-label">Skip post IDs</label>'
            .'<input type="text" name="%s_post_ids" class="regular-text" placeholder="7, 12">'
            .'<span class="rl-sync-hint">Comma separated. Optional.</span></p>',
            esc_attr($prefix),
        );
    }

    private function history(): void
    {
        $sessions = $this->sessions->recent(8);

        echo '<h2>Recent transfers</h2>';

        if ($sessions === []) {
            echo '<p class="rl-sync-hint">Nothing has been imported into this environment yet. '
                .'A push you send from here is recorded on the target, not locally.</p>';

            return;
        }

        echo '<table class="widefat striped rl-sync-history"><thead><tr>'
            .'<th>When</th><th>Datasets</th><th>Rows</th><th>State</th><th></th>'
            .'</tr></thead><tbody>';

        foreach ($sessions as $session) {
            $status = $session->toStatusArray();

            echo '<tr><td>';
            printf(
                '%s<br><code class="rl-sync-id">%s</code>',
                esc_html($this->when((int) $status['updated_at'])),
                esc_html((string) $status['id']),
            );
            echo '</td>';

            printf('<td>%s</td>', esc_html(implode(', ', $status['datasets'])));
            printf(
                '<td>%d%s</td>',
                (int) $status['total_rows'],
                (int) $status['remapped_attachments'] > 0
                    ? ' <span class="rl-sync-hint">'.(int) $status['remapped_attachments'].' remapped</span>'
                    : '',
            );
            printf('<td>%s</td>', $this->stateBadge((string) $status['state'], $status['error']));

            echo '<td>';
            $this->form('rollback_local', 'Roll back', 'button button-small', [
                'session_id' => (string) $status['id'],
            ], 'Revert everything this transfer wrote here?');
            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function stateBadge(string $state, ?string $error): string
    {
        $class = match ($state) {
            'complete' => 'is-ok',
            'failed' => 'is-bad',
            default => 'is-busy',
        };

        $label = $state === 'importing' || $state === 'open' ? 'unfinished' : $state;

        $html = sprintf('<span class="rl-sync-badge %s">%s</span>', esc_attr($class), esc_html($label));

        return $error ? $html.'<br><span class="rl-sync-hint">'.esc_html($error).'</span>' : $html;
    }

    private function when(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '—';
        }

        return sprintf(
            '%s ago',
            human_time_diff($timestamp, time()),
        );
    }

    /**
     * Setup, folded away once it is done — it is a one-time step, not something
     * you come to this screen for.
     */
    private function credentials(): void
    {
        $status = $this->provisioner->status();
        $ready = $status['exists'] && $status['has_capability'] && $status['password_count'] > 0;

        printf('<details class="rl-sync-details"%s><summary>%s</summary>',
            $ready ? '' : ' open',
            $ready
                ? 'Sync credentials &mdash; ready'
                : 'Sync credentials &mdash; <strong>setup needed</strong>',
        );

        echo '<p class="rl-sync-hint">The <code>sync-service</code> user this environment accepts '
            .'sync calls as. Creating it here does what <code>wp user create</code> and '
            .'<code>wp acorn rl:sync:grant</code> do, so no shell access is needed.</p>';

        printf(
            '<p>User: %s &nbsp;&middot;&nbsp; Capability: %s &nbsp;&middot;&nbsp; Passwords: %d</p>',
            $status['exists'] ? 'yes' : 'no',
            $status['has_capability'] ? 'yes' : 'no',
            (int) $status['password_count'],
        );

        if (! $status['available']) {
            printf(
                '<p class="rl-sync-bad">%s</p>',
                esc_html((string) $status['unavailable_reason']),
            );
            echo '</details>';

            return;
        }

        echo '<p>';
        $this->form(
            'provision',
            $status['password_count'] > 0 ? 'Regenerate credentials' : 'Generate credentials',
            'button',
            [],
            $status['password_count'] > 0
                ? 'This revokes the current password. Any environment using it stops syncing until updated. Continue?'
                : null,
        );

        if ($status['exists'] && ($status['has_capability'] || $status['password_count'] > 0)) {
            echo ' ';
            $this->form('revoke', 'Revoke', 'button', [], 'Revoke the password and capability?');
        }

        echo '</p></details>';
    }

    /**
     * @param  array{user_login: string, password: string}  $issued
     */
    private function issuedCredentials(array $issued): void
    {
        $lines = sprintf(
            "SYNC_URL=%s\nSYNC_USER=%s\nSYNC_APP_PASSWORD=%s",
            home_url(),
            $issued['user_login'],
            $issued['password'],
        );

        echo '<div class="rl-sync-panel">';
        echo '<h2>Credentials created</h2>';
        echo '<p>Shown once, and never retrievable again. Add these to the <em>other</em> '
            .'environment&rsquo;s <code>.env</code>, prefixed for this one '
            .'(e.g. <code>STAGING_SYNC_URL</code>):</p>';
        printf(
            '<textarea readonly rows="3" class="rl-sync-code" onclick="this.select()">%s</textarea>',
            esc_textarea($lines),
        );
        echo '</div>';
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function form(string $action, string $label, string $class, array $fields, ?string $confirm = null): void
    {
        echo '<form method="post" class="rl-sync-inline"';

        if ($confirm !== null) {
            printf(' onsubmit="return confirm(%s);"', esc_attr(wp_json_encode($confirm)));
        }

        echo '>';
        wp_nonce_field(self::SLUG);
        printf('<input type="hidden" name="rl_sync_action" value="%s">', esc_attr($action));

        foreach ($fields as $name => $value) {
            printf('<input type="hidden" name="%s" value="%s">', esc_attr($name), esc_attr($value));
        }

        printf('<button type="submit" class="%s">%s</button>', esc_attr($class), esc_html($label));
        echo '</form>';
    }

    /**
     * @return array<int, string>
     */
    private function configuredEnvironments(): array
    {
        return array_keys(array_filter(
            (array) config('rl-sync.environments', []),
            fn ($env) => ! empty($env['url']),
        ));
    }

    private function progressScript(
        string $jobId,
        string $action,
        string $elementId,
        string $kind,
        bool $autostart,
    ): void {
        $payload = wp_json_encode([
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::SLUG),
            'job' => $jobId,
            'action' => $action,
            'el' => $elementId,
            'autostart' => $autostart,
        ]);

        ?>
        <script>
        (function () {
            var cfg = <?php echo $payload; ?>;
            var out = document.getElementById(cfg.el);
            var fill = document.getElementById(cfg.el + '-fill');

            function paint(s) {
                out.textContent = s.label;

                if (s.percent === null) {
                    fill.classList.add('is-indeterminate');
                    fill.style.width = '100%';
                } else {
                    fill.classList.remove('is-indeterminate');
                    fill.style.width = s.percent + '%';
                    out.textContent = s.label + '  ·  ' + s.percent + '%';
                }
            }

            function step() {
                var body = new FormData();
                body.append('action', cfg.action);
                body.append('_wpnonce', cfg.nonce);
                body.append('job', cfg.job);

                fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.success) {
                            out.innerHTML = '<strong>Failed:</strong> ' +
                                ((res.data && res.data.message) || 'unknown error');
                            fill.classList.add('is-bad');
                            return;
                        }

                        paint(res.data);

                        if (res.data.finished) {
                            if (res.data.phase === 'done') {
                                fill.style.width = '100%';
                                fill.classList.add('is-ok');
                            } else {
                                fill.classList.add('is-bad');
                            }

                            setTimeout(function () { window.location.reload(); }, 1200);
                            return;
                        }

                        step();
                    })
                    .catch(function (e) {
                        out.innerHTML = '<strong>Request failed:</strong> ' + e +
                            '<br>Reload and resume to continue from where it stopped.';
                        fill.classList.add('is-bad');
                    });
            }

            window['rlSyncResume_' + <?php echo wp_json_encode($kind); ?>] = step;

            if (cfg.autostart) { step(); }
        })();
        </script>
        <?php
    }

    private function styles(): void
    {
        ?>
        <style>
        .rl-sync .rl-sync-sub { color:#50575e; max-width:60rem; }
        .rl-sync-panel { background:#fff; border:1px solid #c3c4c7; border-left:4px solid #2271b1;
            padding:.75rem 1rem 1rem; margin:1rem 0; max-width:60rem; }
        .rl-sync-panel--muted { border-left-color:#dba617; }
        .rl-sync-panel h2 { margin-top:.25rem; }
        .rl-sync-bar { background:#f0f0f1; border-radius:3px; height:1.25rem; overflow:hidden; }
        .rl-sync-bar-fill { background:#2271b1; height:100%; width:0; transition:width .3s ease; }
        .rl-sync-bar-fill.is-ok { background:#008a20; }
        .rl-sync-bar-fill.is-bad { background:#d63638; }
        .rl-sync-bar-fill.is-indeterminate { background:linear-gradient(90deg,#c3c4c7 25%,#2271b1 50%,#c3c4c7 75%);
            background-size:200% 100%; animation:rl-sync-slide 1.2s linear infinite; }
        @keyframes rl-sync-slide { from { background-position:200% 0; } to { background-position:-200% 0; } }
        .rl-sync-status { font-weight:600; margin:.6rem 0 .2rem; }
        .rl-sync-actions { margin-bottom:0; }
        .rl-sync-hint { color:#646970; font-size:12px; display:block; margin-top:.15rem; }
        .rl-sync-lede { max-width:50rem; }
        .rl-sync-form { background:#fff; border:1px solid #c3c4c7; border-top:0;
            padding:1rem 1.25rem; max-width:60rem; }
        .rl-sync-label { display:block; font-weight:600; margin-bottom:.25rem; }
        .rl-sync-datasets { margin:0 0 .5rem; }
        .rl-sync-datasets li { margin:0 0 .6rem; }
        .rl-sync-clean { color:#646970; font-size:12px; margin-left:.5rem; }
        .rl-sync-inline { display:inline; }
        .rl-sync-history .rl-sync-id { font-size:11px; color:#646970; user-select:all; }
        .rl-sync-badge { border-radius:9px; padding:.1rem .5rem; font-size:11px; font-weight:600; }
        .rl-sync-badge.is-ok { background:#edfaef; color:#00622b; }
        .rl-sync-badge.is-bad { background:#fcf0f1; color:#8a2424; }
        .rl-sync-badge.is-busy { background:#fcf9e8; color:#8a6116; }
        .rl-sync-details { margin:1.5rem 0; max-width:60rem; }
        .rl-sync-details summary { cursor:pointer; font-weight:600; padding:.4rem 0; }
        .rl-sync-code { width:100%; font-family:monospace; }
        .rl-sync-bad { color:#d63638; }
        .rl-sync-tabs { margin-bottom:0; }
        </style>
        <?php
    }
}
