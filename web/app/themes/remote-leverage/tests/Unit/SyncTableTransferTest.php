<?php

declare(strict_types=1);

use App\Domains\Sync\Abilities\ExportTransferBatchAbility;
use App\Domains\Sync\Abilities\ReceiveTransferChunkAbility;
use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Export\ContentExporter;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;
use App\Domains\Sync\Transfer\Import\ContentImporter;
use App\Domains\Sync\Transfer\Import\DatasetCleaner;
use App\Domains\Sync\Transfer\SessionStore;
use App\Domains\Sync\Transfer\TransferManifest;
use App\Domains\Sync\Transfer\UndoLogFactory;
use Illuminate\Support\Facades\DB;

/**
 * The leads dataset travelling as whole table rows, in both directions.
 *
 * Everything here runs against the same in-memory schema the rest of the sync
 * suite uses, so "the row survived" means the real columns survived — including
 * the wide ones (notes, attribution) that are the reason the transfer is
 * batched at all.
 */
beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];

    DB::table('rl_leads')->delete();
    DB::table('rl_lead_activity_logs')->delete();
    DB::table('posts')->delete();
    DB::table('postmeta')->delete();

    $this->registry = new DatasetRegistry;
    $this->exporter = new ContentExporter($this->registry);
    $this->sessions = new SessionStore($this->registry);
    $this->undoLogs = new UndoLogFactory;
    $this->importer = new ContentImporter($this->registry, new AttachmentReferenceRewriter);

    $this->ability = new ReceiveTransferChunkAbility(
        $this->sessions,
        $this->importer,
        new DatasetCleaner,
        $this->undoLogs,
        $this->registry,
    );

    $this->exportAbility = new ExportTransferBatchAbility($this->registry, $this->exporter);
});

afterEach(function () {
    foreach ($this->undoLogs->sessionIds() as $id) {
        $this->undoLogs->forget($id);
    }
});

function leadRow(int $id, string $email = 'lead@example.test', string $notes = 'Called back twice.'): array
{
    return [
        'id' => $id,
        'uuid' => 'uuid-'.$id,
        'name' => 'Lead '.$id,
        'email' => $email,
        'notes' => $notes,
        'attribution' => json_encode(['first_touch' => 'google', 'id' => $id]),
        'source_type' => 'organic',
        'status' => 'captured',
        'is_blocked' => 0,
        'created_at' => '2026-09-01 10:00:00',
        'updated_at' => '2026-09-01 10:00:00',
    ];
}

function activityRow(int $id, int $leadId): array
{
    return [
        'id' => $id,
        'lead_id' => $leadId,
        'event_type' => 'lead.captured',
        'actor_domain' => 'leads',
        'stage' => 'capture',
        'outcome' => 'succeeded',
        'description' => 'Captured from the intake form.',
        'payload' => json_encode(['lead_id' => $leadId]),
        'created_at' => '2026-09-01 10:00:01',
    ];
}

function seedTransferLead(int $id, string $email = 'lead@example.test'): void
{
    DB::table('rl_leads')->insert(leadRow($id, $email));
}

function leadsSession(SessionStore $sessions, DatasetRegistry $registry, string $direction = 'push')
{
    return $sessions->create(TransferManifest::fromArray([
        'direction' => $direction,
        'datasets' => ['leads'],
    ], $registry));
}

describe('the exporter reading whole rows', function () {
    it('batches by id and reports the last one it sent', function () {
        seedTransferLead(1);
        seedTransferLead(2);
        seedTransferLead(3);

        $first = $this->exporter->tableRowBatch('rl_leads', 0, 2);
        $second = $this->exporter->tableRowBatch('rl_leads', 2, 2);

        expect(array_column($first, 'id'))->toBe([1, 2])
            ->and(array_column($second, 'id'))->toBe([3])
            ->and($this->exporter->tableRowBatch('rl_leads', 3, 2))->toBe([]);
    });

    it('sends every column verbatim, PII included', function () {
        seedTransferLead(1, 'someone@real.test');

        $row = $this->exporter->tableRowBatch('rl_leads', 0, 10)[0];

        expect($row['email'])->toBe('someone@real.test')
            ->and($row['notes'])->toBe('Called back twice.')
            ->and($row['attribution'])->toBe(json_encode(['first_touch' => 'google', 'id' => 1]));
    });

    it('counts a table without walking it', function () {
        seedTransferLead(1);
        seedTransferLead(2);

        expect($this->exporter->tableCount('rl_leads'))->toBe(2)
            ->and($this->exporter->tableCount('rl_lead_activity_logs'))->toBe(0);
    });

    it('refuses a table no dataset declares as transferable', function () {
        $this->exporter->tableRowBatch('users', 0, 10);
    })->throws(InvalidArgumentException::class, 'not a transferable table');
});

