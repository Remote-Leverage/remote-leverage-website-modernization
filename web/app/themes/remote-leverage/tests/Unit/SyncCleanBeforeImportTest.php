<?php

declare(strict_types=1);

use App\Domains\Sync\Abilities\ReceiveTransferChunkAbility;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Import\DatasetCleaner;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Illuminate\Support\Facades\DB;

/**
 * The clean and the import meeting each other, through the ability that runs
 * them both — the unit tests either side of this cover them apart.
 */
beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->sessions = new SessionStore($this->registry);
    $this->undoLogs = new UndoLogFactory;

    $this->ability = new ReceiveTransferChunkAbility(
        $this->sessions,
        new ContentImporter($this->registry, new AttachmentReferenceRewriter),
        new DatasetCleaner,
        $this->undoLogs,
    );
});

afterEach(function () {
    foreach ($this->undoLogs->sessionIds() as $id) {
        $this->undoLogs->forget($id);
    }
});

function cleanSession(SessionStore $sessions, DatasetRegistry $registry, array $clean = ['content'])
{
    return $sessions->create(TransferManifest::fromArray([
        'direction' => 'push',
        'datasets' => ['content'],
        'clean_before_import' => $clean,
    ], $registry));
}

function targetPost(int $id, string $title): void
{
    DB::table('posts')->insert([
        'ID' => $id,
        'post_type' => 'page',
        'post_title' => $title,
        'post_content' => '',
        'post_status' => 'publish',
        'post_name' => 'target-'.$id,
        'guid' => "https://target.test/?p={$id}",
        'post_date' => '2026-01-01 00:00:00',
        'post_date_gmt' => '2026-01-01 00:00:00',
        'post_modified' => '2026-01-01 00:00:00',
        'post_modified_gmt' => '2026-01-01 00:00:00',
    ]);
}

function arrivingPost(int $id, string $title): array
{
    return [
        'ID' => $id,
        'post_type' => 'page',
        'post_title' => $title,
        'post_content' => '',
        'post_status' => 'publish',
        'post_name' => 'source-'.$id,
        'guid' => "https://source.test/?p={$id}",
        'post_date' => '2026-02-01 00:00:00',
        'post_date_gmt' => '2026-02-01 00:00:00',
        'post_modified' => '2026-02-01 00:00:00',
        'post_modified_gmt' => '2026-02-01 00:00:00',
    ];
}

it('removes a page that exists only on the target', function () {
    targetPost(50, 'Only on staging');
    $session = cleanSession($this->sessions, $this->registry);

    $result = $this->ability->execute([
        'session_id' => $session->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'From local')],
        'meta' => [],
    ]);

    // The whole point of the mirror: without the clean, page 50 survives
    // because the source has nothing at that ID to overwrite it with.
    expect($result['ok'])->toBeTrue()
        ->and(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([9])
        ->and(DB::table('posts')->where('ID', 9)->value('post_title'))->toBe('From local');
});

it('reports how many rows it removed', function () {
    targetPost(50, 'One');
    targetPost(51, 'Two');

    $result = $this->ability->execute([
        'session_id' => cleanSession($this->sessions, $this->registry)->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'From local')],
        'meta' => [],
    ]);

    expect($result['session']['counters']['content']['removed'])->toBe(2);
});

it('leaves the target alone when clean is not asked for', function () {
    targetPost(50, 'Only on staging');

    $this->ability->execute([
        'session_id' => cleanSession($this->sessions, $this->registry, [])->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'From local')],
        'meta' => [],
    ]);

    expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toEqualCanonicalizing([9, 50]);
});

it('cleans once, not once per chunk', function () {
    targetPost(50, 'Only on staging');
    $session = cleanSession($this->sessions, $this->registry);

    $this->ability->execute([
        'session_id' => $session->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'First chunk')],
        'meta' => [],
    ]);

    $this->ability->execute([
        'session_id' => $session->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(10, 'Second chunk')],
        'meta' => [],
    ]);

    // A clean on the second chunk would delete what the first one imported —
    // the transfer would end with only ever the last batch on the target.
    expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toEqualCanonicalizing([9, 10]);
});

it('does not re-clean when a chunk is redelivered', function () {
    $session = cleanSession($this->sessions, $this->registry);

    $chunk = [
        'session_id' => $session->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'First chunk')],
        'meta' => [],
    ];

    $this->ability->execute($chunk);
    $this->ability->execute([...$chunk, 'posts' => [arrivingPost(10, 'Second chunk')]]);
    $this->ability->execute($chunk);

    expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toEqualCanonicalizing([9, 10]);
});

it('rolls the clean back along with the import', function () {
    targetPost(50, 'Only on staging');
    DB::table('postmeta')->insert(['post_id' => 50, 'meta_key' => 'kept', 'meta_value' => 'yes']);

    $session = cleanSession($this->sessions, $this->registry);

    $this->ability->execute([
        'session_id' => $session->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'From local')],
        'meta' => [],
    ]);

    $this->undoLogs->for($session->id)->rollback();

    expect(DB::table('posts')->pluck('ID')->map('intval')->all())->toBe([50])
        ->and(DB::table('posts')->where('ID', 50)->value('post_title'))->toBe('Only on staging')
        ->and(DB::table('postmeta')->where('post_id', 50)->value('meta_value'))->toBe('yes');
});

it('does not clean a dataset that is not part of this chunk', function () {
    DB::table('posts')->insert([
        'ID' => 60,
        'post_type' => 'attachment',
        'post_title' => 'An upload',
        'post_content' => '',
        'post_status' => 'inherit',
        'post_name' => 'an-upload',
        'guid' => 'https://target.test/wp-content/uploads/an-upload.webp',
        'post_date' => '2026-01-01 00:00:00',
        'post_date_gmt' => '2026-01-01 00:00:00',
        'post_modified' => '2026-01-01 00:00:00',
        'post_modified_gmt' => '2026-01-01 00:00:00',
    ]);
    targetPost(50, 'Only on staging');

    $this->ability->execute([
        'session_id' => cleanSession($this->sessions, $this->registry)->id,
        'dataset' => 'content',
        'posts' => [arrivingPost(9, 'From local')],
        'meta' => [],
    ]);

    // Media was never selected, so the attachment must survive a content clean.
    expect(DB::table('posts')->where('ID', 60)->exists())->toBeTrue();
});
