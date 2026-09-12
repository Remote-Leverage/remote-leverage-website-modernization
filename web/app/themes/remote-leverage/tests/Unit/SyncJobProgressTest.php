<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\Pull\PullJobStore;
use App\Domains\Sync\Transfer\Push\PushJob;
use App\Domains\Sync\Transfer\Push\PushJobStore;
use App\Domains\Sync\Transfer\TransferManifest;

beforeEach(function () {
    $GLOBALS['_wp_mock_options'] = [];

    $this->registry = new DatasetRegistry;
    $this->pushJobs = new PushJobStore($this->registry);
    $this->pullJobs = new PullJobStore($this->registry);

    $this->manifest = TransferManifest::fromArray([
        'direction' => 'push', 'datasets' => ['content'],
    ], $this->registry);

    $this->pullManifest = TransferManifest::fromArray([
        'direction' => 'pull', 'datasets' => ['content'],
    ], $this->registry);
});

describe('progress reporting', function () {
    it('reports no percentage before a total is known', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');

        expect($job->percent())->toBeNull();
    });

    it('reports a percentage once totals are set', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 100, 'files' => 100];
        $job->counters = ['posts' => 50, 'meta' => 0, 'files' => 0];

        expect($job->percent())->toBe(25);
    });

    it('counts files and posts toward the same bar', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 10, 'files' => 10];
        $job->counters = ['posts' => 10, 'meta' => 0, 'files' => 5];

        expect($job->percent())->toBe(75);
    });

    it('never exceeds 100 when more arrives than expected', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 10, 'files' => 0];
        $job->counters = ['posts' => 50, 'meta' => 0, 'files' => 0];

        expect($job->percent())->toBe(100);
    });

    it('reports 100 when finished with nothing to do', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->phase = PushJob::PHASE_DONE;

        expect($job->percent())->toBe(100);
    });

    it('survives a zero total without dividing by it', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 0, 'files' => 0];

        expect($job->percent())->toBeNull();
    });

    it('exposes progress through the status array', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 4, 'files' => 0];
        $job->counters = ['posts' => 1, 'meta' => 0, 'files' => 0];

        $status = $job->toStatusArray();

        expect($status['percent'])->toBe(25)
            ->and($status['units_done'])->toBe(1)
            ->and($status['units_total'])->toBe(4);
    });

    it('persists totals across a save and reload', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->totals = ['posts' => 42, 'files' => 7];
        $this->pushJobs->save($job);

        expect($this->pushJobs->find($job->id)->totals)->toBe(['posts' => 42, 'files' => 7]);
    });
});

describe('one transfer at a time', function () {
    it('reports a newly created job as active', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');

        expect($this->pushJobs->active()?->id)->toBe($job->id);
    });

    it('reports nothing active once the job finishes', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->phase = PushJob::PHASE_DONE;
        $this->pushJobs->save($job);

        expect($this->pushJobs->active())->toBeNull();
    });

    it('cancels a running job', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');

        expect($this->pushJobs->cancel($job->id))->toBeTrue()
            ->and($this->pushJobs->active())->toBeNull()
            ->and($this->pushJobs->find($job->id)->error)->toBe('Cancelled.');
    });

    it('will not cancel a job that already finished', function () {
        $job = $this->pushJobs->create($this->manifest, 'staging');
        $job->phase = PushJob::PHASE_DONE;
        $this->pushJobs->save($job);

        expect($this->pushJobs->cancel($job->id))->toBeFalse();
    });

    it('will not cancel an unknown job', function () {
        expect($this->pushJobs->cancel('deadbeefdeadbeef'))->toBeFalse();
    });

    it('finds the most recent unfinished job when several were abandoned', function () {
        $first = $this->pushJobs->create($this->manifest, 'staging');
        $second = $this->pushJobs->create($this->manifest, 'staging');

        expect($this->pushJobs->active()?->id)->toBe($second->id)
            ->and($first->id)->not->toBe($second->id);
    });

    it('tracks push and pull jobs independently', function () {
        $this->pullJobs->create($this->pullManifest, 'staging');

        expect($this->pushJobs->active())->toBeNull()
            ->and($this->pullJobs->active())->not->toBeNull();
    });
});
