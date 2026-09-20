<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncClient;
use App\Domains\Sync\SyncNotPermittedException;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\Media\MediaFileExporter;
use App\Domains\Sync\Transfer\Push\PushJobRunner;
use App\Domains\Sync\Transfer\Push\PushJobStore;
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

    /** @var array<string, mixed> */
    public array $settingsResponse = ['ok' => true, 'updated' => [], 'rejected' => []];

    public function __construct(public array $beginResponse = []) {}

    public function run(string $abilityName, array $input = []): array
    {
        $this->calls[] = ['ability' => $abilityName, 'input' => $input];

        return match ($abilityName) {
            'app/begin-transfer' => $this->beginResponse ?: ['ok' => true, 'session' => ['id' => 'sess-1']],
            'app/receive-transfer-chunk' => $this->chunkResponse,
            'app/import-syncable-settings' => $this->settingsResponse,
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
    $GLOBALS['_wp_mock_options'] = [];

    $this->pusher = new TransferPusher(
        new PushJobStore($this->registry),
        new PushJobRunner(
            $this->registry,
            new ContentExporter($this->registry),
            new MediaFileExporter,
            fn () => $this->client,
        ),
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
        foreach (range(1, PushJobRunner::BATCH_SIZE + 5) as $id) {
            seedPost($id);
        }

        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($this->client->chunks())->toHaveCount(2)
            ->and($result['sent']['posts'])->toBe(PushJobRunner::BATCH_SIZE + 5);
    });

    it('sends each post exactly once', function () {
        foreach (range(1, PushJobRunner::BATCH_SIZE + 5) as $id) {
            seedPost($id);
        }

        $this->pusher->push(pushManifest(), 'staging');

        $ids = [];

        foreach ($this->client->chunks() as $chunk) {
            $ids = array_merge($ids, array_column($chunk['posts'], 'ID'));
        }

        expect($ids)->toBe(array_unique($ids))
            ->and($ids)->toHaveCount(PushJobRunner::BATCH_SIZE + 5);
    });

    it('sends no chunk at all when nothing matches', function () {
        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($this->client->chunks())->toBeEmpty()
            ->and($result['sent']['posts'])->toBe(0);
    });

    it('reports progress as it goes', function () {
        foreach (range(1, PushJobRunner::BATCH_SIZE + 1) as $id) {
            seedPost($id);
        }

        $seen = [];
        $this->pusher->push(pushManifest(), 'staging', function (string $phase, array $status) use (&$seen) {
            if ($phase === 'rows') {
                $seen[] = $status['counters']['posts'];
            }
        });

        // One callback per step, so the running total is reported as it climbs
        // and then once more on the step that finds the dataset exhausted.
        expect($seen)->toContain(PushJobRunner::BATCH_SIZE)
            ->and(end($seen))->toBe(PushJobRunner::BATCH_SIZE + 1);
    });

    it('returns the target\'s closing summary', function () {
        seedPost(1);

        $result = $this->pusher->push(pushManifest(), 'staging');

        expect($result['session_id'])->toBe('sess-1')
            ->and($result['undo_entries'])->toBe(4);
    });
});

/**
 * Regression cover for a bug that shipped content under the settings label.
 *
 * ContentExporter partitions wp_posts with a single "is it an attachment?"
 * question, so every dataset that was not media fell into the content branch —
 * settings included. A settings-only push therefore counted, and would have
 * sent, every non-attachment post on the site, while the dataset's own
 * description promised "only the option keys whitelisted in config/rl-sync.php,
 * never a wholesale wp_options copy".
 *
 * The first test here is the one that matters: it fails loudly if settings ever
 * starts claiming post rows again.
 */
