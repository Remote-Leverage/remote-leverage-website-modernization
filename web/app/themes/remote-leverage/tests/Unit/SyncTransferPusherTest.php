<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncNotPermittedException;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferPusher;
use Illuminate\Support\Facades\DB;

/**
 * Stands in for a remote environment, recording every ability call so the test
 * can assert on the conversation rather than on HTTP.
 */
class FakeSyncClient extends SyncClient
{
    /** @var array<int, array{ability: string, input: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, mixed> */
    public array $chunkResponse = ['ok' => true];

    public function __construct(public array $beginResponse = []) {}

    public function run(string $abilityName, array $input = []): array
    {
        $this->calls[] = ['ability' => $abilityName, 'input' => $input];

        return match ($abilityName) {
            'app/begin-transfer' => $this->beginResponse ?: ['ok' => true, 'session' => ['id' => 'sess-1']],
            'app/receive-transfer-chunk' => $this->chunkResponse,
            'app/finish-transfer' => ['ok' => true, 'session' => ['state' => 'complete'], 'undo_entries' => 4],
            'app/check-media-files' => ['ok' => true, 'missing' => []],
            default => ['ok' => true],
        };
    }

    /** @return array<int, array<string, mixed>> */
    public function chunks(): array
    {
        return array_values(array_map(
            fn (array $c) => $c['input'],
            array_filter($this->calls, fn (array $c) => $c['ability'] === 'app/receive-transfer-chunk'),
        ));
    }
}

beforeEach(function () {
    $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'development';
    putenv('WP_ENV=development');

    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    // The config stub is a global, so a test that points staging at production's
    // host would leak that into every test after it. Reset to a safe baseline.
    config([
        'rl-sync.environments.staging.url' => 'https://staging.remoteleverage.com',
        'rl-sync.environments.production.url' => 'https://remoteleverage.com',
    ]);

    $this->registry = new DatasetRegistry;
    $this->client = new FakeSyncClient;
    $this->pusher = new TransferPusher(
        $this->registry,
        new ContentExporter($this->registry),
        new MediaFileExporter,
        fn () => $this->client,
    );
});

afterEach(function () {
    unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
    putenv('WP_ENV');
});

function pushManifest(array $datasets = ['content'], string $direction = 'push'): TransferManifest
{
    return TransferManifest::fromArray([
        'direction' => $direction,
        'datasets' => $datasets,
    ], new DatasetRegistry);
}

function seedPost(int $id, string $type = 'page'): void
{
    DB::table('posts')->insert([
        'ID' => $id, 'post_type' => $type, 'post_title' => "Post {$id}",
        'post_content' => '', 'post_status' => 'publish', 'post_name' => "post-{$id}",
        'guid' => "https://local.test/?p={$id}",
    ]);
}

describe('production guards', function () {
    it('refuses a production target outright', function () {
        $this->pusher->push(pushManifest(), 'production');
    })->throws(RuntimeException::class, 'Refusing to push to production');

    it('refuses a target configured with production\'s host', function () {
        config([
            'rl-sync.environments.staging.url' => 'https://remoteleverage.com',
            'rl-sync.environments.production.url' => 'https://remoteleverage.com',
        ]);

        $this->pusher->push(pushManifest(), 'staging');
    })->throws(RuntimeException::class, 'same host as production');

    it('allows a staging target on its own host', function () {
        config([
            'rl-sync.environments.staging.url' => 'https://staging.remoteleverage.com',
            'rl-sync.environments.production.url' => 'https://remoteleverage.com',
        ]);

        $this->pusher->push(pushManifest(), 'staging');

        expect($this->client->calls)->not->toBeEmpty();
    });

    it('refuses to run at all from a production environment', function () {
        $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'production';
        putenv('WP_ENV=production');

        $this->pusher->push(pushManifest(), 'staging');
    })->throws(SyncNotPermittedException::class);

    it('refuses a pull manifest', function () {
        $this->pusher->push(pushManifest(['content'], 'pull'), 'staging');
    })->throws(RuntimeException::class, 'only runs push manifests');
});

describe('the push conversation', function () {
    it('begins, chunks, then finishes', function () {
        seedPost(1);

        $this->pusher->push(pushManifest(), 'staging');

        expect(array_column($this->client->calls, 'ability'))
            ->toBe(['app/begin-transfer', 'app/receive-transfer-chunk', 'app/finish-transfer']);
    });

    it('sends media before content', function () {
        seedPost(1, 'page');
        seedPost(2, 'attachment');

        $this->pusher->push(pushManifest(['content', 'media']), 'staging');

        expect(array_column($this->client->chunks(), 'dataset'))->toBe(['media', 'content']);
    });

    it('stops when the target refuses the transfer', function () {
        $this->client->beginResponse = ['ok' => false, 'error' => 'leads are never transferred'];

        $this->pusher->push(pushManifest(), 'staging');
    })->throws(RuntimeException::class, 'leads are never transferred');

    it('names the session to roll back when a batch is rejected', function () {
        seedPost(1);
        $this->client->chunkResponse = ['ok' => false, 'error' => 'schema mismatch'];

        try {
            $this->pusher->push(pushManifest(), 'staging');
            $this->fail('expected the push to throw');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('schema mismatch')
                ->and($e->getMessage())->toContain('sess-1')
                ->and($e->getMessage())->toContain('roll it back');
        }
    });

    it('does not call finish after a failed batch', function () {
        seedPost(1);
        $this->client->chunkResponse = ['ok' => false, 'error' => 'nope'];

        try {
            $this->pusher->push(pushManifest(), 'staging');
        } catch (RuntimeException) {
            // expected
        }

        expect(array_column($this->client->calls, 'ability'))->not->toContain('app/finish-transfer');
    });

    it('walks every post across multiple batches', function () {
        foreach (range(1, TransferPusher::BATCH_SIZE + 5) as $id) {
            seedPost($id);
        }

        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($this->client->chunks())->toHaveCount(2)
            ->and($result['sent']['posts'])->toBe(TransferPusher::BATCH_SIZE + 5);
    });

    it('sends each post exactly once', function () {
        foreach (range(1, TransferPusher::BATCH_SIZE + 5) as $id) {
            seedPost($id);
        }

        $this->pusher->push(pushManifest(), 'staging');

        $ids = [];

        foreach ($this->client->chunks() as $chunk) {
            $ids = array_merge($ids, array_column($chunk['posts'], 'ID'));
        }

        expect($ids)->toBe(array_unique($ids))
            ->and($ids)->toHaveCount(TransferPusher::BATCH_SIZE + 5);
    });

    it('sends no chunk at all when nothing matches', function () {
        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($this->client->chunks())->toBeEmpty()
            ->and($result['sent']['posts'])->toBe(0);
    });

    it('reports progress as it goes', function () {
        foreach (range(1, TransferPusher::BATCH_SIZE + 1) as $id) {
            seedPost($id);
        }

        $seen = [];
        $this->pusher->push(pushManifest(), 'staging', function (string $dataset, array $sent) use (&$seen) {
            $seen[] = $sent['posts'];
        });

        expect($seen)->toBe([TransferPusher::BATCH_SIZE, TransferPusher::BATCH_SIZE + 1]);
    });

    it('returns the target\'s closing summary', function () {
        seedPost(1);

        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($result['session_id'])->toBe('sess-1')
            ->and($result['undo_entries'])->toBe(4);
    });
});
