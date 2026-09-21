<?php

declare(strict_types=1);

use App\Ai\Abilities\ErrorLogAbility;

/**
 * Reading a remote environment's log, and emptying it once read.
 *
 * The clear half is what needs covering: it truncates the only copy of the file, so "keep the last
 * N bytes" and "do not touch anything unless asked" are the behaviours that matter. The read half
 * is covered for the one thing that is easy to get wrong — a log here reaches tens of megabytes
 * between clears, and reading it whole to show twenty lines is how a diagnostic tool becomes the
 * second outage.
 */
function writeLog(string $contents): string
{
    $dir = sys_get_temp_dir().'/rl-log-test-'.bin2hex(random_bytes(4));
    mkdir($dir.'/logs', 0777, true);

    $path = $dir.'/logs/laravel.log';
    file_put_contents($path, $contents);

    // The ability resolves its file through storage_path(), which is what the stub overrides.
    $GLOBALS['rl_test_storage_path'] = $dir;

    return $path;
}

afterEach(function () {
    unset($GLOBALS['rl_test_storage_path']);
});

test('it returns the tail, newest last', function () {
    writeLog(implode("\n", array_map(static fn (int $i): string => "line {$i}", range(1, 500))));

    $out = (new ErrorLogAbility)->execute(['lines' => 3]);

    expect($out['exists'])->toBeTrue()
        ->and($out['lines'])->toBe(['line 498', 'line 499', 'line 500']);
});

test('it filters case-insensitively, so a search does not have to match the log level', function () {
    writeLog("nothing here\nMarketingServiceProvider: the cost alert run FAILED\nalso nothing");

    $out = (new ErrorLogAbility)->execute(['lines' => 100, 'contains' => 'cost alert run failed']);

    expect($out['lines'])->toHaveCount(1)
        ->and($out['lines'][0])->toContain('MarketingServiceProvider');
});

test('reading does not touch the file', function () {
    $path = writeLog("keep\nme\n");

    (new ErrorLogAbility)->execute(['lines' => 10]);

    // Clearing is something you ask for, never a side effect of looking.
    expect(file_get_contents($path))->toBe("keep\nme\n");
});

test('clear empties the file after the content has been read back', function () {
    $path = writeLog("first\nsecond\nthird");

    $out = (new ErrorLogAbility)->execute(['lines' => 10, 'clear' => true]);

    // The caller still gets everything that was there — the point is "this is everything since I
    // last looked", not "this is gone now".
    expect($out['lines'])->toBe(['first', 'second', 'third'])
        ->and($out['cleared'])->toBeTrue()
        ->and($out['size_bytes_after'])->toBe(0)
        ->and(file_get_contents($path))->toBe('');
});

test('keep_bytes leaves the tail behind', function () {
    $path = writeLog(str_repeat('x', 500).'TAIL');

    (new ErrorLogAbility)->execute(['clear' => true, 'keep_bytes' => 4]);

    expect(file_get_contents($path))->toBe('TAIL');
});

test('a missing log is reported rather than thrown', function () {
    $dir = sys_get_temp_dir().'/rl-log-empty-'.bin2hex(random_bytes(4));
    mkdir($dir.'/logs', 0777, true);
    $GLOBALS['rl_test_storage_path'] = $dir;

    $out = (new ErrorLogAbility)->execute([]);

    // An environment that has logged nothing is a normal answer, not a failure.
    expect($out['exists'])->toBeFalse()
        ->and($out['lines'])->toBe([])
        ->and($out['message'])->toContain('No log file');
});
