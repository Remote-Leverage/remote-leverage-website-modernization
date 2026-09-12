<?php

declare(strict_types=1);

use App\Domains\Sync\Transfer\Import\AttachmentIdMap;
use App\Domains\Sync\Transfer\Import\AttachmentReferenceRewriter;

beforeEach(function () {
    $this->rewriter = new AttachmentReferenceRewriter;
    $this->map = AttachmentIdMap::fromArray([506 => 9001, 12 => 34]);
});

describe('attachment id map', function () {
    it('returns the original id when nothing was remapped', function () {
        expect($this->map->resolve(777))->toBe(777);
    });

    it('ignores a mapping of an id to itself', function () {
        $map = AttachmentIdMap::fromArray([5 => 5]);

        expect($map->isEmpty())->toBeTrue();
    });

    it('ignores non-positive ids', function () {
        $map = AttachmentIdMap::fromArray([0 => 9, -1 => 9, 4 => 0]);

        expect($map->isEmpty())->toBeTrue();
    });

    it('counts only real remappings', function () {
        expect($this->map->count())->toBe(2);
    });
});

describe('content rewriting', function () {
    it('does nothing at all when the map is empty', function () {
        $content = '<img class="wp-image-506"> {"id":506}';

        expect($this->rewriter->rewriteContent($content, AttachmentIdMap::fromArray([])))
            ->toBe($content);
    });

    it('rewrites the wp-image class', function () {
        expect($this->rewriter->rewriteContent('<img class="foo wp-image-506 bar">', $this->map))
            ->toBe('<img class="foo wp-image-9001 bar">');
    });

    it('leaves an unmapped wp-image class alone', function () {
        expect($this->rewriter->rewriteContent('<img class="wp-image-777">', $this->map))
            ->toBe('<img class="wp-image-777">');
    });

    it('rewrites single media id block attributes', function () {
        $content = '<!-- wp:image {"id":506,"sizeSlug":"large"} -->';

        expect($this->rewriter->rewriteContent($content, $this->map))
            ->toBe('<!-- wp:image {"id":9001,"sizeSlug":"large"} -->');
    });

    it('rewrites every media id key variant', function () {
        $content = '{"mediaId":506,"imageId":12,"backgroundId":506}';

        expect($this->rewriter->rewriteContent($content, $this->map))
            ->toBe('{"mediaId":9001,"imageId":34,"backgroundId":9001}');
    });

    it('rewrites gallery id lists', function () {
        $content = '<!-- wp:gallery {"ids":[506,777,12]} -->';

        expect($this->rewriter->rewriteContent($content, $this->map))
            ->toBe('<!-- wp:gallery {"ids":[9001,777,34]} -->');
    });

    it('does not rewrite an integer that merely equals a mapped id', function () {
        // "506" here is a count and a price, not an attachment reference.
        $content = '<!-- wp:acf/stats {"data":{"count":"506","price":506}} -->';

        expect($this->rewriter->rewriteContent($content, $this->map))->toBe($content);
    });

    it('does not touch a post id that collides with a mapped attachment id', function () {
        $content = '<!-- wp:pattern {"postId":506} -->';

        expect($this->rewriter->rewriteContent($content, $this->map))->toBe($content);
    });
});

describe('meta rewriting', function () {
    it('rewrites _thumbnail_id', function () {
        expect($this->rewriter->rewriteMetaValue('506', '_thumbnail_id', $this->map))->toBe('9001');
    });

    it('preserves the integer type it was given', function () {
        expect($this->rewriter->rewriteMetaValue(506, '_thumbnail_id', $this->map))->toBe(9001);
    });

    it('leaves meta under an undeclared key untouched', function () {
        expect($this->rewriter->rewriteMetaValue('506', 'some_count', $this->map))->toBe('506');
    });

    it('rewrites a declared ACF image field', function () {
        expect($this->rewriter->rewriteMetaValue('506', 'hero_image', $this->map, ['hero_image']))
            ->toBe('9001');
    });

    it('rewrites ids inside a serialized declared field', function () {
        $value = serialize(['506', '777', 12]);

        expect($this->rewriter->rewriteMetaValue($value, 'gallery', $this->map, ['gallery']))
            ->toBe(serialize(['9001', '777', 34]));
    });

    it('preserves string and int types through a serialized rewrite', function () {
        $result = $this->rewriter->rewriteMetaValue(serialize(['506', 12]), 'g', $this->map, ['g']);
        $decoded = unserialize($result);

        expect($decoded[0])->toBeString()->toBe('9001')
            ->and($decoded[1])->toBeInt()->toBe(34);
    });

    it('leaves serialized object payloads untouched rather than rewriting them', function () {
        // allowed_classes:false means no object is ever instantiated from
        // imported data; the payload is handed back as-is rather than
        // re-serialized, so nothing inside it can be corrupted.
        $payload = 'O:8:"stdClass":1:{s:3:"foo";i:506;}';

        expect($this->rewriter->rewriteMetaValue($payload, 'g', $this->map, ['g']))
            ->toBe($payload);
    });

    it('leaves an object nested inside an array untouched', function () {
        $payload = 'a:1:{s:3:"obj";O:8:"stdClass":1:{s:2:"id";i:506;}}';

        expect($this->rewriter->rewriteMetaValue($payload, 'g', $this->map, ['g']))
            ->toBe($payload);
    });

    it('still rewrites a serialized false without treating it as malformed', function () {
        expect($this->rewriter->rewriteMetaValue(serialize(false), 'g', $this->map, ['g']))
            ->toBe(serialize(false));
    });

    it('returns malformed serialized data unchanged', function () {
        expect($this->rewriter->rewriteMetaValue('a:9:{broken', 'g', $this->map, ['g']))
            ->toBe('a:9:{broken');
    });

    it('does nothing when the map is empty', function () {
        expect($this->rewriter->rewriteMetaValue('506', '_thumbnail_id', AttachmentIdMap::fromArray([])))
            ->toBe('506');
    });
});
