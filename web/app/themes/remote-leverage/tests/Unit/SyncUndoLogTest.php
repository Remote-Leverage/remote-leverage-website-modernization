<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->factory = new UndoLogFactory;
    $this->importer = new ContentImporter($this->registry, new AttachmentReferenceRewriter);
    $this->session = (new SessionStore($this->registry))->create(
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content'],
        ], $this->registry),
    );
    $this->undo = $this->factory->for($this->session->id);
});

afterEach(function () {
    $this->factory->forget($this->session->id);
});

function row(int $id, array $overrides = []): array
{
    return array_merge([
        'ID' => $id,
        'post_type' => 'page',
        'post_title' => "Incoming {$id}",
        'post_content' => 'new body',
        'post_status' => 'publish',
        'post_name' => "incoming-{$id}",
        'guid' => "https://source.test/?p={$id}",
    ], $overrides);
}

describe('undo log basics', function () {
    it('does not exist until something is recorded', function () {
        expect($this->undo->exists())->toBeFalse();
    });

    it('records and counts entries', function () {
        $this->undo->recordInsert('posts', ['ID' => 1]);
        $this->undo->recordUpdate('posts', ['ID' => 2], ['ID' => 2, 'post_title' => 'old']);

        expect($this->undo->exists())->toBeTrue()
            ->and($this->undo->entryCount())->toBe(2);
    });

    it('discards the log on request', function () {
        $this->undo->recordInsert('posts', ['ID' => 1]);
        $this->undo->discard();

        expect($this->undo->exists())->toBeFalse();
    });

    it('skips a truncated trailing line rather than abandoning the rollback', function () {
        $this->undo->recordUpdate('posts', ['ID' => 1], ['ID' => 1, 'post_title' => 'kept']);

        $path = (new ReflectionClass($this->undo))->getProperty('path')->getValue($this->undo);
        file_put_contents($path, '{"op":"update","tab', FILE_APPEND);

        expect($this->undo->entryCount())->toBe(1);
    });

    it('reports nothing for a session that never wrote', function () {
        expect($this->undo->entryCount())->toBe(0)
            ->and($this->undo->rollback())->toBe(0);
    });
});

describe('rolling back an import', function () {
    it('deletes a post the transfer created', function () {
        $this->importer->importBatch($this->session, 'content', [row(5)], [], $this->undo);
        expect(DB::table('posts')->where('ID', 5)->exists())->toBeTrue();

        $this->undo->rollback();

        expect(DB::table('posts')->where('ID', 5)->exists())->toBeFalse();
    });

    it('restores a post the transfer overwrote', function () {
        DB::table('posts')->insert(row(5, ['post_title' => 'Original', 'post_content' => 'old body']));

        $this->importer->importBatch($this->session, 'content', [row(5)], [], $this->undo);
        expect(DB::table('posts')->where('ID', 5)->first()->post_title)->toBe('Incoming 5');

        $this->undo->rollback();

        $restored = DB::table('posts')->where('ID', 5)->first();

        expect($restored->post_title)->toBe('Original')
            ->and($restored->post_content)->toBe('old body');
    });

    it('restores meta the transfer replaced', function () {
        DB::table('posts')->insert(row(5));
        DB::table('postmeta')->insert(['post_id' => 5, 'meta_key' => 'original', 'meta_value' => 'kept']);

        $this->importer->importBatch($this->session, 'content', [row(5)], [
            ['post_id' => 5, 'meta_key' => 'incoming', 'meta_value' => 'new'],
        ], $this->undo);

        expect(DB::table('postmeta')->where('post_id', 5)->pluck('meta_key')->all())->toBe(['incoming']);

        $this->undo->rollback();

        expect(DB::table('postmeta')->where('post_id', 5)->pluck('meta_key')->all())->toBe(['original']);
    });

    it('removes meta attached to a post that did not exist before', function () {
        $this->importer->importBatch($this->session, 'content', [row(5)], [
            ['post_id' => 5, 'meta_key' => 'incoming', 'meta_value' => 'new'],
        ], $this->undo);

        $this->undo->rollback();

        expect(DB::table('postmeta')->where('post_id', 5)->count())->toBe(0)
            ->and(DB::table('posts')->where('ID', 5)->exists())->toBeFalse();
    });

    it('restores a multi-post batch to exactly its prior state', function () {
        DB::table('posts')->insert(row(1, ['post_title' => 'Keep 1']));
        DB::table('posts')->insert(row(2, ['post_title' => 'Keep 2']));

        $this->importer->importBatch($this->session, 'content', [row(1), row(2), row(3)], [], $this->undo);

        expect(DB::table('posts')->count())->toBe(3);

        $this->undo->rollback();

        $titles = DB::table('posts')->orderBy('ID')->pluck('post_title')->all();

        expect($titles)->toBe(['Keep 1', 'Keep 2'])
            ->and(DB::table('posts')->where('ID', 3)->exists())->toBeFalse();
    });

    it('undoes in reverse so a row written twice ends at its original value', function () {
        DB::table('posts')->insert(row(5, ['post_title' => 'Original']));

        $this->importer->importBatch($this->session, 'content', [row(5, ['post_title' => 'First'])], [], $this->undo);
        $this->importer->importBatch($this->session, 'content', [row(5, ['post_title' => 'Second'])], [], $this->undo);

        $this->undo->rollback();

        expect(DB::table('posts')->where('ID', 5)->first()->post_title)->toBe('Original');
    });

    it('writes no undo entries when no log is passed', function () {
        $this->importer->importBatch($this->session, 'content', [row(5)], []);

        expect($this->undo->exists())->toBeFalse()
            ->and(DB::table('posts')->where('ID', 5)->exists())->toBeTrue();
    });
});
