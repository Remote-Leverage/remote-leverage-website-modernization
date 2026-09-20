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
        if (! SyncEnvironment::syncEnabled()) {
            $this->renderSourceOnly($issuedCredentials);

            return;
        }

        $push = $this->pushJobs->active();
        $pull = $this->pullJobs->active();
        $running = $push !== null || $pull !== null;

        echo '<div class="wrap rl-admin-wrap rl-sync">';
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
        $targets = $this->configuredEnvironments(includeReadOnly: true);

        echo '<div class="rl-admin-header">';
        echo '<h1 class="rl-admin-title">Environment Sync</h1>';
        echo '<p class="rl-admin-subtitle">';
        printf(
            'This environment is <code>%s</code>. ',
            esc_html(SyncEnvironment::current()),
        );

        echo $targets === []
            ? '<strong>No remotes configured.</strong> Add the <code>*_SYNC_*</code> values to this '
                .'environment&rsquo;s <code>.env</code> to connect one.'
            : 'Connected to '.esc_html(implode(', ', $targets)).'.';

        echo ' Production can be pulled from, never pushed to.</p>';
        echo '</div>';
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

        echo '<div class="rl-card rl-sync-live">';
        printf('<p class="rl-card-title">%s</p>', esc_html($title));

        printf(
            '<div class="rl-sync-bar"><div id="%s-fill" class="rl-sync-bar-fill%s" style="width:%s"></div></div>',
            esc_attr($elementId),
            $percent === null ? ' is-indeterminate' : '',
            $percent === null ? '100%' : (int) $percent.'%',
        );

        printf(
            '<p class="rl-sync-status" id="%s">%s</p>',
            esc_attr($elementId),
            esc_html((string) $status['label']),
        );

        if (! $autostart) {
            echo '<p class="rl-hint">This transfer was left unfinished. '
                .'Resume it, or cancel it to start something else.</p>';
        }

        echo '<p class="rl-sync-actions">';

        if (! $autostart) {
            printf(
                '<button type="button" class="rl-btn rl-btn-primary" onclick="rlSyncResume_%s()">Resume</button> ',
                esc_attr($kind),
            );
        }

        $this->form('cancel_job', 'Cancel transfer', 'rl-btn rl-btn-outline', [
            'job_id' => (string) $status['id'],
        ], 'Cancel this transfer? Anything already written stays until you roll the session back.');

        echo '</p></div>';

        $this->progressScript((string) $status['id'], $action, $elementId, $kind, $autostart);
    }

    private function tabs(bool $running): void
    {
        // Production alone is enough to justify the tabs: you cannot push to it,
        // but you can pull from it, so hiding them would hide the only thing
        // this screen could do.
        if ($this->configuredEnvironments(includeReadOnly: true) === []) {
            return;
        }

        $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'push';
        $current = in_array($requested, ['push', 'pull', 'maintenance'], true) ? $requested : 'push';

        echo '<nav class="rl-tabs">';

        foreach (['push' => 'Push', 'pull' => 'Pull', 'maintenance' => 'Maintenance'] as $tab => $label) {
            printf(
                '<a href="%s" class="rl-tab%s">%s</a>',
                esc_url(admin_url('options-general.php?page='.self::SLUG.'&tab='.$tab)),
                $tab === $current ? ' rl-tab-active' : '',
                esc_html($label),
            );
        }

        echo '</nav>';

        if ($running) {
            echo '<div class="rl-card"><p class="rl-card-sub" style="margin:0;">A transfer is running. '
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
        echo '<form method="post" class="rl-card" '
            .'onsubmit="return confirm(\'This overwrites data on the target. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="push">';

        echo '<p class="rl-card-title">Push to a remote</p>';
        echo '<p class="rl-card-sub">Send this environment&rsquo;s data to a remote, overwriting what is there.</p>';

        $this->environmentSelect('target');
        $this->datasetChoices('datasets', true);
        $this->exclusionFields('excluded');

        echo '<p><button type="submit" class="rl-btn rl-btn-primary">Start push</button></p>';
        echo '</form>';
    }

    private function pullForm(): void
    {
        echo '<form method="post" class="rl-card" '
            .'onsubmit="return confirm(\'This overwrites data in THIS environment. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="pull">';

        echo '<p class="rl-card-title">Pull from a remote</p>';
        echo '<p class="rl-card-sub">Bring a remote&rsquo;s data into this environment, overwriting what is '
            .'here. Undoable from Recent transfers below.</p>';

        $this->environmentSelect('source');
        $this->datasetChoices('pull_datasets', false);
        $this->exclusionFields('pull_excluded');

        echo '<p><button type="submit" class="rl-btn rl-btn-primary">Start pull</button></p>';
        echo '</form>';
    }

    private function maintenanceForm(): void
    {
        echo '<form method="post" class="rl-card">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="purge">';

        echo '<p class="rl-card-title">Purge a dataset</p>';
        echo '<p class="rl-card-sub">Empty a dataset that is never copied between environments. '
            .'<strong>This cannot be undone</strong> — there is no rollback for a purge.</p>';

        echo '<div class="rl-field"><label class="rl-label">Dataset</label>'
            .'<select name="purge_dataset" class="rl-select">';

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

        echo '</select></div>';

        echo '<div class="rl-field"><label class="rl-label">Where</label>'
            .'<select name="purge_where" class="rl-select">';
        printf('<option value="local">this environment (%s)</option>', esc_html(SyncEnvironment::current()));

        foreach ($this->configuredEnvironments() as $env) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $env));
        }

        echo '</select></div>';

        echo '<div class="rl-field"><label class="rl-label">Confirm</label>'
            .'<input type="text" name="purge_confirm" class="rl-input" autocomplete="off" '
            .'placeholder="type the environment name">'
            .'<span class="rl-hint">Naming the environment is what proves which one you meant. '
            .'A mismatch deletes nothing.</span></div>';

        echo '<p><button type="submit" class="rl-btn rl-btn-destructive">Purge</button></p>';
        echo '</form>';
    }

    /**
     * Production is offered as a source and withheld everywhere else.
     *
     * It is read-only by ability, and TransferPusher refuses it by host as well,
     * so listing it as a target would only ever produce an error. Leaving it out
     * of the dropdown is the honest version of the same rule.
     */
    private function environmentSelect(string $name): void
    {
        $isSource = $name === 'source';

        printf('<div class="rl-field"><label class="rl-label">%s</label>'
            .'<select name="%s" class="rl-select">',
            esc_html($isSource ? 'Source' : 'Target'),
            esc_attr($name),
        );

        foreach ($this->configuredEnvironments($isSource) as $env) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $env));
        }

        echo '</select></div>';
    }

    private function datasetChoices(string $field, bool $withClean): void
    {
        echo '<div class="rl-field"><span class="rl-label">Datasets</span><ul class="rl-choices">';

        foreach ($this->registry->transferable() as $dataset) {
            $this->datasetChoice($dataset, $field, $withClean);
        }

        echo '</ul>';
        echo '<span class="rl-hint">Referrals, scheduling and users are never transferred. '
            .'Leads are, and they carry real contact details: the target\'s lead tables are '
            .'emptied and replaced, never merged. '
            .'Content without media leaves posts pointing at files the other side does not have.</span></div>';
    }

    private function datasetChoice(Dataset $dataset, string $field, bool $withClean): void
    {
        printf(
            '<li class="rl-choice"><label><input type="checkbox" name="%s[]" value="%s"%s> %s</label>',
            esc_attr($field),
            esc_attr($dataset->key),
            $dataset->defaultSelected ? ' checked' : '',
            esc_html($dataset->label),
        );

        if ($withClean) {
            printf(
                '<label class="rl-choice-aside"><input type="checkbox" name="clean[]" value="%s"> '
                .'empty on target first</label>',
                esc_attr($dataset->key),
            );
        }

        printf('<span class="rl-hint">%s</span></li>', esc_html($dataset->description));
    }

    private function exclusionFields(string $prefix): void
    {
        printf(
            '<div class="rl-field"><label class="rl-label">Skip post types</label>'
            .'<input type="text" name="%s_post_types" class="rl-input" placeholder="case_study, page">'
            .'<span class="rl-hint">Comma separated. Optional.</span></div>',
            esc_attr($prefix),
        );

        printf(
            '<div class="rl-field"><label class="rl-label">Skip post IDs</label>'
            .'<input type="text" name="%s_post_ids" class="rl-input" placeholder="7, 12">'
            .'<span class="rl-hint">Comma separated. Optional.</span></div>',
            esc_attr($prefix),
        );
    }

    private function history(): void
    {
        $sessions = $this->sessions->recent(8);

        echo '<p class="rl-card-title" style="margin-top:24px;">Recent transfers</p>';
        echo '<p class="rl-card-sub">Transfers imported <em>into</em> this environment. '
            .'A push you send from here is recorded on the target, not locally.</p>';

        if ($sessions === []) {
            echo '<div class="rl-table-container"><p class="rl-empty">Nothing imported here yet.</p></div>';

            return;
        }

        echo '<div class="rl-table-container"><table class="rl-table"><thead><tr>'
            .'<th>When</th><th>Datasets</th><th>Rows</th><th>State</th><th></th>'
            .'</tr></thead><tbody>';

        foreach ($sessions as $session) {
            $status = $session->toStatusArray();

            echo '<tr><td>';
            printf(
                '%s<br><span class="rl-mono">%s</span>',
                esc_html($this->when((int) $status['updated_at'])),
                esc_html((string) $status['id']),
            );
            echo '</td>';

            printf('<td>%s</td>', esc_html(implode(', ', $status['datasets'])));
            printf(
                '<td>%d%s</td>',
                (int) $status['total_rows'],
                (int) $status['remapped_attachments'] > 0
                    ? '<span class="rl-hint">'.(int) $status['remapped_attachments'].' remapped</span>'
                    : '',
            );
            printf('<td>%s</td>', $this->stateBadge((string) $status['state'], $status['error']));

            echo '<td>';
            $this->form('rollback_local', 'Roll back', 'rl-btn rl-btn-outline rl-btn-sm', [
                'session_id' => (string) $status['id'],
            ], 'Revert everything this transfer wrote here?');
            echo '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private function stateBadge(string $state, ?string $error): string
    {
        $class = match ($state) {
            'complete' => 'rl-badge-ok',
            'failed' => 'rl-badge-bad',
            default => 'rl-badge-busy',
        };

        $label = $state === 'importing' || $state === 'open' ? 'unfinished' : $state;

        $html = sprintf('<span class="rl-badge %s">%s</span>', esc_attr($class), esc_html($label));

        return $error ? $html.'<span class="rl-hint">'.esc_html($error).'</span>' : $html;
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
    /**
     * The whole screen in an environment that cannot run a transfer — today,
     * production.
     *
     * Deliberately not the normal screen with the controls disabled. There is
     * nothing to push, nothing to pull and no history to show here, and a row of
     * greyed-out buttons invites someone to work out how to un-grey them. What
     * is left is a credentials card and a paragraph saying why it is the only
     * thing here.
     *
     * @param  array{user_login: string, password: string}|null  $issuedCredentials
     */
    private function renderSourceOnly(?array $issuedCredentials): void
    {
        echo '<div class="wrap rl-admin-wrap rl-sync">';
        $this->styles();

        echo '<div class="rl-admin-header">';
        echo '<h1 class="rl-admin-title">Environment Sync</h1>';
        printf(
            '<p class="rl-admin-subtitle">This environment is <code>%s</code>, so it can be '
                .'<strong>read from and never written to</strong>. Transfers are started on the '
                .'environment receiving them; this page exists only to issue the credential they '
                .'authenticate with.</p>',
            esc_html(SyncEnvironment::current()),
        );
        echo '</div>';

        if ($issuedCredentials !== null) {
            $this->issuedCredentials($issuedCredentials);
        }

        $this->credentials();

        echo '<p class="rl-hint">A credential issued here can export content, list media and read '
            .'an uploaded file. It cannot import, purge or roll anything back &mdash; those refuse '
            .'on this environment whoever is calling. Lead rows are exported with their personal '
            .'data intact, so revoke this when you are done pulling.</p>';

        echo '</div>';
    }

    private function credentials(): void
    {
        $status = $this->provisioner->status();
        $ready = $status['exists'] && $status['has_capability'] && $status['password_count'] > 0;

        printf('<details class="rl-card rl-sync-details"%s><summary>%s</summary>',
            $ready ? '' : ' open',
            $ready
                ? 'Sync credentials &mdash; ready'
                : 'Sync credentials &mdash; <strong>setup needed</strong>',
        );

        echo '<p class="rl-hint">The <code>sync-service</code> user this environment accepts '
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
                '<p class="rl-badge rl-badge-bad">%s</p>',
                esc_html((string) $status['unavailable_reason']),
            );
            echo '</details>';

            return;
        }

        echo '<p>';
        $this->form(
            'provision',
            $status['password_count'] > 0 ? 'Regenerate credentials' : 'Generate credentials',
            'rl-btn rl-btn-primary',
            [],
            $status['password_count'] > 0
                ? 'This revokes the current password. Any environment using it stops syncing until updated. Continue?'
                : null,
        );

        if ($status['exists'] && ($status['has_capability'] || $status['password_count'] > 0)) {
            echo ' ';
            $this->form('revoke', 'Revoke', 'rl-btn rl-btn-outline', [], 'Revoke the password and capability?');
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

        echo '<div class="rl-card">';
        echo '<p class="rl-card-title">Credentials created</p>';
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
        echo '<form method="post" class="rl-inline"';

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
    /**
     * Remotes this environment has credentials for.
     *
     * @param  bool  $includeReadOnly  true when the caller is choosing something
     *                                 to read from. Production is configured
     *                                 like any other remote but can only ever be
     *                                 a source, so it is excluded by default and
     *                                 opted into here.
     * @return array<int, string>
     */
    private function configuredEnvironments(bool $includeReadOnly = false): array
    {
        $configured = array_keys(array_filter(
            (array) config('rl-sync.environments', []),
            fn ($env) => ! empty($env['url']),
        ));

        return array_values(array_filter(
            $configured,
            fn ($env) => $includeReadOnly || $env !== SyncEnvironment::PRODUCTION,
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

    /**
     * Only what the shared tokens do not already cover: the progress bar, and
     * the couple of places this screen needs to sit differently.
     */
    private function styles(): void
    {
        ?>
        <style>
        .rl-sync-live { border-left: 3px solid #18181b; }
        .rl-sync-bar {
            background: #f4f4f5; border-radius: 9999px; height: 8px; overflow: hidden; margin: 12px 0 0;
        }
        .rl-sync-bar-fill {
            background: #18181b; height: 100%; width: 0; border-radius: 9999px;
            transition: width .3s ease;
        }
        .rl-sync-bar-fill.is-ok { background: #16a34a; }
        .rl-sync-bar-fill.is-bad { background: #dc2626; }
        .rl-sync-bar-fill.is-indeterminate {
            background: linear-gradient(90deg, #e4e4e7 25%, #18181b 50%, #e4e4e7 75%);
            background-size: 200% 100%; animation: rl-sync-slide 1.2s linear infinite;
        }
        @keyframes rl-sync-slide { from { background-position: 200% 0; } to { background-position: -200% 0; } }
        .rl-sync-status { font-size: 13px; font-weight: 500; margin: 10px 0 0; color: #09090b; }
        .rl-sync-actions { margin: 14px 0 0; display: flex; gap: 8px; align-items: center; }
        .rl-sync-details summary {
            cursor: pointer; font-size: 14px; font-weight: 600; list-style: none;
        }
        .rl-sync-details summary::-webkit-details-marker { display: none; }
        .rl-sync-details summary::before { content: "\25B8 "; color: #71717a; }
        .rl-sync-details[open] summary::before { content: "\25BE "; }
        .rl-sync-code {
            width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px;
            border: 1px solid #e4e4e7; border-radius: 8px; padding: 10px; background: #fafafa;
        }
        </style>
        <?php
    }
}
