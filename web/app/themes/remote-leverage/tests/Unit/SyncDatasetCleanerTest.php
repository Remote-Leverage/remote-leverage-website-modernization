<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Import\DatasetCleaner;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferSession;
use App\Domains\Sync\Transfer\UndoLog;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->cleaner = new DatasetCleaner;
    $this->undoPath = sys_get_temp_dir().'/rl-clean-undo-'.bin2hex(random_bytes(6)).'.jsonl';
});

afterEach(function () {
    if (is_file($this->undoPath)) {
        unlink($this->undoPath);
    }
});

function cleanerSession(array $manifest = []): TransferSession
{
    $registry = new DatasetRegistry;

    return (new SessionStore($registry))->create(TransferManifest::fromArray(array_merge([
        'direction' => 'push',
        'datasets' => ['content', 'media'],
        'clean_before_import' => ['content'],
    ], $manifest), $registry));
}

function existingPost(int $id, string $type, array $overrides = []): void
{
    DB::table('posts')->insert(array_merge([
        'ID' => $id,
        'post_type' => $type,
        'post_title' => "Existing {$id}",
        'post_content' => '',
        'post_status' => 'publish',
        'post_name' => "existing-{$id}",
        'guid' => "https://target.test/?p={$id}",
        'post_date' => '2026-01-01 00:00:00',
        'post_date_gmt' => '2026-01-01 00:00:00',
        'post_modified' => '2026-01-01 00:00:00',
        'post_modified_gmt' => '2026-01-01 00:00:00',
    ], $overrides));
}

describe('what a clean removes', function () {
    it('deletes the content posts already on the target', function () {
        existingPost(11, 'page');
        existingPost(12, 'post');

        $removed = $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT);

        expect($removed)->toBe(2)
            ->and(DB::table('posts')->count())->toBe(0);
    });

    it('leaves attachments alone when cleaning content', function () {
        existingPost(11, 'page');
        existingPost(12, 'attachment');

        $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT);

        expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([12]);
    });

    it('deletes only attachments when cleaning media', function () {
        existingPost(11, 'page');
        existingPost(12, 'attachment');

        $this->cleaner->clean(cleanerSession(['clean_before_import' => ['media']]), DatasetRegistry::MEDIA);

        expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([11]);
    });

    it('deletes the meta belonging to the posts it removes', function () {
        existingPost(11, 'page');
        DB::table('postmeta')->insert(['post_id' => 11, 'meta_key' => 'k', 'meta_value' => 'v']);
        DB::table('postmeta')->insert(['post_id' => 99, 'meta_key' => 'k', 'meta_value' => 'kept']);

        $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT);

        expect(DB::table('postmeta')->pluck('post_id')->map('intval')->all())->toBe([99]);
    });

    it('never deletes revisions or auto-drafts', function () {
        existingPost(11, 'revision');
        existingPost(12, 'auto-draft');
        existingPost(13, 'page');

        $removed = $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT);

        expect($removed)->toBe(1)
            ->and(DB::table('posts')->pluck('ID')->map('intval')->all())->toEqualCanonicalizing([11, 12]);
    });

    it('walks past a batch boundary', function () {
        foreach (range(1, 205) as $id) {
            existingPost($id, 'page');
        }

        expect($this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT))->toBe(205)
            ->and(DB::table('posts')->count())->toBe(0);
    });
});

describe('exclusions bind the clean', function () {
    it('keeps a post type the transfer was told to skip', function () {
        existingPost(11, 'page');
        existingPost(12, 'case_study');

        $session = cleanerSession(['excluded_post_types' => ['case_study']]);
        $removed = $this->cleaner->clean($session, DatasetRegistry::CONTENT);

        // Nothing is arriving to replace an excluded type, so deleting it would
        // be a one-way loss rather than a mirror.
        expect($removed)->toBe(1)
            ->and(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([12]);
    });

    it('keeps a post ID the transfer was told to skip', function () {
        existingPost(11, 'page');
        existingPost(12, 'page');

        $session = cleanerSession(['excluded_post_ids' => [12]]);
        $this->cleaner->clean($session, DatasetRegistry::CONTENT);

        expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([12]);
    });
});

describe('a clean is reversible', function () {
    it('restores the posts and meta it deleted', function () {
        existingPost(11, 'page', ['post_title' => 'Only on the target']);
        DB::table('postmeta')->insert(['post_id' => 11, 'meta_key' => 'colour', 'meta_value' => 'red']);

        $undo = new UndoLog($this->undoPath);
        $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT, $undo);

        expect(DB::table('posts')->count())->toBe(0);

        $undo->rollback();

        $row = DB::table('posts')->where('ID', 11)->first();

        expect($row)->not->toBeNull()
            ->and($row->post_title)->toBe('Only on the target')
            ->and(DB::table('postmeta')->where('post_id', 11)->value('meta_value'))->toBe('red');
    });

    it('records an entry per deleted post even with no meta', function () {
        existingPost(11, 'page');
        existingPost(12, 'page');

        $undo = new UndoLog($this->undoPath);
        $this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT, $undo);

        // One posts entry and one postmeta rowset entry each.
        expect($undo->entryCount())->toBe(4);
    });

    it('works without an undo log', function () {
        existingPost(11, 'page');

        expect($this->cleaner->clean(cleanerSession(), DatasetRegistry::CONTENT, null))->toBe(1);
    });
});

describe('the once-only guard', function () {
    it('starts unmarked and records each dataset separately', function () {
        $session = cleanerSession();

        expect($session->hasCleaned('content'))->toBeFalse();

        $session->markCleaned('content');

        expect($session->hasCleaned('content'))->toBeTrue()
            ->and($session->hasCleaned('media'))->toBeFalse();
    });

    it('does not mark the same dataset twice', function () {
        $session = cleanerSession();
        $session->markCleaned('content');
        $session->markCleaned('content');

        expect($session->cleaned)->toBe(['content']);
    });

    it('survives being persisted and reloaded', function () {
        $store = new SessionStore($this->registry);
        $session = cleanerSession();
        $session->markCleaned('content');
        $store->save($session);

        // The guard is worthless if it does not outlive the request: a
        // redelivered chunk would otherwise clean away everything already
        // imported by the chunks before it.
        expect($store->find($session->id)->hasCleaned('content'))->toBeTrue();
    });
});
