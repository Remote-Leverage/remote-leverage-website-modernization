<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\TransferManifest;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->exporter = new ContentExporter($this->registry);

    $insert = function (int $id, string $type, string $title) {
        DB::table('posts')->insert([
            'ID' => $id,
            'post_type' => $type,
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'publish',
            'post_name' => strtolower(str_replace(' ', '-', $title)),
        ]);
    };

    $insert(1, 'page', 'Home');
    $insert(2, 'post', 'A Guide');
    $insert(3, 'case_study', 'Case One');
    $insert(4, 'attachment', 'globe.webp');
    $insert(5, 'attachment', 'hero.webp');
    $insert(6, 'revision', 'Home autosave');
    $insert(7, 'auto-draft', 'Untitled');

    DB::table('postmeta')->insert([
        ['post_id' => 1, 'meta_key' => '_thumbnail_id', 'meta_value' => '4'],
        ['post_id' => 2, 'meta_key' => 'reading_time', 'meta_value' => '6'],
        ['post_id' => 4, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/09/globe.webp'],
    ]);
});

function manifestFor(array $datasets, array $extra = []): TransferManifest
{
    return TransferManifest::fromArray(array_merge([
        'direction' => 'push',
        'datasets' => $datasets,
    ], $extra), new DatasetRegistry);
}

it('excludes revisions and auto-drafts from content', function () {
    $ids = $this->exporter->postIdBatch(manifestFor(['content']), DatasetRegistry::CONTENT, 0, 100);

    expect($ids)->toBe([1, 2, 3]);
});

it('partitions attachments into media, not content', function () {
    $manifest = manifestFor(['content', 'media']);

    expect($this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, 0, 100))->toBe([1, 2, 3])
        ->and($this->exporter->postIdBatch($manifest, DatasetRegistry::MEDIA, 0, 100))->toBe([4, 5]);
});

it('transfers every post exactly once when both datasets are selected', function () {
    $manifest = manifestFor(['content', 'media']);

    $all = array_merge(
        $this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, 0, 100),
        $this->exporter->postIdBatch($manifest, DatasetRegistry::MEDIA, 0, 100),
    );

    expect($all)->toBe(array_unique($all))
        ->and(count($all))->toBe(5);
});

it('honours excluded post types', function () {
    $manifest = manifestFor(['content'], ['excluded_post_types' => ['case_study']]);

    expect($this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, 0, 100))->toBe([1, 2]);
});

it('honours excluded post ids', function () {
    $manifest = manifestFor(['content'], ['excluded_post_ids' => [2]]);

    expect($this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, 0, 100))->toBe([1, 3]);
});

it('excludes attachments by id from media too', function () {
    $manifest = manifestFor(['media'], ['excluded_post_ids' => [4]]);

    expect($this->exporter->postIdBatch($manifest, DatasetRegistry::MEDIA, 0, 100))->toBe([5]);
});

it('walks batches by cursor without skipping or repeating', function () {
    $manifest = manifestFor(['content']);
    $seen = [];
    $after = 0;

    while (($batch = $this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, $after, 2)) !== []) {
        $seen = array_merge($seen, $batch);
        $after = (int) end($batch);
    }

    expect($seen)->toBe([1, 2, 3]);
});

it('keeps the cursor stable when a row is inserted mid-walk', function () {
    $manifest = manifestFor(['content']);

    $first = $this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, 0, 2);
    expect($first)->toBe([1, 2]);

    // A new post lands behind the cursor between requests.
    DB::table('posts')->insert([
        'ID' => 99, 'post_type' => 'post', 'post_title' => 'Late arrival',
        'post_content' => '', 'post_status' => 'publish', 'post_name' => 'late',
    ]);

    $second = $this->exporter->postIdBatch($manifest, DatasetRegistry::CONTENT, (int) end($first), 2);

    expect($second)->toBe([3, 99]);
});

it('counts what it will export', function () {
    expect($this->exporter->count(manifestFor(['content']), DatasetRegistry::CONTENT))->toBe(3)
        ->and($this->exporter->count(manifestFor(['media']), DatasetRegistry::MEDIA))->toBe(2);
});

it('returns full post rows for a batch', function () {
    $rows = $this->exporter->postRows([1, 2]);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['post_title'])->toBe('Home')
        ->and($rows[1]['post_type'])->toBe('post');
});

it('scopes postmeta to the posts travelling with it', function () {
    $rows = $this->exporter->postMetaRows([1, 2]);

    expect($rows)->toHaveCount(2)
        ->and(array_column($rows, 'post_id'))->toBe([1, 2]);
});

it('returns no meta for an empty batch rather than every row', function () {
    expect($this->exporter->postMetaRows([]))->toBe([])
        ->and($this->exporter->postRows([]))->toBe([]);
});
