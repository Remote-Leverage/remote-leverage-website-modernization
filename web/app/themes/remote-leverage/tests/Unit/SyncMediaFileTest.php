<?php

declare(strict_types=1);

use App\Domains\Sync\Transfer\Media\MediaFileReceiver;
use App\Domains\Sync\Transfer\UndoLog;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/rl-media-test-'.bin2hex(random_bytes(4));
    mkdir($this->base, 0775, true);

    $this->receiver = new MediaFileReceiver($this->base);
    $this->undoPath = $this->base.'/undo.jsonl';
    $this->undo = new UndoLog($this->undoPath);
});

afterEach(function () {
    $remove = function (string $dir) use (&$remove) {
        foreach (glob($dir.'/*') ?: [] as $item) {
            is_dir($item) ? $remove($item) : @unlink($item);
        }
        @rmdir($dir);
    };
    $remove($this->base);
});

function send(MediaFileReceiver $receiver, string $path, string $contents, ?UndoLog $undo = null): array
{
    return $receiver->writeChunk(
        $path,
        0,
        base64_encode($contents),
        true,
        hash('sha256', $contents),
        $undo,
    );
}

describe('deciding what to send', function () {
    it('asks for a file it does not have', function () {
        $missing = $this->receiver->missing([
            ['path' => '2026/09/globe.webp', 'size' => 3, 'sha256' => hash('sha256', 'abc')],
        ]);

        expect($missing)->toBe(['2026/09/globe.webp']);
    });

    it('does not ask for a file it already has byte for byte', function () {
        send($this->receiver, '2026/09/globe.webp', 'abc');

        $missing = $this->receiver->missing([
            ['path' => '2026/09/globe.webp', 'size' => 3, 'sha256' => hash('sha256', 'abc')],
        ]);

        expect($missing)->toBe([]);
    });

    it('asks again when the content differs', function () {
        send($this->receiver, '2026/09/globe.webp', 'old');

        $missing = $this->receiver->missing([
            ['path' => '2026/09/globe.webp', 'size' => 3, 'sha256' => hash('sha256', 'new')],
        ]);

        expect($missing)->toBe(['2026/09/globe.webp']);
    });

    it('silently skips an unsafe path rather than requesting it', function () {
        $missing = $this->receiver->missing([
            ['path' => '../../wp-config.php', 'size' => 1, 'sha256' => 'x'],
        ]);

        expect($missing)->toBe([]);
    });
});

describe('writing files', function () {
    it('writes a whole file and creates its directory', function () {
        $result = send($this->receiver, '2026/09/globe.webp', 'image-bytes');

        expect($result['complete'])->toBeTrue()
            ->and(file_get_contents($this->base.'/2026/09/globe.webp'))->toBe('image-bytes');
    });

    it('assembles a file from several chunks', function () {
        $contents = str_repeat('a', 10).str_repeat('b', 10);

        $this->receiver->writeChunk('x.webp', 0, base64_encode(str_repeat('a', 10)), false, '');
        $result = $this->receiver->writeChunk(
            'x.webp', 10, base64_encode(str_repeat('b', 10)), true, hash('sha256', $contents)
        );

        expect($result['complete'])->toBeTrue()
            ->and(file_get_contents($this->base.'/x.webp'))->toBe($contents);
    });

    it('leaves nothing in place when the checksum does not match', function () {
        $attempt = fn () => $this->receiver->writeChunk(
            'x.webp', 0, base64_encode('corrupted'), true, hash('sha256', 'expected')
        );

        expect($attempt)->toThrow(RuntimeException::class, 'Checksum mismatch');
        expect(is_file($this->base.'/x.webp'))->toBeFalse();
    });

    it('refuses a traversal path', function () {
        $attempt = fn () => send($this->receiver, '../escaped.webp', 'x');

        expect($attempt)->toThrow(InvalidArgumentException::class);
    });

    it('refuses an executable extension', function () {
        $attempt = fn () => send($this->receiver, 'shell.php', '<?php');

        expect($attempt)->toThrow(InvalidArgumentException::class);
    });

    it('rejects malformed base64', function () {
        $attempt = fn () => $this->receiver->writeChunk('x.webp', 0, '!!!not base64!!!', true, '');

        expect($attempt)->toThrow(RuntimeException::class, 'valid base64');
    });

    it('restarts a retried file rather than appending to the previous attempt', function () {
        $this->receiver->writeChunk('x.webp', 0, base64_encode('first attempt'), false, '');
        send($this->receiver, 'x.webp', 'second');

        expect(file_get_contents($this->base.'/x.webp'))->toBe('second');
    });
});

describe('undoing file writes', function () {
    it('deletes a file the transfer added', function () {
        send($this->receiver, '2026/09/new.webp', 'bytes', $this->undo);
        expect(is_file($this->base.'/2026/09/new.webp'))->toBeTrue();

        $this->undo->rollback();

        expect(is_file($this->base.'/2026/09/new.webp'))->toBeFalse();
    });

    it('restores a file the transfer replaced', function () {
        send($this->receiver, 'x.webp', 'original');
        send($this->receiver, 'x.webp', 'replaced', $this->undo);

        expect(file_get_contents($this->base.'/x.webp'))->toBe('replaced');

        $this->undo->rollback();

        expect(file_get_contents($this->base.'/x.webp'))->toBe('original');
    });

    it('records nothing when no log is given', function () {
        send($this->receiver, 'x.webp', 'bytes');

        expect($this->undo->exists())->toBeFalse();
    });
});
