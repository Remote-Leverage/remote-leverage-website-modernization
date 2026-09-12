<?php

declare(strict_types=1);

use App\Domains\Sync\Datasets\DatasetRegistry;
use App\Domains\Sync\Transfer\InvalidManifestException;
use App\Domains\Sync\Transfer\TransferManifest;

beforeEach(function () {
    $this->registry = new DatasetRegistry;
});

describe('dataset catalogue', function () {
    it('never exposes comments or migrations as syncable', function () {
        $tables = [];

        foreach ($this->registry->all() as $dataset) {
            $tables = array_merge($tables, $dataset->tables);
        }

        expect($tables)->not->toContain('comments')
            ->and($tables)->not->toContain('commentmeta')
            ->and($tables)->not->toContain('migrations');
    });

    it('marks migrations and comments as never synced', function () {
        expect($this->registry->isNeverSynced('migrations'))->toBeTrue()
            ->and($this->registry->isNeverSynced('comments'))->toBeTrue()
            ->and($this->registry->isNeverSynced('commentmeta'))->toBeTrue()
            ->and($this->registry->isNeverSynced('posts'))->toBeFalse();
    });

    it('allows only content, media and settings to transfer', function () {
        expect(array_keys($this->registry->transferable()))
            ->toEqualCanonicalizing([
                DatasetRegistry::CONTENT,
                DatasetRegistry::MEDIA,
                DatasetRegistry::SETTINGS,
            ]);
    });

    it('allows only real-data groups to be purged', function () {
        expect(array_keys($this->registry->purgeable()))
            ->toEqualCanonicalizing([
                DatasetRegistry::LEADS,
                DatasetRegistry::REFERRALS,
                DatasetRegistry::SCHEDULING,
            ]);
    });

    it('lets users be neither transferred nor purged', function () {
        $users = $this->registry->get(DatasetRegistry::USERS);

        expect($users->transferable)->toBeFalse()
            ->and($users->purgeable)->toBeFalse();
    });

    it('selects content and media by default', function () {
        expect($this->registry->defaultSelection())
            ->toEqualCanonicalizing([DatasetRegistry::CONTENT, DatasetRegistry::MEDIA]);
    });

    it('applies the install prefix to table names', function () {
        expect($this->registry->get(DatasetRegistry::LEADS)->prefixedTables('wp_'))
            ->toBe(['wp_rl_leads', 'wp_rl_lead_activity_logs']);
    });

    it('rejects an unknown dataset key', function () {
        $this->registry->get('nope');
    })->throws(InvalidArgumentException::class);
});

describe('transfer manifest', function () {
    it('builds from a valid selection', function () {
        $manifest = TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'media'],
            'clean_before_import' => ['content'],
            'excluded_post_types' => ['case_study'],
            'excluded_post_ids' => ['7', 12],
        ], $this->registry);

        expect($manifest->isPush())->toBeTrue()
            ->and($manifest->includes('content'))->toBeTrue()
            ->and($manifest->shouldClean('content'))->toBeTrue()
            ->and($manifest->shouldClean('media'))->toBeFalse()
            ->and($manifest->excludedPostIds)->toBe([7, 12]);
    });

    it('refuses a dataset that is purge-only', function () {
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'leads'],
        ], $this->registry);
    })->throws(InvalidManifestException::class, 'never transferred');

    it('refuses to transfer users even though they are a known dataset', function () {
        TransferManifest::fromArray([
            'direction' => 'pull',
            'datasets' => ['users'],
        ], $this->registry);
    })->throws(InvalidManifestException::class);

    it('refuses an unknown dataset', function () {
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'wp_options_all'],
        ], $this->registry);
    })->throws(InvalidManifestException::class, 'Unknown sync dataset');

    it('refuses an invalid direction', function (mixed $direction) {
        TransferManifest::fromArray([
            'direction' => $direction,
            'datasets' => ['content'],
        ], $this->registry);
    })->throws(InvalidManifestException::class)->with(['sideways', '', 'PUSH', null]);

    it('refuses an empty selection', function () {
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => [],
        ], $this->registry);
    })->throws(InvalidManifestException::class, 'at least one dataset');

    it('refuses cleaning a dataset that is not being transferred', function () {
        TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content'],
            'clean_before_import' => ['media'],
        ], $this->registry);
    })->throws(InvalidManifestException::class, 'not one of the selected datasets');

    it('normalises duplicate and blank exclusions', function () {
        $manifest = TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'content'],
            'excluded_post_types' => ['page', ' page ', '', 'post'],
            'excluded_post_ids' => [5, '5', 0, -3, 'abc'],
        ], $this->registry);

        expect($manifest->datasets)->toBe(['content'])
            ->and($manifest->excludedPostTypes)->toBe(['page', 'post'])
            ->and($manifest->excludedPostIds)->toBe([5]);
    });

    it('flags content without media as a dangling-reference risk', function () {
        $manifest = TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content'],
        ], $this->registry);

        expect($manifest->hasDanglingMediaRisk())->toBeTrue();
    });

    it('does not flag a risk when media travels with content', function () {
        $manifest = TransferManifest::fromArray([
            'direction' => 'push',
            'datasets' => ['content', 'media'],
        ], $this->registry);

        expect($manifest->hasDanglingMediaRisk())->toBeFalse();
    });

    it('round-trips through toArray', function () {
        $input = [
            'direction' => 'pull',
            'datasets' => ['content'],
            'excluded_post_types' => ['page'],
            'excluded_post_ids' => [9],
            'excluded_taxonomies' => ['category'],
            'clean_before_import' => ['content'],
        ];

        $manifest = TransferManifest::fromArray($input, $this->registry);

        expect(TransferManifest::fromArray($manifest->toArray(), $this->registry)->toArray())
            ->toBe($manifest->toArray());
    });
});