describe('the export ability', function () {
    it('serves a table batch for a table-backed dataset', function () {
        seedTransferLead(1);
        seedTransferLead(2);

        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['leads']],
            'dataset' => 'leads',
            'table_index' => 2,
            'after' => 0,
            'limit' => 10,
        ]);

        expect($batch['ok'])->toBeTrue()
            ->and($batch['table'])->toBe('rl_leads')
            ->and(array_column($batch['rows'], 'id'))->toBe([1, 2])
            ->and($batch['last_id'])->toBe(2)
            ->and($batch['done'])->toBeFalse()
            ->and($batch['total'])->toBe(2);
    });

    it('reports done once the cursor has passed the last row', function () {
        seedTransferLead(1);

        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['leads']],
            'dataset' => 'leads',
            'table_index' => 0,
            'after' => 1,
            'limit' => 10,
        ]);

        expect($batch['done'])->toBeTrue()
            ->and($batch['rows'])->toBe([])
            ->and($batch['total'])->toBeNull();
    });

    it('serves a later table by index', function () {
        seedTransferLead(1);
        DB::table('rl_lead_activity_logs')->insert(activityRow(7, 1));

        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['leads']],
            'dataset' => 'leads',
            'table_index' => 3,
            'after' => 0,
            'limit' => 10,
        ]);

        expect($batch['table'])->toBe('rl_lead_activity_logs')
            ->and($batch['rows'][0]['lead_id'])->toBe(1);
    });

    it('refuses a table index the dataset does not have', function () {
        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['leads']],
            'dataset' => 'leads',
            'table_index' => 5,
        ]);

        expect($batch['ok'])->toBeFalse()
            ->and($batch['error'])->toContain('Table index 5');
    });

    it('still refuses a dataset that is not transferable at all', function () {
        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['referrals']],
            'dataset' => 'referrals',
        ]);

        expect($batch['ok'])->toBeFalse()
            ->and($batch['error'])->toContain('never transferred');
    });

    it('does not serve a table batch for a dataset outside the manifest', function () {
        $batch = $this->exportAbility->execute([
            'manifest' => ['direction' => 'pull', 'datasets' => ['content']],
            'dataset' => 'leads',
            'table_index' => 0,
        ]);

        expect($batch['ok'])->toBeFalse()
            ->and($batch['error'])->toContain('not part of this transfer');
    });
});

describe('the importer replacing a table', function () {
    it('empties the target before the first batch', function () {
        seedTransferLead(900, 'stale@target.test');
        $session = leadsSession($this->sessions, $this->registry);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(1)]);

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1]);
    });

    it('does not empty again on a later batch', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(1)]);
        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(2)]);

        // A second empty would leave only the final batch on the target.
        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1, 2]);
    });

    it('does not empty again when the second table starts', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(1)]);
        $this->importer->importTableBatch($session, 'leads', 'rl_lead_activity_logs', [activityRow(5, 1)]);

        expect(DB::table('rl_leads')->count())->toBe(1)
            ->and(DB::table('rl_lead_activity_logs')->count())->toBe(1);
    });

    it('keeps the original primary keys so the foreign key still resolves', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(4100), leadRow(4101)]);
        $this->importer->importTableBatch($session, 'leads', 'rl_lead_activity_logs', [activityRow(88, 4101)]);

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([4100, 4101])
            ->and(DB::table('rl_lead_activity_logs')->where('id', 88)->value('lead_id'))->toBe(4101);
    });

    it('empties the child table before the parent', function () {
        $order = [];

        seedTransferLead(1);
        DB::table('rl_lead_activity_logs')->insert(activityRow(1, 1));

        DB::listen(function ($query) use (&$order) {
            if (str_starts_with(strtolower($query->sql), 'delete')) {
                $order[] = str_contains($query->sql, 'activity') ? 'child' : 'parent';
            }
        });

        $this->importer->emptyTablesFor(leadsSession($this->sessions, $this->registry), 'leads');

        DB::getEventDispatcher()->forget('Illuminate\Database\Events\QueryExecuted');

        expect($order)->toBe(['child', 'parent']);
    });

    it('writes the rows it received, byte for byte', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [
            leadRow(1, 'verbatim@example.test', "A note with 'quotes' and a — dash."),
        ]);

        $stored = (array) DB::table('rl_leads')->where('id', 1)->first();

        expect($stored['email'])->toBe('verbatim@example.test')
            ->and($stored['notes'])->toBe("A note with 'quotes' and a — dash.")
            ->and($stored['attribution'])->toBe(json_encode(['first_touch' => 'google', 'id' => 1]));
    });

    it('refuses a table that belongs to another dataset', function () {
        $this->importer->importTableBatch(
            leadsSession($this->sessions, $this->registry),
            'leads',
            'rl_referrals',
            [],
        );
    })->throws(InvalidArgumentException::class, 'not part of the "Leads" dataset');

    it('refuses a dataset the session did not open', function () {
        $session = $this->sessions->create(TransferManifest::fromArray([
            'direction' => 'push', 'datasets' => ['content'],
        ], $this->registry));

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [leadRow(1)]);
    })->throws(InvalidArgumentException::class, 'not part of this transfer');

    it('refuses to empty the tables of a dataset that is not table-backed', function () {
        $session = $this->sessions->create(TransferManifest::fromArray([
            'direction' => 'push', 'datasets' => ['content'],
        ], $this->registry));

        $this->importer->emptyTablesFor($session, 'content');
    })->throws(InvalidArgumentException::class, 'does not travel as table rows');

    it('refuses a row with no primary key rather than dropping it', function () {
        $session = leadsSession($this->sessions, $this->registry);
        $row = leadRow(1);
        unset($row['id']);

        $this->importer->importTableBatch($session, 'leads', 'rl_leads', [$row]);
    })->throws(RuntimeException::class, 'without a id');
});

