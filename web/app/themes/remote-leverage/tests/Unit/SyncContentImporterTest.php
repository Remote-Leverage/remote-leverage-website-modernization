<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->importer = new ContentImporter($this->registry, new AttachmentReferenceRewriter);
    $this->session = (new SessionStore($this->registry))->create(
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'media'],
        ], $this->registry),
    );
});

function incomingPost(int $id, string $type, array $overrides = []): array
{
    return array_merge([
        'ID' => $id,
        'post_type' => $type,
        'post_title' => "Post {$id}",
        'post_content' => '',
        'post_status' => 'publish',
        'post_name' => "post-{$id}",
        'guid' => "https://source.test/wp-content/uploads/file-{$id}.webp",
    ], $overrides);
}

describe('import ordering', function () {
    it('puts media before content', function () {
        expect($this->registry->importOrder(['content', 'media']))->toBe(['media', 'content']);
    });

    it('keeps settings last', function () {
        expect($this->registry->importOrder(['settings', 'content', 'media']))
            ->toBe(['media', 'content', 'settings']);
    });

    it('does not drop an unrecognised dataset', function () {
        expect($this->registry->importOrder(['content', 'future']))->toBe(['content', 'future']);
    });
});

describe('importing posts', function () {
    it('inserts a post that does not exist on the target', function () {
        $this->importer->importBatch($this->session, 'content', [incomingPost(5, 'page')], []);

        $row = DB::table('posts')->where('ID', 5)->first();

        expect($row)->not->toBeNull()
            ->and($row->post_title)->toBe('Post 5');
    });

    it('overwrites an existing post at the same id', function () {
        DB::table('posts')->insert(incomingPost(5, 'page', ['post_title' => 'Old title']));

        $this->importer->importBatch($this->session, 'content', [incomingPost(5, 'page')], []);

        expect(DB::table('posts')->where('ID', 5)->first()->post_title)->toBe('Post 5')
            ->and(DB::table('posts')->count())->toBe(1);
    });

    it('skips a row with no usable id', function () {
        $written = $this->importer->importBatch($this->session, 'content', [
            incomingPost(0, 'page'),
        ], []);

        expect($written['posts'])->toBe(0)
            ->and(DB::table('posts')->count())->toBe(0);
    });

    it('counts what it wrote onto the session', function () {
        $this->importer->importBatch($this->session, 'content', [
            incomingPost(1, 'page'),
            incomingPost(2, 'post'),
        ], []);

        expect($this->session->rowsFor('content', 'posts'))->toBe(2);
    });
});

describe('attachment id collisions', function () {
    it('keeps the source id when the target slot is free', function () {
        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect(DB::table('posts')->where('ID', 40)->exists())->toBeTrue()
            ->and($this->session->attachmentMap)->toBe([]);
    });

    it('keeps the source id when the same file is already there', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment'));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect($this->session->attachmentMap)->toBe([])
            ->and(DB::table('posts')->count())->toBe(1);
    });

    it('treats the same filename on a different host as the same attachment', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', [
            'guid' => 'https://staging.test/wp-content/uploads/file-40.webp',
        ]));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect($this->session->attachmentMap)->toBe([]);
    });

    it('remaps when a different attachment occupies the id', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', [
            'guid' => 'https://staging.test/wp-content/uploads/something-else.webp',
        ]));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect($this->session->attachmentMap)->toHaveKey(40)
            ->and(DB::table('posts')->count())->toBe(2);
    });

    it('does not destroy the attachment already on the target', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', [
            'guid' => 'https://staging.test/wp-content/uploads/keep-me.webp',
        ]));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect(DB::table('posts')->where('ID', 40)->first()->guid)
            ->toBe('https://staging.test/wp-content/uploads/keep-me.webp');
    });

    it('remaps when a non-attachment post occupies the id', function () {
        DB::table('posts')->insert(incomingPost(40, 'page'));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect($this->session->attachmentMap)->toHaveKey(40)
            ->and(DB::table('posts')->where('ID', 40)->first()->post_type)->toBe('page');
    });

    it('reuses an established remap across batches', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', ['guid' => 'https://s.test/other.webp']));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);
        $newId = $this->session->attachmentMap[40];

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);

        expect($this->session->attachmentMap[40])->toBe($newId)
            ->and(DB::table('posts')->count())->toBe(2);
    });
});

