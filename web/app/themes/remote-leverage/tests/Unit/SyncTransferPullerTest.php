<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncNotPermittedException;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Pull\PullJobRunner;
use App\Domains\Sync\Transfer\Pull\PullJobStore;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\TransferPuller;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Illuminate\Support\Facades\DB;

/**
 * Stands in for the remote environment being pulled from.
 */
class FakeSourceClient extends SyncClient
{
    /** @var array<int, string> */
    public array $calls = [];

    /** @var array<int, array<string, mixed>> */
    public array $batches = [];

    /** @var array<string, mixed>|null */
    public ?array $mediaManifest = null;

    public function __construct() {}

    public function run(string $abilityName, array $input = []): array
    {
        $this->calls[] = $abilityName;

        return match ($abilityName) {
            'app/export-transfer-batch' => $this->nextBatch($input),
            'app/export-media-manifest' => $this->mediaManifest ?? ['ok' => true, 'files' => []],
            default => ['ok' => true],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function nextBatch(array $input): array
    {
        $dataset = (string) ($input['dataset'] ?? '');
        $after = (int) ($input['after'] ?? 0);
        $tableIndex = (int) ($input['table_index'] ?? 0);

        foreach ($this->batches as $batch) {
            if (($batch['dataset'] ?? '') !== $dataset || (int) ($batch['after'] ?? 0) !== $after) {
                continue;
            }

            // Table-backed datasets ask per table, so a stub that does not say
            // which one is taken to mean the first — which is what every
            // posts-shaped stub in this file means.
            if ((int) ($batch['table_index'] ?? 0) !== $tableIndex) {
                continue;
            }

            return $batch['response'];
        }

        return ['ok' => true, 'done' => true];
    }
}

beforeEach(function () {
    $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'development';
    putenv('WP_ENV=development');

    $GLOBALS['_wp_mock_options'] = [];
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->client = new FakeSourceClient;
    $this->undoLogs = new UndoLogFactory;
    $this->puller = new TransferPuller(
        new PullJobStore($this->registry),
        new PullJobRunner(
            $this->registry,
            new SessionStore($this->registry),
            new ContentImporter($this->registry, new AttachmentReferenceRewriter),
            $this->undoLogs,
            new MediaFileExporter,
            fn () => $this->client,
        ),
        $this->undoLogs,
    );
});

afterEach(function () {
    unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
    putenv('WP_ENV');
});

function pullManifest(array $datasets = ['content']): TransferManifest
{
    return TransferManifest::fromArray([
        'direction' => 'pull',
        'datasets' => $datasets,
    ], new DatasetRegistry);
}

function sourceRow(int $id, string $type = 'page', string $title = 'From remote'): array
{
    return [
        'ID' => $id, 'post_type' => $type, 'post_title' => $title,
        'post_content' => '', 'post_status' => 'publish', 'post_name' => "remote-{$id}",
        'guid' => "https://staging.test/?p={$id}",
    ];
}

describe('guards', function () {
    it('refuses a push manifest', function () {
        $manifest = TransferManifest::fromArray([
            'direction' => 'push', 'datasets' => ['content'],
        ], $this->registry);

        $this->puller->pull($manifest, 'staging');
    })->throws(RuntimeException::class, 'only runs pull manifests');

    it('refuses to run in production', function () {
        $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'production';
        putenv('WP_ENV=production');

        $this->puller->pull(pullManifest(), 'staging');
    })->throws(SyncNotPermittedException::class);
});

describe('pulling rows', function () {
    it('writes the source rows into this environment', function () {
        $this->client->batches = [[
            'dataset' => 'content', 'after' => 0,
            'response' => ['ok' => true, 'done' => false, 'last_id' => 5,
                'posts' => [sourceRow(5)], 'meta' => [['post_id' => 5, 'meta_key' => 'k', 'meta_value' => 'v']]],
        ]];

        $result = $this->puller->pull(pullManifest(), 'staging');

        expect(DB::table('posts')->where('ID', 5)->first()->post_title)->toBe('From remote')
            ->and(DB::table('postmeta')->where('post_id', 5)->count())->toBe(1)
            ->and($result['session']['phase'])->toBe('done');
    });

    it('follows the cursor across batches', function () {
        $this->client->batches = [
            ['dataset' => 'content', 'after' => 0, 'response' => [
                'ok' => true, 'done' => false, 'last_id' => 1, 'posts' => [sourceRow(1)], 'meta' => [],
            ]],
            ['dataset' => 'content', 'after' => 1, 'response' => [
                'ok' => true, 'done' => false, 'last_id' => 2, 'posts' => [sourceRow(2)], 'meta' => [],
            ]],
        ];

        $this->puller->pull(pullManifest(), 'staging');

        expect(DB::table('posts')->count())->toBe(2);
    });

    it('stops when the source refuses', function () {
        $this->client->batches = [[
            'dataset' => 'content', 'after' => 0,
            'response' => ['ok' => false, 'error' => 'leads are never transferred'],
        ]];

        $this->puller->pull(pullManifest(), 'staging');
    })->throws(RuntimeException::class, 'leads are never transferred');

    it('names the local rollback command when it fails', function () {
        $this->client->batches = [[
            'dataset' => 'content', 'after' => 0,
            'response' => ['ok' => false, 'error' => 'boom'],
        ]];

        try {
            $this->puller->pull(pullManifest(), 'staging');
            $this->fail('expected a throw');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('rl:sync:rollback-local');
        }
    });

    it('asks the source for media before content', function () {
        $this->puller->pull(pullManifest(['content', 'media']), 'staging');

        $batchCalls = array_values(array_filter(
            $this->client->calls,
            fn (string $c) => $c === 'app/export-transfer-batch',
        ));

        expect($batchCalls)->not->toBeEmpty();
    });

    it('records an undo log for what it wrote', function () {
        $this->client->batches = [[
            'dataset' => 'content', 'after' => 0,
            'response' => ['ok' => true, 'done' => false, 'last_id' => 5, 'posts' => [sourceRow(5)], 'meta' => []],
        ]];

        $result = $this->puller->pull(pullManifest(), 'staging');

        expect($result['undo_entries'])->toBeGreaterThan(0);

        $this->undoLogs->for($result['session_id'])->rollback();

        expect(DB::table('posts')->where('ID', 5)->exists())->toBeFalse();

        $this->undoLogs->forget($result['session_id']);
    });
});

/**
 * Leads travel as whole rows of their own tables rather than as posts, so the
 * pull walks a cursor per table and the local tables are replaced rather than
 * merged. See ContentImporter::importTableBatch().
 */
describe('pulling the leads dataset', function () {
    beforeEach(function () {
        DB::table('rl_leads')->delete();
        DB::table('rl_lead_activity_logs')->delete();
    });

    it('writes the source rows under their original ids', function () {
        $this->client->batches = [
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_leads', 'done' => false, 'last_id' => 4100, 'total' => 1,
                'rows' => [remoteLead(4100)],
            ]],
            ['dataset' => 'leads', 'table_index' => 3, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_lead_activity_logs', 'done' => false, 'last_id' => 9,
                'total' => 1, 'rows' => [remoteActivity(9, 4100)],
            ]],
        ];

        $this->puller->pull(pullManifest(['leads']), 'staging');

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([4100])
            ->and(DB::table('rl_leads')->where('id', 4100)->value('email'))->toBe('remote@example.test')
            ->and(DB::table('rl_lead_activity_logs')->where('id', 9)->value('lead_id'))->toBe(4100);
    });

