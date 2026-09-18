<?php

declare(strict_types=1);

use App\Ai\Abilities\UploadMediaAbility;
use App\Ai\Support\MediaUploader;

/**
 * The upload itself is WordPress core (download_url, media_handle_sideload) and
 * is not worth re-testing here. What is worth pinning is the input handling in
 * front of it, because each case below fails silently or destructively rather
 * than loudly:
 *
 *  - a data-URI prefix decodes to bytes that are not the image, so the upload
 *    "succeeds" and puts a corrupt file on a live page;
 *  - a non-http scheme would make the site read its own disk for whoever is
 *    driving the session;
 *  - passing both sources means the caller is confused about what it is
 *    uploading, and guessing puts the wrong image on a live page.
 */
function uploader(): MediaUploader
{
    return new MediaUploader;
}

it('requires exactly one source', function (string $url, string $base64, ?string $expected) {
    expect(uploader()->validateSource($url, $base64))->toBe($expected);
})->with([
    'neither' => ['', '', 'Provide either source_url or data_base64.'],
    'both' => ['https://example.com/a.png', 'aGk=', 'Provide only one of source_url or data_base64, not both.'],
    'url only' => ['https://example.com/a.png', '', null],
    'base64 only' => ['', 'aGk=', null],
]);

it('rejects a source_url that is not http or https', function (string $url, bool $fetchable) {
    expect(uploader()->isFetchableUrl($url))->toBe($fetchable);
})->with([
    'https' => ['https://example.com/a.png', true],
    'http' => ['http://example.com/a.png', true],
    // Would otherwise read the container's own filesystem.
    'file' => ['file:///etc/passwd', false],
    'data' => ['data:image/png;base64,iVBORw0KGgo=', false],
    'no host' => ['https:///a.png', false],
    'bare path' => ['/wp-content/uploads/a.png', false],
]);

/**
 * A model asked for "the base64" very often produces a full data URI. Decoding
 * that verbatim yields the preamble as bytes, which WordPress will happily store
 * as a broken file.
 */
it('strips a data URI preamble before decoding', function (string $given, string $expected) {
    expect(uploader()->stripDataUri($given))->toBe($expected);
})->with([
    'plain base64 untouched' => ['iVBORw0KGgo=', 'iVBORw0KGgo='],
    'png data uri' => ['data:image/png;base64,iVBORw0KGgo=', 'iVBORw0KGgo='],
    'jpeg data uri' => ['data:image/jpeg;base64,/9j/4AAQ', '/9j/4AAQ'],
    'with charset' => ['data:image/svg+xml;charset=utf-8;base64,PHN2Zz4=', 'PHN2Zz4='],
    // A base64 body can itself contain a comma; only the preamble is removed.
    'no preamble but has comma' => ['abc,def', 'abc,def'],
]);

it('falls back to the URL basename when no filename is given', function (mixed $filename, string $url, string $expected) {
    expect(uploader()->filenameCandidate($filename, $url))->toBe($expected);
})->with([
    'explicit wins' => ['hero.png', 'https://example.com/other.jpg', 'hero.png'],
    'from url' => [null, 'https://example.com/media/hero-shot.jpg', 'hero-shot.jpg'],
    'blank is not explicit' => ['  ', 'https://example.com/hero.webp', 'hero.webp'],
    'query string ignored' => [null, 'https://example.com/a/hero.png?v=2', 'hero.png'],
    // basename('/media/') is 'media' — a directory name, not a file.
    'directory url' => [null, 'https://example.com/media/', 'upload'],
    'no path at all' => [null, 'https://example.com', 'upload'],
    'extensionless path' => [null, 'https://example.com/download', 'upload'],
]);

/**
 * The fallback is deliberately extensionless so that a URL which gave us nothing
 * usable fails with an error naming the fix, rather than reaching
 * media_handle_sideload() and coming back as a file-type security refusal.
 */
it('treats a name with no extension as unusable', function (string $filename, bool $usable) {
    expect(uploader()->hasExtension($filename))->toBe($usable);
})->with([
    'jpg' => ['hero-shot.jpg', true],
    'webp' => ['a.webp', true],
    'the fallback itself' => ['upload', false],
    'directory name' => ['media', false],
    'trailing dot' => ['hero.', false],
]);

/**
 * The cap exists because the payload travels inline in a JSON-RPC message. If it
 * were raised far enough to matter, the failure would move from a clear error
 * message to a transport-level truncation with no cause attached.
 */
it('caps an inline upload at 8MB decoded', function () {
    expect(MediaUploader::MAX_DECODED_BYTES)->toBe(8 * 1024 * 1024);
});

it('is exposed to MCP and gated on upload_files', function () {
    $ability = new UploadMediaAbility(uploader());

    expect($ability->meta())->toBe(['mcp' => ['public' => true]]);

    // Deliberately not edit_pages: adding a file to the library is a different
    // act from editing a page, and is granted separately per environment.
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Ai/Abilities/UploadMediaAbility.php');
    expect($source)->toContain("current_user_can('upload_files')");
});

/**
 * Every other MCP ability declares a non-empty input schema for the reason
 * ListPatternsAbility documents at length — an empty one causes a zero-arg call
 * and an ArgumentCountError at runtime, not at boot.
 */
it('declares an input schema with both sources', function () {
    $schema = (new UploadMediaAbility(uploader()))->inputSchema();

    expect($schema['properties'])->toHaveKeys(['source_url', 'data_base64', 'filename', 'alt_text']);
});
