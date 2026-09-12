<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferSession;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];

    $this->registry = new DatasetRegistry;
    $this->store = new SessionStore($this->registry);
    $this->manifest = TransferManifest::fromArray([
        'direction' => 'push',
        'datasets' => ['content', 'media'],
    ], $this->registry);
});

describe('session state', function () {
    it('accumulates row counts per dataset and kind', function () {
        $session = $this->store->create($this->manifest);

        $session->recordRows('content', 'posts', 50);
        $session->recordRows('content', 'posts', 25);
        $session->recordRows('content', 'meta', 10);

        expect($session->rowsFor('content', 'posts'))->toBe(75)
            ->and($session->rowsFor('content', 'meta'))->toBe(10)
            ->and($session->totalRows())->toBe(85);
    });

    it('reports zero for a dataset it has not seen', function () {
        expect($this->store->create($this->manifest)->rowsFor('media', 'posts'))->toBe(0);
    });

    it('is not finished while open', function () {
        expect($this->store->create($this->manifest)->isFinished())->toBeFalse();
    });

    it('records a failure reason', function () {
        $session = $this->store->create($this->manifest);
        $session->fail('target schema is behind');

        expect($session->state)->toBe(TransferSession::STATE_FAILED)
            ->and($session->error)->toBe('target schema is behind')
            ->and($session->isFinished())->toBeTrue();
    });

    it('clears a previous error when completing', function () {
        $session = $this->store->create($this->manifest);
        $session->fail('transient');
        $session->complete();

        expect($session->state)->toBe(TransferSession::STATE_COMPLETE)
            ->and($session->error)->toBeNull();
    });

    it('exposes a status summary', function () {
        $session = $this->store->create($this->manifest);
        $session->recordRows('content', 'posts', 3);
        $session->attachmentMap = [506 => 9001];

        $status = $session->toStatusArray();

        expect($status['state'])->toBe('open')
            ->and($status['direction'])->toBe('push')
            ->and($status['total_rows'])->toBe(3)
            ->and($status['remapped_attachments'])->toBe(1);
    });
});

describe('session store', function () {
    it('round-trips a session through options', function () {
        $session = $this->store->create($this->manifest);
        $session->recordRows('content', 'posts', 7);
        $session->attachmentMap = [12 => 34];
        $this->store->save($session);

        $loaded = $this->store->find($session->id);

        expect($loaded)->not->toBeNull()
            ->and($loaded->id)->toBe($session->id)
            ->and($loaded->rowsFor('content', 'posts'))->toBe(7)
            ->and($loaded->attachmentMap)->toBe([12 => 34])
            ->and($loaded->manifest->datasets)->toBe(['content', 'media']);
    });

    it('returns null for an unknown session', function () {
        expect($this->store->find('deadbeefdeadbeef'))->toBeNull();
    });

    it('refuses a malformed session id rather than building an option name from it', function (string $id) {
        expect($this->store->find($id))->toBeNull();
    })->with(['../../etc/passwd', 'short', '', 'rl_sync_session_x; DROP TABLE', str_repeat('a', 200)]);

    it('gives every session a distinct id', function () {
        $ids = [];

        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->store->create($this->manifest)->id;
        }

        expect(array_unique($ids))->toHaveCount(5);
    });

    it('keeps only the retention limit of sessions', function () {
        $created = [];

        for ($i = 0; $i < SessionStore::RETENTION + 3; $i++) {
            $created[] = $this->store->create($this->manifest)->id;
        }

        expect($this->store->ids())->toHaveCount(SessionStore::RETENTION);

        // The three oldest are gone, the newest survive.
        expect($this->store->find($created[0]))->toBeNull()
            ->and($this->store->find($created[1]))->toBeNull()
            ->and($this->store->find($created[2]))->toBeNull()
            ->and($this->store->find(end($created)))->not->toBeNull();
    });

    it('lists recent sessions newest first', function () {
        $first = $this->store->create($this->manifest)->id;
        $second = $this->store->create($this->manifest)->id;

        $recent = $this->store->recent(10);

        expect($recent[0]->id)->toBe($second)
            ->and($recent[1]->id)->toBe($first);
    });

    it('honours the recent limit', function () {
        for ($i = 0; $i < 5; $i++) {
            $this->store->create($this->manifest);
        }

        expect($this->store->recent(2))->toHaveCount(2);
    });

    it('deletes a session and drops it from the index', function () {
        $session = $this->store->create($this->manifest);
        $this->store->delete($session->id);

        expect($this->store->find($session->id))->toBeNull()
            ->and($this->store->ids())->not->toContain($session->id);
    });

    it('survives a corrupted index option', function () {
        update_option('rl_sync_session_index', 'not-an-array');

        expect($this->store->ids())->toBe([]);
    });
});