describe('the receive ability', function () {
    it('applies a table chunk and reports what it wrote', function () {
        seedTransferLead(900, 'stale@target.test');
        $session = leadsSession($this->sessions, $this->registry);

        $result = $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1), leadRow(2)],
        ]);

        expect($result['ok'])->toBeTrue()
            ->and($result['written'])->toBe(['table' => 'rl_leads', 'rows' => 2])
            ->and(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1, 2]);
    });

    it('empties even when the arriving batch is empty', function () {
        seedTransferLead(900);
        seedTransferLead(901);

        $this->ability->execute([
            'session_id' => leadsSession($this->sessions, $this->registry)->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [],
        ]);

        // A source with no leads still means the target must end up with none.
        expect(DB::table('rl_leads')->count())->toBe(0);
    });

    it('does not re-empty when a chunk is redelivered', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $chunk = [
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1)],
        ];

        $this->ability->execute($chunk);
        $this->ability->execute([...$chunk, 'rows' => [leadRow(2)]]);
        $this->ability->execute($chunk);

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([1, 2]);
    });

    it('records the emptied rows in the session counters', function () {
        seedTransferLead(900);
        seedTransferLead(901);

        $result = $this->ability->execute([
            'session_id' => leadsSession($this->sessions, $this->registry)->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1)],
        ]);

        expect($result['session']['counters']['leads']['removed'])->toBe(2)
            ->and($result['session']['counters']['leads']['rl_leads'])->toBe(1);
    });

    it('refuses a chunk for a dataset the session never opened', function () {
        $session = $this->sessions->create(TransferManifest::fromArray([
            'direction' => 'push', 'datasets' => ['content'],
        ], $this->registry));

        seedTransferLead(900);

        $result = $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1)],
        ]);

        expect($result['ok'])->toBeFalse()
            ->and(DB::table('rl_leads')->count())->toBe(1);
    });

    it('never truncates on an unknown session', function () {
        seedTransferLead(900);

        $result = $this->ability->execute([
            'session_id' => 'not-a-session',
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [],
        ]);

        expect($result['ok'])->toBeFalse()
            ->and(DB::table('rl_leads')->count())->toBe(1);
    });

    it('does not run the post cleaner for a table-backed dataset', function () {
        DB::table('posts')->insert([
            'ID' => 5, 'post_type' => 'page', 'post_title' => 'Untouched',
            'post_content' => '', 'post_status' => 'publish', 'post_name' => 'untouched',
        ]);

        $session = $this->sessions->create(TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['leads'],
            'clean_before_import' => ['leads'],
        ], $this->registry));

        $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1)],
        ]);

        // Asking to "clean leads" must not be read as "delete every post".
        expect(DB::table('posts')->count())->toBe(1);
    });
});

describe('rolling a lead transfer back', function () {
    it('restores the rows the import replaced', function () {
        seedTransferLead(900, 'original@target.test');
        DB::table('rl_lead_activity_logs')->insert(activityRow(90, 900));

        $session = leadsSession($this->sessions, $this->registry);

        $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1, 'incoming@source.test')],
        ]);
        $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_lead_activity_logs',
            'rows' => [activityRow(2, 1)],
        ]);

        $this->undoLogs->for($session->id)->rollback();

        expect(DB::table('rl_leads')->pluck('id')->map('intval')->all())->toBe([900])
            ->and(DB::table('rl_leads')->where('id', 900)->value('email'))->toBe('original@target.test')
            ->and(DB::table('rl_lead_activity_logs')->pluck('id')->map('intval')->all())->toBe([90]);
    });

    it('leaves nothing behind when the target started empty', function () {
        $session = leadsSession($this->sessions, $this->registry);

        $this->ability->execute([
            'session_id' => $session->id,
            'dataset' => 'leads',
            'table' => 'rl_leads',
            'rows' => [leadRow(1), leadRow(2)],
        ]);

        $this->undoLogs->for($session->id)->rollback();

        expect(DB::table('rl_leads')->count())->toBe(0);
    });
});