describe('rewriting on import', function () {
    it('rewrites content references to a remapped attachment', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', ['guid' => 'https://s.test/other.webp']));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);
        $newId = $this->session->attachmentMap[40];

        $this->importer->importBatch($this->session, 'content', [
            incomingPost(7, 'page', ['post_content' => '<!-- wp:image {"id":40} --><img class="wp-image-40">']),
        ], []);

        $content = DB::table('posts')->where('ID', 7)->first()->post_content;

        expect($content)->toContain('"id":'.$newId)
            ->and($content)->toContain('wp-image-'.$newId)
            ->and($content)->not->toContain('wp-image-40');
    });

    it('leaves content untouched when nothing was remapped', function () {
        $html = '<!-- wp:image {"id":40} -->';

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);
        $this->importer->importBatch($this->session, 'content', [
            incomingPost(7, 'page', ['post_content' => $html]),
        ], []);

        expect(DB::table('posts')->where('ID', 7)->first()->post_content)->toBe($html);
    });
});

describe('importing meta', function () {
    it('writes meta scoped to its post', function () {
        $this->importer->importBatch($this->session, 'content', [incomingPost(5, 'page')], [
            ['post_id' => 5, 'meta_key' => 'a', 'meta_value' => '1'],
            ['post_id' => 5, 'meta_key' => 'b', 'meta_value' => '2'],
            ['post_id' => 99, 'meta_key' => 'other', 'meta_value' => 'x'],
        ]);

        expect(DB::table('postmeta')->where('post_id', 5)->count())->toBe(2)
            ->and(DB::table('postmeta')->count())->toBe(2);
    });

    it('replaces meta the source has since deleted', function () {
        DB::table('postmeta')->insert(['post_id' => 5, 'meta_key' => 'stale', 'meta_value' => 'x']);

        $this->importer->importBatch($this->session, 'content', [incomingPost(5, 'page')], [
            ['post_id' => 5, 'meta_key' => 'fresh', 'meta_value' => '1'],
        ]);

        $keys = DB::table('postmeta')->where('post_id', 5)->pluck('meta_key')->all();

        expect($keys)->toBe(['fresh']);
    });

    it('follows a remapped attachment when writing its meta', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', ['guid' => 'https://s.test/other.webp']));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], [
            ['post_id' => 40, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/09/file-40.webp'],
        ]);

        $newId = $this->session->attachmentMap[40];

        expect(DB::table('postmeta')->where('post_id', $newId)->count())->toBe(1)
            ->and(DB::table('postmeta')->where('post_id', 40)->count())->toBe(0);
    });

    it('rewrites _thumbnail_id through the map', function () {
        DB::table('posts')->insert(incomingPost(40, 'attachment', ['guid' => 'https://s.test/other.webp']));

        $this->importer->importBatch($this->session, 'media', [incomingPost(40, 'attachment')], []);
        $newId = $this->session->attachmentMap[40];

        $this->importer->importBatch($this->session, 'content', [incomingPost(7, 'page')], [
            ['post_id' => 7, 'meta_key' => '_thumbnail_id', 'meta_value' => '40'],
        ]);

        expect(DB::table('postmeta')->where('post_id', 7)->first()->meta_value)->toBe((string) $newId);
    });

    it('counts meta rows onto the session', function () {
        $this->importer->importBatch($this->session, 'content', [incomingPost(5, 'page')], [
            ['post_id' => 5, 'meta_key' => 'a', 'meta_value' => '1'],
        ]);

        expect($this->session->rowsFor('content', 'meta'))->toBe(1);
    });
});