describe('the settings dataset', function () {
    beforeEach(function () {
        config(['rl-sync.options' => ['rl_lead_webhook_url', 'rl_slack_webhook_url']]);

        $GLOBALS['_wp_mock_options'] = [
            'rl_lead_webhook_url' => 'https://hooks.test/lead',
            'rl_slack_webhook_url' => 'https://hooks.test/slack',
        ];
    });

    it('sends no content rows, however much content exists', function () {
        seedPost(1);
        seedPost(2);
        seedPost(3, 'case_study');

        $this->pusher->push(pushManifest(['settings']), 'staging');

        expect($this->client->chunks())->toBe([]);
    });

    it('counts no posts, so a dry run cannot report the content total', function () {
        seedPost(1);
        seedPost(2);

        $exporter = new ContentExporter($this->registry);

        expect($exporter->count(pushManifest(['settings']), 'settings'))->toBe(0)
            ->and($exporter->count(pushManifest(['content']), 'content'))->toBe(2);
    });

    it('sends the whitelisted options through the settings ability', function () {
        $this->pusher->push(pushManifest(['settings']), 'staging');

        $calls = array_values(array_filter(
            $this->client->calls,
            fn (array $c) => $c['ability'] === 'app/import-syncable-settings',
        ));

        expect($calls)->toHaveCount(1)
            ->and($calls[0]['input']['values'])->toBe([
                'rl_lead_webhook_url' => 'https://hooks.test/lead',
                'rl_slack_webhook_url' => 'https://hooks.test/slack',
            ]);
    });

    it('still closes the session when settings are the only dataset', function () {
        $result = $this->pusher->push(pushManifest(['settings']), 'staging');

        expect($result['session_id'])->toBe('sess-1');
    });

    it('fails rather than quietly dropping a key the target will not accept', function () {
        $this->client->settingsResponse = [
            'ok' => true,
            'updated' => ['rl_lead_webhook_url'],
            'rejected' => ['rl_slack_webhook_url'],
        ];

        expect(fn () => $this->pusher->push(pushManifest(['settings']), 'staging'))
            ->toThrow(RuntimeException::class, 'rl_slack_webhook_url');
    });

    it('does not disturb a content push', function () {
        seedPost(1);

        $this->pusher->push(pushManifest(['content']), 'staging');

        expect($this->client->chunks())->toHaveCount(1);
    });
});

/**
 * A push refused at begin never received a session id.
 *
 * Telling the operator to "roll back session " — with nothing after it — sent
 * them looking for a session that does not exist, while the id actually
 * blocking them sat in the line above. Seen for real against staging.
 */
describe('reporting a push that never opened a session', function () {
    it('does not tell the operator to roll back an empty session id', function () {
        $this->client->beginResponse = [
            'ok' => false,
            'error' => 'A transfer is already in progress on this environment (session other-1, open).',
        ];

        try {
            $this->pusher->push(pushManifest(), 'staging');
            $this->fail('Expected the push to throw.');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->not->toContain('still holds session')
                ->and($e->getMessage())->toContain('other-1');
        }
    });

    it('still names the session when one was opened', function () {
        seedPost(1);
        $this->client->chunkResponse = ['ok' => false, 'error' => 'nope'];

        try {
            $this->pusher->push(pushManifest(), 'staging');
            $this->fail('Expected the push to throw.');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('still holds session sess-1');
        }
    });
});

/**
 * Leads are pushed as whole rows of their own tables. The runner walks a cursor
 * per table and a tableIndex across them, and every table gets an opening chunk
 * — including an empty one, which is what tells the target to empty its copy.
 */
