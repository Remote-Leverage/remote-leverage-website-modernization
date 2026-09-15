<?php

declare(strict_types=1);

use App\Domains\Sync\Abilities\PurgeTransferLogsAbility;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncNotPermittedException;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferLogPurger;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\UndoLogFactory;

/**
 * Clearing an environment's transfer history.
 *
 * Added because there was no way to do it on a remote at all: staging has no
 * shell, and nothing in the sync channel could delete a session. A run of failed
 * pushes therefore left stale sessions that refused every later transfer and
 * could not be cleared from anywhere.
 *
 * The refusals matter more than the deletion here. An undo log is the only thing
 * that can revert a transfer that already landed, so clearing one does not undo
 * that transfer, it makes it permanent.
 */
beforeEach(function () {
    $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'development';
    putenv('WP_ENV=development');

    $GLOBALS['_wp_mock_options'] = [];

    $this->registry = new DatasetRegistry;
    $this->sessions = new SessionStore($this->registry);
    $this->undoLogs = new UndoLogFactory;
    $this->purger = new TransferLogPurger($this->sessions, $this->undoLogs);
});

afterEach(function () {
    foreach ($this->undoLogs->sessionIds() as $id) {
        $this->undoLogs->forget($id);
    }

    unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
    putenv('WP_ENV');
});

function purgeSession(SessionStore $store, DatasetRegistry $registry, string $state = 'complete')
{
    $session = $store->create(TransferManifest::fromArray([
        'direction' => 'push',
        'datasets' => ['content'],
    ], $registry));

    $state === 'complete' ? $session->complete() : $session->fail('done in test');
    $store->save($session);

    return $session;
}

it('deletes every session record', function () {
    purgeSession($this->sessions, $this->registry);
    purgeSession($this->sessions, $this->registry, 'failed');

    $result = $this->purger->purge();

    expect($result['sessions'])->toBe(2)
        ->and($this->sessions->ids())->toBe([]);
});

it('deletes the undo logs with them', function () {
    $session = purgeSession($this->sessions, $this->registry);
    $this->undoLogs->for($session->id)->recordInsert('posts', ['ID' => 1]);

    expect($this->undoLogs->for($session->id)->exists())->toBeTrue();

    $result = $this->purger->purge();

    expect($result['undo_logs'])->toBe(1)
        ->and($this->undoLogs->for($session->id)->exists())->toBeFalse();
});

it('finds an orphaned log whose session record is already gone', function () {
    $session = purgeSession($this->sessions, $this->registry);
    $this->undoLogs->for($session->id)->recordInsert('posts', ['ID' => 1]);
    $this->sessions->delete($session->id);

    $result = $this->purger->purge();

    expect($result['sessions'])->toBe(0)
        ->and($result['undo_logs'])->toBe(1);
});

it('refuses while a session is still live, rather than stranding its undo log', function () {
    $live = $this->sessions->create(TransferManifest::fromArray([
        'direction' => 'push',
        'datasets' => ['content'],
    ], $this->registry));

    expect(fn () => $this->purger->purge())
        ->toThrow(RuntimeException::class, $live->id);

    expect($this->sessions->ids())->toContain($live->id);
});

it('refuses to run in production at all', function () {
    $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'production';
    putenv('WP_ENV=production');

    $this->purger->purge();
})->throws(SyncNotPermittedException::class);

describe('the ability wrapper', function () {
    it('refuses when the caller names the wrong environment', function () {
        purgeSession($this->sessions, $this->registry);

        $result = (new PurgeTransferLogsAbility($this->purger))
            ->execute(['confirm_environment' => 'staging']);

        expect($result['ok'])->toBeFalse()
            ->and($this->sessions->ids())->toHaveCount(1);
    });

    it('clears when the environment matches', function () {
        purgeSession($this->sessions, $this->registry);

        $result = (new PurgeTransferLogsAbility($this->purger))
            ->execute(['confirm_environment' => 'development']);

        expect($result['ok'])->toBeTrue()
            ->and($result['sessions'])->toBe(1)
            ->and($this->sessions->ids())->toBe([]);
    });

    it('reports a live session as an error rather than throwing', function () {
        $this->sessions->create(TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content'],
        ], $this->registry));

        $result = (new PurgeTransferLogsAbility($this->purger))
            ->execute(['confirm_environment' => 'development']);

        expect($result['ok'])->toBeFalse()
            ->and($result['error'])->toContain('still');
    });
});