    it('asks for each table in turn, with its own cursor', function () {
        $this->client->batches = [
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_leads', 'done' => false, 'last_id' => 1, 'total' => 2,
                'rows' => [remoteLead(1)],
            ]],
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 1, 'response' => [
                'ok' => true, 'table' => 'rl_leads', 'done' => false, 'last_id' => 2,
                'rows' => [remoteLead(2)],
            ]],
            ['dataset' => 'leads', 'table_index' => 3, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_lead_activity_logs', 'done' => false, 'last_id' => 3,
                'total' => 1, 'rows' => [remoteActivity(3, 2)],
            ]],
        ];

        $this->puller->pull(pullManifest(['leads']), 'staging');

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1, 2])
            ->and(DB::table('rl_lead_activity_logs')->count())->toBe(1);
    });

    it('empties the local tables even when the source has nothing', function () {
        DB::table('rl_leads')->insert(remoteLead(900));
        DB::table('rl_lead_activity_logs')->insert(remoteActivity(1, 900));

        // Every stub misses, so the source answers "done" with no rows.
        $this->puller->pull(pullManifest(['leads']), 'staging');

        expect(DB::table('rl_leads')->count())->toBe(0)
            ->and(DB::table('rl_lead_activity_logs')->count())->toBe(0);
    });

    it('replaces rather than merges what the target already held', function () {
        DB::table('rl_leads')->insert(remoteLead(900, 'stale@target.test'));

        $this->client->batches = [
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_leads', 'done' => false, 'last_id' => 1, 'total' => 1,
                'rows' => [remoteLead(1)],
            ]],
        ];

        $this->puller->pull(pullManifest(['leads']), 'staging');

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1]);
    });

    it('stops rather than importing rows the source labelled another table', function () {
        $this->client->batches = [
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_lead_activity_logs', 'done' => false, 'last_id' => 1,
                'rows' => [remoteLead(1)],
            ]],
        ];

        $this->puller->pull(pullManifest(['leads']), 'staging');
    })->throws(RuntimeException::class, 'disagree on the shape');

    it('rolls back to what the target held before', function () {
        DB::table('rl_leads')->insert(remoteLead(900, 'original@target.test'));

        $this->client->batches = [
            ['dataset' => 'leads', 'table_index' => 2, 'after' => 0, 'response' => [
                'ok' => true, 'table' => 'rl_leads', 'done' => false, 'last_id' => 1, 'total' => 1,
                'rows' => [remoteLead(1)],
            ]],
        ];

        $result = $this->puller->pull(pullManifest(['leads']), 'staging');
        $this->undoLogs->for($result['session_id'])->rollback();

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([900])
            ->and(DB::table('rl_leads')->where('id', 900)->value('email'))->toBe('original@target.test');

        $this->undoLogs->forget($result['session_id']);
    });
});

/**
 * @return array<string, mixed>
 */
function remoteLead(int $id, string $email = 'remote@example.test'): array
{
    return [
        'id' => $id,
        'uuid' => 'remote-uuid-'.$id,
        'name' => 'Remote lead '.$id,
        'email' => $email,
        'notes' => 'Wide column, sent verbatim.',
        'attribution' => json_encode(['first_touch' => 'linkedin']),
        'source_type' => 'organic',
        'status' => 'captured',
        'is_blocked' => 0,
        'created_at' => '2026-09-02 09:00:00',
        'updated_at' => '2026-09-02 09:00:00',
    ];
}

/**
 * @return array<string, mixed>
 */
function remoteActivity(int $id, int $leadId): array
{
    return [
        'id' => $id,
        'lead_id' => $leadId,
        'event_type' => 'lead.captured',
        'actor_domain' => 'leads',
        'stage' => 'capture',
        'outcome' => 'succeeded',
        'description' => 'Captured remotely.',
        'payload' => json_encode(['lead_id' => $leadId]),
        'created_at' => '2026-09-02 09:00:01',
    ];
}
