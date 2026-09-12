<?php

declare(strict_types=1);

use App\Domains\Sync\Transfer\Media\UploadPath;

describe('accepted paths', function () {
    it('accepts an ordinary upload path', function () {
        expect(UploadPath::from('2026/09/globe.webp')->relative)->toBe('2026/09/globe.webp');
    });

    it('accepts a bare filename', function () {
        expect(UploadPath::from('globe.webp')->relative)->toBe('globe.webp');
    });

    it('accepts WordPress size suffixes', function () {
        expect(UploadPath::isValid('2026/09/globe-248x300.webp'))->toBeTrue();
    });

    it('normalises backslashes', function () {
        expect(UploadPath::from('2026\\09\\globe.webp')->relative)->toBe('2026/09/globe.webp');
    });

    it('reports its directory', function () {
        expect(UploadPath::from('2026/09/globe.webp')->directory())->toBe('2026/09')
            ->and(UploadPath::from('globe.webp')->directory())->toBe('');
    });
});

describe('rejected paths', function () {
    it('refuses traversal', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with([
        '../wp-config.php',
        '../../wp-config.php',
        '2026/../../../etc/passwd',
        '2026/09/../../../wp-config.php',
        './../secrets.json',
    ]);

    it('refuses absolute paths', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with(['/etc/passwd', '/var/www/html/wp-config.php', 'C:/Windows/system.ini']);

    it('refuses stream wrappers', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with(['php://filter/resource=x.png', 'https://evil.test/x.png', 'data://text/plain,x.png']);

    it('refuses a null byte', function () {
        expect(UploadPath::isValid("2026/09/globe.webp\0.php"))->toBeFalse();
    });

    it('refuses executable extensions', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with([
        '2026/09/shell.php',
        '2026/09/shell.phtml',
        '2026/09/shell.php5',
        'x.sh',
        'x.htaccess',
        '.htaccess',
    ]);

    it('refuses a file with no extension', function () {
        expect(UploadPath::isValid('2026/09/globe'))->toBeFalse();
    });

    it('refuses empty and whitespace paths', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with(['', '   ', '/', '//']);

    it('refuses unsafe characters', function (string $path) {
        expect(UploadPath::isValid($path))->toBeFalse();
    })->with(['2026/09/a b.webp', '2026/09/a;rm -rf.webp', '2026/09/a$(x).webp', '2026/09/a|b.webp']);
});

describe('resolving under a base', function () {
    it('joins onto the uploads base', function () {
        expect(UploadPath::from('2026/09/globe.webp')->absoluteUnder('/var/uploads'))
            ->toBe('/var/uploads/2026/09/globe.webp');
    });

    it('tolerates a trailing slash on the base', function () {
        expect(UploadPath::from('globe.webp')->absoluteUnder('/var/uploads/'))
            ->toBe('/var/uploads/globe.webp');
    });

    it('never resolves outside the base', function () {
        $resolved = UploadPath::from('2026/09/globe.webp')->absoluteUnder('/var/uploads');

        expect($resolved)->toStartWith('/var/uploads/');
    });
});
