<?php

declare(strict_types=1);

namespace App\Infrastructure\WordPress\Admin;

use App\Domains\Sync\Datasets\Dataset;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Provisioning\SyncCredentialProvisioner;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncEnvironment;
use App\Domains\Sync\Transfer\DatasetPurger;
use App\Domains\Sync\Transfer\Pull\PullJobRunner;
use App\Domains\Sync\Transfer\Pull\PullJobStore;
use App\Domains\Sync\Transfer\Push\PushJobRunner;
use App\Domains\Sync\Transfer\Push\PushJobStore;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Throwable;

/**
 * Settings → Environment Sync.
 *
 * Credentials, a push form, and the history of transfers this environment has
 * received. The credential has to exist before any transfer can run, which is
 * why provisioning sits above the transfer form rather than beside it.
 *
 * The screen is never registered in production — see register(). That is the
 * outermost of the four gates in the design doc; the abilities enforce their
 * own independently, so removing this one would still not make production
 * reachable.
 */
class EnvironmentSyncAdmin
{
    public const SLUG = 'rl-environment-sync';

    /**
     * Transient holding the one-time plaintext application password between the
     * provisioning request and the redirect that displays it. Keyed per user so
     * two admins provisioning at once can't read each other's.
     */
    private const PASSWORD_TRANSIENT = 'rl_env_sync_password_';

    public function __construct(
        private readonly SyncCredentialProvisioner $provisioner,
        private readonly DatasetRegistry $registry,
        private readonly SessionStore $sessions,
        private readonly PushJobStore $jobs,
        private readonly PushJobRunner $runner,
        private readonly UndoLogFactory $undoLogs,
        private readonly PullJobStore $pullJobs,
        private readonly PullJobRunner $pullRunner,
        private readonly DatasetPurger $purger,
    ) {}

