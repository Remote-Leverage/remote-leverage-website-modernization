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
        private readonly EnvironmentSyncScreen $screen,
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

        $this->renderNotices();

        $issued = get_transient(self::PASSWORD_TRANSIENT.get_current_user_id());
        delete_transient(self::PASSWORD_TRANSIENT.get_current_user_id());

        // A job started by the form this request answers should begin stepping
        // straight away; one merely *found* unfinished should not, or reloading
        // the page would silently resume something you had walked away from.
        $autostartPush = (bool) get_transient($this->noticeKey('job'));
        $autostartPull = (bool) get_transient($this->noticeKey('pulljob'));
        delete_transient($this->noticeKey('job'));
        delete_transient($this->noticeKey('pulljob'));

        $this->screen->render(is_array($issued) ? $issued : null, $autostartPush, $autostartPull);
    }

    /**
     * Result messages for the action just performed.
     *
     * These stay .notice — WordPress hoisting them to the top of the screen is
     * exactly right for a one-line outcome. The progress panel deliberately is
     * not one, because a live bar has to stay in the content where it is read.
     */
    private function renderNotices(): void
    {
        foreach (['error', 'success'] as $type) {
            $message = get_transient($this->noticeKey($type));

            if ($message === false) {
                continue;
            }

            delete_transient($this->noticeKey($type));

            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type === 'error' ? 'error' : 'success'),
                esc_html((string) $message),
            );
        }
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

    private function noticeKey(string $type): string
    {
        return 'rl_env_sync_notice_'.$type.'_'.get_current_user_id();
    }
}
