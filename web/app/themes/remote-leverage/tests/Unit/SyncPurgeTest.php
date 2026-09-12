<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\SyncNotPermittedException;
use App\Domains\Sync\Transfer\DatasetPurger;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'development';
    putenv('WP_ENV=development');

    DB::table('rl_leads')->delete();
    DB::table('rl_lead_activity_logs')->delete();

    $this->purger = new DatasetPurger(new DatasetRegistry);
});

afterEach(function () {
    unset($_ENV['WP_ENV'], $_SERVER['WP_ENV']);
    putenv('WP_ENV');
});

function seedLead(string $email): void
{
    DB::table('rl_leads')->insert([
        'uuid' => bin2hex(random_bytes(8)),
        'name' => 'Test',
        'email' => $email,
        'created_at' => '2026-09-01 00:00:00',
    ]);
}

describe('what may be purged', function () {
    it('empties a purgeable dataset', function () {
        seedLead('a@example.com');
        seedLead('b@example.com');

        $deleted = $this->purger->purge(DatasetRegistry::LEADS);

        expect(DB::table('rl_leads')->count())->toBe(0)
            ->and($deleted['rl_leads'])->toBe(2);
    });

    it('refuses to purge content', function () {
        $this->purger->purge(DatasetRegistry::CONTENT);
    })->throws(InvalidArgumentException::class, 'cannot be purged');

    it('refuses to purge users', function () {
        $this->purger->purge(DatasetRegistry::USERS);
    })->throws(InvalidArgumentException::class, 'cannot be purged');

    it('refuses to purge media', function () {
        $this->purger->purge(DatasetRegistry::MEDIA);
    })->throws(InvalidArgumentException::class);

    it('refuses an unknown dataset', function () {
        $this->purger->purge('everything');
    })->throws(InvalidArgumentException::class, 'Unknown sync dataset');

    it('refuses to run in production', function () {
        $_ENV['WP_ENV'] = $_SERVER['WP_ENV'] = 'production';
        putenv('WP_ENV=production');

        $this->purger->purge(DatasetRegistry::LEADS);
    })->throws(SyncNotPermittedException::class);
});

describe('previewing', function () {
    it('counts without deleting', function () {
        seedLead('a@example.com');

        $counts = $this->purger->preview(DatasetRegistry::LEADS);

        expect($counts['rl_leads'])->toBe(1)
            ->and(DB::table('rl_leads')->count())->toBe(1);
    });

    it('reports a missing table as -1 rather than failing', function () {
        // rl_live_call_sessions has no table in the test schema, which mirrors
        // an environment whose migrations have not been run.
        $counts = $this->purger->preview(DatasetRegistry::SCHEDULING);

        expect($counts['rl_live_call_sessions'])->toBe(-1);
    });

    it('survives purging a dataset whose tables are absent', function () {
        $deleted = $this->purger->purge(DatasetRegistry::SCHEDULING);

        expect($deleted['rl_live_call_sessions'])->toBe(-1);
    });
});