    public function register(): void
    {
        if (! SyncEnvironment::syncEnabled()) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'handleActions']);
        add_action('wp_ajax_rl_sync_push_step', [$this, 'handlePushStep']);
        add_action('wp_ajax_rl_sync_pull_step', [$this, 'handlePullStep']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            page_title: 'Environment Sync',
            menu_title: 'Environment Sync',
            capability: 'manage_options',
            menu_slug: self::SLUG,
            callback: [$this, 'render'],
        );
    }

    public function handleActions(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['rl_sync_action'] ?? ''));

        if ($action === '') {
            return;
        }

        check_admin_referer(self::SLUG);

        try {
            match ($action) {
                'provision' => $this->handleProvision(),
                'revoke' => $this->handleRevoke(),
                'push' => $this->handlePush(),
                'rollback' => $this->handleRollback(),
                'rollback_local' => $this->handleRollbackLocal(),
                'pull' => $this->handlePull(),
                'purge' => $this->handlePurge(),
                'cancel_job' => $this->handleCancelJob(),
                default => null,
            };
        } catch (Throwable $e) {
            set_transient($this->noticeKey('error'), $e->getMessage(), 60);
        }

        wp_safe_redirect(admin_url('options-general.php?page='.self::SLUG));
        exit;
    }

    private function handleProvision(): void
    {
        $result = $this->provisioner->provision();

        set_transient(
            self::PASSWORD_TRANSIENT.get_current_user_id(),
            $result,
            5 * MINUTE_IN_SECONDS,
        );
    }

    private function handleRevoke(): void
    {
        $this->provisioner->revoke();
        set_transient($this->noticeKey('success'), 'Sync credentials revoked.', 60);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('You do not have permission to access this page.');
        }

        $status = $this->provisioner->status();
        $issued = get_transient(self::PASSWORD_TRANSIENT.get_current_user_id());
        delete_transient(self::PASSWORD_TRANSIENT.get_current_user_id());

        echo '<div class="wrap">';
        echo '<h1>Environment Sync</h1>';

        $this->renderNotices();
        $this->renderEnvironmentRow();

        if (is_array($issued)) {
            $this->renderIssuedPassword($issued);
        }

        $this->renderCredentialSection($status);
        $this->renderRunningJob();
        $this->renderRunningPull();
        $this->renderTransferSection();
        $this->renderPullSection();
        $this->renderMaintenanceSection();
        $this->renderHistory();

        echo '</div>';
    }

    private function renderEnvironmentRow(): void
    {
        printf(
            '<p>Running in <code>%s</code>. This screen is not registered in production.</p>',
            esc_html(SyncEnvironment::current()),
        );
    }

    private function renderNotices(): void
    {
        foreach (['error', 'success'] as $type) {
            $message = get_transient($this->noticeKey($type));

            if ($message === false) {
                continue;
            }

            delete_transient($this->noticeKey($type));

            printf(
                '<div class="notice notice-%s"><p>%s</p></div>',
                esc_attr($type === 'error' ? 'error' : 'success'),
                esc_html((string) $message),
            );
        }
    }

    /**
     * @param  array{user_login: string, password: string}  $issued
     */
    private function renderIssuedPassword(array $issued): void
    {
        $envLines = sprintf(
            "SYNC_URL=%s\nSYNC_USER=%s\nSYNC_APP_PASSWORD=%s",
            home_url(),
            $issued['user_login'],
            $issued['password'],
        );

        echo '<div class="notice notice-success"><p><strong>Credentials created.</strong> ';
        echo 'The password below is shown once and cannot be retrieved again.</p>';
        echo '<p>Add these to the <em>other</em> environment&rsquo;s <code>.env</code>, ';
        echo 'prefixed for this environment (e.g. <code>STAGING_SYNC_URL</code>):</p>';
        printf(
            '<textarea readonly rows="3" style="width:100%%;font-family:monospace;">%s</textarea>',
            esc_textarea($envLines),
        );
        echo '</div>';
    }

    /**
     * @param  array{exists: bool, has_capability: bool, password_count: int, available: bool, unavailable_reason: ?string}  $status
     */
    private function renderCredentialSection(array $status): void
    {
        echo '<h2>Sync credentials</h2>';
        echo '<p>The <code>sync-service</code> user this environment accepts sync calls as. ';
        echo 'Creating it here does what <code>wp user create</code> and ';
        echo '<code>wp acorn rl:sync:grant</code> do, so no shell access is needed.</p>';

        echo '<table class="widefat striped" style="max-width:40rem;"><tbody>';
        $this->statusRow('User exists', $status['exists']);
        $this->statusRow('Has sync capability', $status['has_capability']);
        printf(
            '<tr><td>Active application passwords</td><td>%d</td></tr>',
            (int) $status['password_count'],
        );
        echo '</tbody></table>';

        if (! $status['available']) {
            printf(
                '<div class="notice notice-error inline"><p>%s</p></div>',
                esc_html((string) $status['unavailable_reason']),
            );

            return;
        }

        echo '<p>';
        $this->actionButton(
            'provision',
            $status['password_count'] > 0 ? 'Regenerate credentials' : 'Generate sync credentials',
            'button button-primary',
            $status['password_count'] > 0
                ? 'This revokes the current password. Any environment using it will stop syncing until updated. Continue?'
                : null,
        );

        if ($status['exists'] && ($status['has_capability'] || $status['password_count'] > 0)) {
            echo ' ';
            $this->actionButton(
                'revoke',
                'Revoke',
                'button',
                'This revokes the password and removes the sync capability. Continue?',
            );
        }

        echo '</p>';
    }

    /**
     * Create the job. Do none of the work.
     *
     * The push itself is then driven by the browser, one step per AJAX request,
     * because running it here is what returned a 504: the whole conversation is
     * far longer than nginx's proxy timeout, and PHP's own time limit is not
     * what was being hit.
     */
    private function handlePush(): void
    {
        $manifest = TransferManifest::fromArray([
            'direction' => TransferManifest::PUSH,
            'datasets' => array_map('sanitize_key', (array) ($_POST['datasets'] ?? [])),
            'excluded_post_types' => $this->csvField('excluded_post_types'),
            'excluded_post_ids' => $this->csvField('excluded_post_ids'),
            'clean_before_import' => array_map('sanitize_key', (array) ($_POST['clean'] ?? [])),
        ], $this->registry);

        $target = sanitize_key(wp_unslash($_POST['target'] ?? 'staging'));

        if ($target === SyncEnvironment::PRODUCTION) {
            throw new \RuntimeException('Refusing to push to production.');
        }

        $this->assertNothingInFlight();

        $job = $this->jobs->create($manifest, $target);

        set_transient($this->noticeKey('job'), $job->id, HOUR_IN_SECONDS);
    }

    /**
     * Advance a push by exactly one step and report progress.
     *
     * Each call does a bounded amount of work — one batch of rows, or one chunk
     * of one file — so no single request can approach the proxy timeout however
     * large the transfer is.
     */
    public function handlePushStep(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not permitted.'], 403);
        }

        check_ajax_referer(self::SLUG);

        if (! SyncEnvironment::syncEnabled()) {
            wp_send_json_error(['message' => 'Sync is not available in this environment.'], 403);
        }

        $job = $this->jobs->find(sanitize_text_field(wp_unslash($_POST['job'] ?? '')));

        if ($job === null) {
            wp_send_json_error(['message' => 'Unknown push job.'], 404);
        }

        $job = $this->runner->step($job);
        $this->jobs->save($job);

        wp_send_json_success($job->toStatusArray());
    }

    /**
     * Create the pull job. Like push, the work is driven from the browser.
     */
    private function handlePull(): void
    {
        $manifest = TransferManifest::fromArray([
            'direction' => TransferManifest::PULL,
            'datasets' => array_map('sanitize_key', (array) ($_POST['pull_datasets'] ?? [])),
            'excluded_post_types' => $this->csvField('pull_excluded_post_types'),
            'excluded_post_ids' => $this->csvField('pull_excluded_post_ids'),
        ], $this->registry);

        $source = sanitize_key(wp_unslash($_POST['source'] ?? 'staging'));

        $this->assertNothingInFlight();

        $job = $this->pullJobs->create($manifest, $source);

        set_transient($this->noticeKey('pulljob'), $job->id, HOUR_IN_SECONDS);
    }

    public function handlePullStep(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Not permitted.'], 403);
        }

        check_ajax_referer(self::SLUG);

        if (! SyncEnvironment::syncEnabled()) {
            wp_send_json_error(['message' => 'Sync is not available in this environment.'], 403);
        }

        $job = $this->pullJobs->find(sanitize_text_field(wp_unslash($_POST['job'] ?? '')));

        if ($job === null) {
            wp_send_json_error(['message' => 'Unknown pull job.'], 404);
        }

        $job = $this->pullRunner->step($job);
        $this->pullJobs->save($job);

        wp_send_json_success($job->toStatusArray());
    }

    /**
     * Empty a purgeable dataset, here or on a remote.
     *
     * The typed confirmation has to match the environment being emptied, not
     * just be non-empty: the mistake worth guarding against is purging the
     * environment you did not mean to, and only naming it proves which one you
     * had in mind.
     */
    private function handlePurge(): void
    {
        $dataset = sanitize_key(wp_unslash($_POST['purge_dataset'] ?? ''));
        $where = sanitize_key(wp_unslash($_POST['purge_where'] ?? 'local'));
        $typed = sanitize_text_field(wp_unslash($_POST['purge_confirm'] ?? ''));

        $expected = $where === 'local' ? SyncEnvironment::current() : $where;

        if ($typed !== $expected) {
            set_transient(
                $this->noticeKey('error'),
                "Confirmation did not match \"{$expected}\". Nothing was deleted.",
                60,
            );

            return;
        }

        $deleted = $where === 'local'
            ? $this->purger->purge($dataset)
            : $this->purgeRemotely($dataset, $where);

        $summary = [];

        foreach ($deleted as $table => $count) {
            $summary[] = $count < 0 ? "{$table}: table not present" : "{$table}: {$count}";
        }

        set_transient(
            $this->noticeKey('success'),
            'Purged '.$dataset.' on '.$expected.' — '.implode(', ', $summary),
            120,
        );
    }

    /**
     * @return array<string, int>
     */
    private function purgeRemotely(string $dataset, string $target): array
    {
        $result = (new SyncClient($target))->run('app/purge-dataset', [
            'dataset' => $dataset,
            'confirm_environment' => $target,
        ]);

        if (($result['ok'] ?? false) !== true) {
            throw new \RuntimeException((string) ($result['error'] ?? 'Purge failed.'));
        }

        return (array) ($result['deleted'] ?? []);
    }

    /**
     * Refuse to start a transfer while another is unfinished.
     *
     * Two overlapping runs interleave their writes and, worse, interleave their
     * undo entries — rolling either one back would then restore rows the other
     * had legitimately changed. Cancelling is explicit rather than automatic so
     * an abandoned run is never silently discarded.
     */
    private function assertNothingInFlight(): void
    {
        $push = $this->jobs->active();
        $pull = $this->pullJobs->active();

        if ($push === null && $pull === null) {
            return;
        }

        $kind = $push !== null ? 'push' : 'pull';
        $id = $push !== null ? $push->id : $pull->id;

        throw new \RuntimeException(
            "A {$kind} is already in progress ({$id}). Cancel it before starting another."
        );
    }

    private function handleCancelJob(): void
    {
        $id = sanitize_text_field(wp_unslash($_POST['job_id'] ?? ''));

        $cancelled = $this->jobs->cancel($id) || $this->pullJobs->cancel($id);

        set_transient(
            $this->noticeKey($cancelled ? 'success' : 'error'),
            $cancelled
                ? 'Transfer cancelled. Anything already written is still there — roll back the session to undo it.'
                : 'That transfer is not running.',
            60,
        );
    }

    /**
     * Undo a transfer imported into this environment, using its own undo log.
     */
    private function handleRollbackLocal(): void
    {
        $sessionId = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        $session = $this->sessions->find($sessionId);

        if ($session === null) {
            set_transient($this->noticeKey('error'), 'Unknown session.', 60);

            return;
        }

        $log = $this->undoLogs->for($session->id);

        if (! $log->exists()) {
            set_transient($this->noticeKey('error'), 'That session has nothing recorded to roll back.', 60);

            return;
        }

        $applied = $log->rollback();
        $log->discard();

        $session->fail('Rolled back: '.$applied.' change(s) reverted.');
        $this->sessions->save($session);

        set_transient($this->noticeKey('success'), "Reverted {$applied} change(s).", 60);
    }

    private function handleRollback(): void
    {
        $sessionId = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
        $target = sanitize_key(wp_unslash($_POST['target'] ?? 'staging'));

        $result = (new SyncClient($target))->run('app/rollback-transfer', ['session_id' => $sessionId]);

        if (($result['ok'] ?? false) !== true) {
            set_transient($this->noticeKey('error'), (string) ($result['error'] ?? 'Rollback failed.'), 120);

            return;
        }

        set_transient(
            $this->noticeKey('success'),
            sprintf('Reverted %d change(s) on %s.', (int) ($result['reverted'] ?? 0), $target),
            120,
        );
    }

    /**
     * @return array<int, string>
     */
    private function csvField(string $name): array
    {
        $raw = sanitize_text_field(wp_unslash($_POST[$name] ?? ''));

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== ''));
    }

    /**
     * The progress panel, and the loop that drives it.
     *
     * Plain fetch() in a loop rather than anything scheduled: the work only
     * needs to continue while someone is watching, and a browser-driven loop
     * needs no cron, no queue and no worker on either environment — none of
     * which this stack has.
     */
    /**
     * Show whatever transfer is in flight, whether it was just started or was
     * left running by an earlier page load.
     *
     * Reading the store rather than only a transient matters: a tab closed
     * mid-transfer would otherwise leave a job that blocks new runs with nothing
     * on screen explaining why.
     */
    private function renderRunningJob(): void
    {
        $job = $this->jobs->active();

        if ($job !== null) {
            $this->renderProgressPanel(
                $job->toStatusArray(),
                'Push in progress',
                '&rarr; '.$job->target,
                'rl_sync_push_step',
                'rl-sync-progress',
                (bool) get_transient($this->noticeKey('job')),
            );

            delete_transient($this->noticeKey('job'));
        }
    }

    private function renderRunningPull(): void
    {
        $job = $this->pullJobs->active();

        if ($job !== null) {
            $this->renderProgressPanel(
                $job->toStatusArray(),
                'Pull in progress',
                '&larr; '.$job->source,
                'rl_sync_pull_step',
                'rl-sync-pull-progress',
                (bool) get_transient($this->noticeKey('pulljob')),
            );

            delete_transient($this->noticeKey('pulljob'));
        }
    }

    /**
     * @param  array<string, mixed>  $status
     * @param  bool  $autostart  Whether to begin stepping immediately. False for a
     *                           job found already running, so reloading the page
     *                           does not silently resume something you left.
     */
    private function renderProgressPanel(
        array $status,
        string $title,
        string $subject,
        string $action,
        string $elementId,
        bool $autostart,
    ): void {
        $percent = $status['percent'];

        echo '<div class="notice notice-info">';
        printf('<p><strong>%s</strong> %s</p>', esc_html($title), wp_kses_post($subject));

        // An indeterminate bar until a total is known, rather than a confident 0%.
        printf(
            '<progress id="%s-bar" style="width:100%%;height:1.4rem;" max="100"%s></progress>',
            esc_attr($elementId),
            $percent === null ? '' : ' value="'.(int) $percent.'"',
        );

        printf(
            '<p id="%s">%s</p>',
            esc_attr($elementId),
            esc_html((string) $status['label']),
        );

        printf(
            '<p class="description">%s</p>',
            $autostart
                ? 'Keep this tab open. Each step is a separate request, so the transfer is not '
                    .'bound by the server&rsquo;s request timeout.'
                : 'This transfer was left unfinished. Resume it, or cancel it to start a different one.'
        );

        echo '<p>';

        if (! $autostart) {
            printf(
                '<button type="button" class="button" onclick="rlSyncResume_%s()">Resume</button> ',
                esc_attr(str_replace('-', '_', $elementId)),
            );
        }

        $this->cancelButton((string) $status['id']);
        echo '</p></div>';

        $this->renderProgressScript((string) $status['id'], $action, $elementId, $autostart);
    }

    private function cancelButton(string $jobId): void
    {
        echo '<form method="post" style="display:inline;" '
            .'onsubmit="return confirm(\'Cancel this transfer? Anything already written stays until you roll the session back.\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="cancel_job">';
        printf('<input type="hidden" name="job_id" value="%s">', esc_attr($jobId));
        echo '<button type="submit" class="button">Cancel transfer</button>';
        echo '</form>';
    }

    private function renderProgressScript(
        string $jobId,
        string $action,
        string $elementId,
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

        $fn = 'rlSyncResume_'.str_replace('-', '_', $elementId);

        ?>
        <script>
        (function () {
            var cfg = <?php echo $payload; ?>;
            var out = document.getElementById(cfg.el);
            var bar = document.getElementById(cfg.el + '-bar');

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
                            return;
                        }

                        var s = res.data;
                        out.textContent = s.label;

                        if (s.percent === null) {
                            bar.removeAttribute('value');
                        } else {
                            bar.value = s.percent;
                            out.textContent = s.label + '  (' + s.percent + '%)';
                        }

                        if (s.finished) {
                            if (s.phase === 'done') { bar.value = 100; }
                            if (s.phase === 'failed' && s.session_id) {
                                out.innerHTML = out.textContent +
                                    '<br><em>Session ' + s.session_id +
                                    ' is still on the target and can be rolled back.</em>';
                            }
                            return;
                        }

                        step();
                    })
                    .catch(function (e) {
                        out.innerHTML = '<strong>Request failed:</strong> ' + e +
                            '<br><em>Reload and resume to continue from where it stopped.</em>';
                    });
            }

            window[<?php echo wp_json_encode($fn); ?>] = step;

            if (cfg.autostart) { step(); }
        })();
        </script>
        <?php
    }

    /**
     * Pull is the safer direction — everything it overwrites is local — so it
     * needs a lighter confirmation than push, but it is still destructive here.
     */
    private function renderPullSection(): void
    {
        $sources = $this->configuredEnvironments();

        if ($sources === []) {
            return;
        }

        echo '<h2>Pull from a remote</h2>';
        echo '<p>Fetches the remote&rsquo;s data into <em>this</em> environment, overwriting what is here. '
            .'Undoable from Recent transfers below.</p>';

        echo '<form method="post" onsubmit="return confirm(\'This overwrites local data. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="pull">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">Source</th><td><select name="source">';
        foreach ($sources as $source) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $source));
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Datasets</th><td>';
        foreach ($this->registry->transferable() as $dataset) {
            printf(
                '<p><label><input type="checkbox" name="pull_datasets[]" value="%s"%s> %s</label></p>',
                esc_attr($dataset->key),
                $dataset->defaultSelected ? ' checked' : '',
                esc_html($dataset->label),
            );
        }
        echo '</td></tr>';

        echo '<tr><th scope="row">Skip post types</th><td>'
            .'<input type="text" name="pull_excluded_post_types" class="regular-text"></td></tr>';
        echo '<tr><th scope="row">Skip post IDs</th><td>'
            .'<input type="text" name="pull_excluded_post_ids" class="regular-text"></td></tr>';

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button">Pull into this environment</button></p>';
        echo '</form>';
    }

    /**
     * Purge: the only thing that may touch leads, referrals and scheduling.
     *
     * Kept visually separate from transfer because it is the one irreversible
     * action on this screen — there is no undo log for a purge, by design.
     */
    private function renderMaintenanceSection(): void
    {
        echo '<h2>Maintenance</h2>';
        echo '<p>Empties a dataset that is never copied between environments. '
            .'<strong>This cannot be undone.</strong></p>';

        echo '<form method="post">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="purge">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">Dataset</th><td><select name="purge_dataset">';
        foreach ($this->registry->purgeable() as $dataset) {
            $counts = $this->purger->preview($dataset->key);
            $total = array_sum(array_filter($counts, fn (int $c) => $c >= 0));
            printf(
                '<option value="%s">%s (%d rows here)</option>',
                esc_attr($dataset->key),
                esc_html($dataset->label),
                $total,
            );
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Where</th><td><select name="purge_where">';
        printf('<option value="local">this environment (%s)</option>', esc_html(SyncEnvironment::current()));
        foreach ($this->configuredEnvironments() as $env) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $env));
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Confirm</th><td>'
            .'<input type="text" name="purge_confirm" class="regular-text" '
            .'placeholder="type the environment name" autocomplete="off">'
            .'<p class="description">Type the name of the environment you are emptying. '
            .'A mismatch deletes nothing.</p></td></tr>';

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-link-delete">Purge</button></p>';
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

    private function renderTransferSection(): void
    {
        echo '<h2>Transfer</h2>';

        $targets = $this->configuredEnvironments();

        if ($targets === []) {
            echo '<div class="notice notice-warning inline"><p>No remote environments are configured. '
                .'Add <code>STAGING_SYNC_URL</code>, <code>STAGING_SYNC_USER</code> and '
                .'<code>STAGING_SYNC_APP_PASSWORD</code> to this environment&rsquo;s <code>.env</code>.</p></div>';

            return;
        }

        echo '<form method="post" onsubmit="return confirm(\'This overwrites data on the target. Continue?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="push">';

        echo '<table class="form-table"><tbody>';

        echo '<tr><th scope="row">Target</th><td><select name="target">';
        foreach ($targets as $target) {
            printf('<option value="%1$s">%1$s</option>', esc_attr((string) $target));
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Datasets</th><td>';
        foreach ($this->registry->transferable() as $dataset) {
            $this->datasetRow($dataset);
        }
        echo '<p class="description">Leads, referrals, scheduling and users are never transferred. '
            .'Content without media leaves posts pointing at attachments the target does not have.</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">Skip post types</th><td>'
            .'<input type="text" name="excluded_post_types" class="regular-text" placeholder="case_study, page">'
            .'<p class="description">Comma separated.</p></td></tr>';

        echo '<tr><th scope="row">Skip post IDs</th><td>'
            .'<input type="text" name="excluded_post_ids" class="regular-text" placeholder="7, 12">'
            .'<p class="description">Comma separated.</p></td></tr>';

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-primary">Push to target</button></p>';
        echo '</form>';

        echo '<p class="description">A large push is many round trips and can outrun the web server&rsquo;s '
            .'request timeout. For a full sync prefer <code>wp acorn rl:sync:push --target=staging</code>, '
            .'which is not bound by it.</p>';
    }

    private function datasetRow(Dataset $dataset): void
    {
        printf(
            '<p><label><input type="checkbox" name="datasets[]" value="%s"%s> <strong>%s</strong></label>'
            .' &nbsp; <label><input type="checkbox" name="clean[]" value="%s"> empty on target first</label>'
            .'<br><span class="description">%s</span></p>',
            esc_attr($dataset->key),
            $dataset->defaultSelected ? ' checked' : '',
            esc_html($dataset->label),
            esc_attr($dataset->key),
            esc_html($dataset->description),
        );
    }

    private function renderHistory(): void
    {
        $sessions = $this->sessions->recent(10);

        echo '<h2>Recent transfers</h2>';

        if ($sessions === []) {
            echo '<p>No transfers have been received by this environment yet.</p>';
            echo '<p class="description">This list shows sessions this environment <em>received</em>. '
                .'A push you send from here is recorded on the target, not locally.</p>';

            return;
        }

        echo '<table class="widefat striped"><thead><tr>'
            .'<th>Session</th><th>State</th><th>Datasets</th><th>Rows</th><th>Remapped</th><th></th>'
            .'</tr></thead><tbody>';

        foreach ($sessions as $session) {
            $status = $session->toStatusArray();

            // The full ID, not a prefix: this is the value you need to roll the
            // session back, whether from the button beside it or the CLI.
            printf(
                '<tr><td><code style="user-select:all;">%s</code></td>'
                .'<td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s',
                esc_html($status['id']),
                esc_html($status['state']),
                esc_html(implode(', ', $status['datasets'])),
                (int) $status['total_rows'],
                (int) $status['remapped_attachments'],
                $status['error'] ? '<span class="description">'.esc_html($status['error']).'</span> ' : '',
            );

            $this->rollbackButton($status['id']);

            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Undo a transfer this environment received.
     *
     * Local, not remote: the sessions listed here are the ones imported into
     * this install, so the undo log that can reverse them is this one's.
     */
    private function rollbackButton(string $sessionId): void
    {
        echo '<form method="post" style="display:inline;" '
            .'onsubmit="return confirm(\'Revert everything this transfer wrote here?\');">';
        wp_nonce_field(self::SLUG);
        echo '<input type="hidden" name="rl_sync_action" value="rollback_local">';
        printf('<input type="hidden" name="session_id" value="%s">', esc_attr($sessionId));
        echo '<button type="submit" class="button button-small">Roll back</button>';
        echo '</form>';
    }

    private function statusRow(string $label, bool $value): void
    {
        printf(
            '<tr><td>%s</td><td>%s</td></tr>',
            esc_html($label),
            $value
                ? '<span style="color:#008a20;">&#10003; yes</span>'
                : '<span style="color:#b32d2e;">&#10007; no</span>',
        );
    }

    private function actionButton(string $action, string $label, string $class, ?string $confirm): void
    {
        echo '<form method="post" style="display:inline;"';

        if ($confirm !== null) {
            printf(' onsubmit="return confirm(%s);"', esc_attr(wp_json_encode($confirm)));
        }

        echo '>';
        wp_nonce_field(self::SLUG);
        printf('<input type="hidden" name="rl_sync_action" value="%s">', esc_attr($action));
        printf(
            '<button type="submit" class="%s">%s</button>',
            esc_attr($class),
            esc_html($label),
        );
        echo '</form>';
    }

    private function noticeKey(string $type): string
    {
        return 'rl_env_sync_notice_'.$type.'_'.get_current_user_id();
    }
}
