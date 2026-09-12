<?php

declare(strict_types=1);

namespace App\Domains\Sync\Transfer\Import;

/**
 * Rewrites references to remapped attachment IDs in imported content.
 *
 * Scope is deliberately narrow. A blanket "replace every integer that appears
 * in the map" pass would corrupt unrelated data — a serialized value holding
 * the number 47 as a count, not an attachment ID, would be silently rewritten.
 * So this only touches places where an integer is unambiguously an attachment
 * reference:
 *
 *  - the `wp-image-<id>` class WordPress puts on inserted images
 *  - block attribute keys that are always media IDs (`id`, `ids`, `mediaId`, …)
 *  - postmeta under keys the caller declares to be attachment references
 *
 * Known gap: ACF image fields store a bare attachment ID under the field's own
 * name, and nothing in the row says it is an image — resolving that needs the
 * field group definition. Pass those key names via $attachmentMetaKeys when you
 * know them; they are not auto-detected.
 *
 * Nothing here runs at all when the map is empty, which is the usual case.
 */
class AttachmentReferenceRewriter
{
    /**
     * Block attribute keys whose value is always a single media ID.
     *
     * @var array<int, string>
     */
    private const SINGLE_ID_KEYS = ['id', 'mediaId', 'imageId', 'backgroundId', 'iconId', 'logoId'];

    /**
     * Block attribute keys whose value is an array of media IDs.
     *
     * @var array<int, string>
     */
    private const ID_LIST_KEYS = ['ids', 'mediaIds', 'imageIds'];

    /**
     * Postmeta keys that always hold a bare attachment ID.
     *
     * @var array<int, string>
     */
    private const DEFAULT_META_KEYS = ['_thumbnail_id'];

    /**
     * Rewrite every attachment reference in a post_content body.
     */
    public function rewriteContent(string $content, AttachmentIdMap $map): string
    {
        if ($map->isEmpty() || $content === '') {
            return $content;
        }

        $content = $this->rewriteImageClasses($content, $map);
        $content = $this->rewriteSingleIdAttributes($content, $map);

        return $this->rewriteIdListAttributes($content, $map);
    }

    /**
     * Rewrite a postmeta value, when its key marks it as an attachment
     * reference. Values under any other key are returned untouched.
     *
     * @param  array<int, string>  $attachmentMetaKeys  Extra keys beyond _thumbnail_id.
     */
    public function rewriteMetaValue(
        mixed $value,
        string $metaKey,
        AttachmentIdMap $map,
        array $attachmentMetaKeys = [],
    ): mixed {
        if ($map->isEmpty()) {
            return $value;
        }

        $keys = array_merge(self::DEFAULT_META_KEYS, $attachmentMetaKeys);

        if (! in_array($metaKey, $keys, true)) {
            return $value;
        }

        if (is_int($value)) {
            return $map->resolve($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        // A bare integer string — the shape _thumbnail_id and ACF image fields use.
        if (preg_match('/^\d+$/', $value) === 1) {
            return (string) $map->resolve((int) $value);
        }

        return $this->rewriteSerialized($value, $map);
    }

    /**
     * Walk a serialized structure and remap its integer leaves.
     *
     * Only reached for meta keys the caller has already declared to be
     * attachment references, so remapping every integer inside is safe here in
     * a way it would not be across arbitrary meta.
     *
     * Unserializes without allowing objects: imported content is data from
     * another environment, and instantiating arbitrary classes from it would be
     * an object-injection sink.
     */
    private function rewriteSerialized(string $value, AttachmentIdMap $map): string
    {
        if (! $this->looksSerialized($value)) {
            return $value;
        }

        $data = $this->safeUnserialize($value);

        if ($data === null) {
            return $value;
        }

        // allowed_classes:false turns any serialized object into an incomplete
        // class rather than instantiating it. Re-serializing one reproduces the
        // original payload, so there is nothing to gain and a corruption risk to
        // take — hand the untouched string back instead.
        if ($this->containsObject($data)) {
            return $value;
        }

        return serialize($this->remapLeaves($data, $map));
    }

    /**
     * Unserialize without letting a malformed payload raise a warning.
     *
     * Imported data is arbitrary bytes from another environment; a broken row
     * should be skipped quietly, not surface as a PHP warning in the middle of
     * an import. Returns null when the value cannot be read.
     */
    private function safeUnserialize(string $value): mixed
    {
        set_error_handler(static fn () => true);

        try {
            $data = unserialize($value, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        return $data === false && $value !== serialize(false) ? null : $data;
    }

    private function containsObject(mixed $data): bool
    {
        if (is_object($data)) {
            return true;
        }

        if (! is_array($data)) {
            return false;
        }

        foreach ($data as $item) {
            if ($this->containsObject($item)) {
                return true;
            }
        }

        return false;
    }

    private function remapLeaves(mixed $data, AttachmentIdMap $map): mixed
    {
        if (is_array($data)) {
            $out = [];

            foreach ($data as $key => $item) {
                $out[$key] = $this->remapLeaves($item, $map);
            }

            return $out;
        }

        if (is_int($data)) {
            return $map->resolve($data);
        }

        if (is_string($data) && preg_match('/^\d+$/', $data) === 1) {
            $resolved = $map->resolve((int) $data);

            return $resolved === (int) $data ? $data : (string) $resolved;
        }

        return $data;
    }

    private function looksSerialized(string $value): bool
    {
        return preg_match('/^[aOsbidN]:/', $value) === 1;
    }

    private function rewriteImageClasses(string $content, AttachmentIdMap $map): string
    {
        return (string) preg_replace_callback(
            '/\bwp-image-(\d+)\b/',
            fn (array $m) => 'wp-image-'.$map->resolve((int) $m[1]),
            $content,
        );
    }

    private function rewriteSingleIdAttributes(string $content, AttachmentIdMap $map): string
    {
        $keys = implode('|', array_map('preg_quote', self::SINGLE_ID_KEYS));

        return (string) preg_replace_callback(
            '/"('.$keys.')"\s*:\s*(\d+)/',
            fn (array $m) => '"'.$m[1].'":'.$map->resolve((int) $m[2]),
            $content,
        );
    }

    private function rewriteIdListAttributes(string $content, AttachmentIdMap $map): string
    {
        $keys = implode('|', array_map('preg_quote', self::ID_LIST_KEYS));

        return (string) preg_replace_callback(
            '/"('.$keys.')"\s*:\s*\[([\d,\s]*)\]/',
            function (array $m) use ($map) {
                $ids = array_filter(array_map('trim', explode(',', $m[2])), fn ($v) => $v !== '');
                $remapped = array_map(fn ($id) => (string) $map->resolve((int) $id), $ids);

                return '"'.$m[1].'":['.implode(',', $remapped).']';
            },
            $content,
        );
    }
}