describe('pushing the leads dataset', function () {
    beforeEach(function () {
        DB::table('rl_leads')->delete();
        DB::table('rl_lead_activity_logs')->delete();
    });

    it('sends each table in load order, rows verbatim', function () {
        seedPushLead(1);
        DB::table('rl_lead_activity_logs')->insert(pushActivityRow(7, 1));

        $this->pusher->push(pushManifest(['leads']), 'staging');

        $chunks = $this->client->chunks();

        expect(array_column($chunks, 'table'))->toBe([
            // Every table gets an opening chunk, empty or not: that chunk is what tells the
            // target to empty its copy, so skipping one would turn the replace into a merge.
            'rl_lead_profiles',
            'rl_lead_identifiers',
            'rl_leads',
            'rl_lead_activity_logs',
        ])
            ->and(array_column($chunks, 'dataset'))->toBe(['leads', 'leads', 'leads', 'leads'])
            // The profile tables are empty here, so the rows land in the third and fourth chunks.
            ->and($chunks[2]['rows'][0]['id'])->toBe(1)
            ->and($chunks[2]['rows'][0]['email'])->toBe('pushed@example.test')
            ->and($chunks[3]['rows'][0]['lead_id'])->toBe(1);
    });

    it('sends no post rows at all', function () {
        seedPost(1);
        seedPushLead(1);

        $this->pusher->push(pushManifest(['leads']), 'staging');

        foreach ($this->client->chunks() as $chunk) {
            expect($chunk)->not->toHaveKey('posts');
        }
    });

    it('still opens each table when the source has no rows for it', function () {
        $this->pusher->push(pushManifest(['leads']), 'staging');

        // Without this the target would keep rows the source does not have, and
        // a "replace" would quietly be a merge.
        expect(array_column($this->client->chunks(), 'table'))
            ->toBe([
                // Every table gets an opening chunk, empty or not: that chunk is what tells the
                // target to empty its copy, so skipping one would turn the replace into a merge.
                'rl_lead_profiles',
                'rl_lead_identifiers',
                'rl_leads',
                'rl_lead_activity_logs',
            ]);
    });

    it('walks a wide table across several batches', function () {
        foreach (range(1, PushJobRunner::BATCH_SIZE + 3) as $id) {
            seedPushLead($id);
        }

        $this->pusher->push(pushManifest(['leads']), 'staging');

        $chunks = $this->client->chunks();
        $ids = [];

        foreach ($chunks as $chunk) {
            $ids = array_merge($ids, array_column($chunk['rows'], 'id'));
        }

        /*
         * Two chunks for the leads themselves, plus one opening chunk for each of the other three
         * tables — empty, and sent anyway, because that is what empties the target's copy.
         */
        expect($chunks)->toHaveCount(5)
            ->and($ids)->toBe(array_unique($ids))
            ->and($ids)->toHaveCount(PushJobRunner::BATCH_SIZE + 3);
    });

    it('counts lead rows toward the progress total, not the content set', function () {
        seedPost(1);
        seedPost(2);
        seedPushLead(1);

        $seen = [];
        $this->pusher->push(pushManifest(['leads']), 'staging', function (string $phase, array $status) use (&$seen) {
            $seen[] = $status['totals']['posts'];
        });

        expect($seen[0])->toBe(1);
    });

    it('stops when the target rejects a table batch', function () {
        seedPushLead(1);
        $this->client->chunkResponse = ['ok' => false, 'error' => 'unknown column'];

        $this->pusher->push(pushManifest(['leads']), 'staging');
    })->throws(RuntimeException::class, 'The target rejected a rl_lead_profiles batch');
});

function seedPushLead(int $id): void
{
    DB::table('rl_leads')->insert([
        'id' => $id,
        'uuid' => 'push-uuid-'.$id,
        'name' => 'Pushed lead '.$id,
        'email' => 'pushed@example.test',
        'notes' => 'A wide note column.',
        'attribution' => json_encode(['first_touch' => 'google']),
        'source_type' => 'organic',
        'status' => 'captured',
        'is_blocked' => 0,
        'created_at' => '2026-09-03 08:00:00',
        'updated_at' => '2026-09-03 08:00:00',
    ]);
}

/**
 * @return array<string, mixed>
 */
function pushActivityRow(int $id, int $leadId): array
{
    return [
        'id' => $id,
        'lead_id' => $leadId,
        'event_type' => 'lead.captured',
        'actor_domain' => 'leads',
        'stage' => 'capture',
        'outcome' => 'succeeded',
        'description' => 'Captured locally.',
        'payload' => json_encode(['lead_id' => $leadId]),
        'created_at' => '2026-09-03 08:00:01',
    ];
}
