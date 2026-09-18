<?php

declare(strict_types=1);

namespace App\Ai\Support;

/**
 * Puts a file into the WordPress media library and returns its attachment id.
 *
 * Exists because every image on a landing page is addressed by attachment id —
 * describe-page reports the field as `image_id` and update-page-sections writes
 * one — so without an upload path a marketing session can only ever point at art
 * somebody already added by hand in wp-admin. That was the one step in "clone
 * this page and swap the imagery" that could not be done by asking.
 *
 * The two accepted sources are deliberate:
 *
 *  - `source_url` is what a session normally has (an image already on the site,
 *    a signed Drive link, a stock photo) and costs nothing to pass along.
 *  - `data_base64` is the fallback for a file that exists only on the editor's
 *    machine. It is capped, because it travels inline inside a JSON-RPC message.
 *
 * What may be uploaded is not decided here. media_handle_sideload() runs the
 * file through WordPress's own mime allowlist and the uploading user's
 * upload_files capability, so this class deliberately adds no second opinion
 * about file types — one allowlist that WordPress already enforces everywhere
 * beats two that can disagree.
 */
class MediaUploader
{
    /**
     * Ceiling on a base64 payload, measured after decoding.
     *
     * MCP carries this inline in a JSON-RPC message and base64 inflates by
     * roughly a third on the wire, so this is already an uncomfortable amount of
     * text for a model to emit in one message. A file larger than this is a job
     * for wp-admin, and the error says so rather than failing at the transport
     * layer where the cause would be invisible.
     */
    public const MAX_DECODED_BYTES = 8388608;

    /**
     * Upload one file and return a row describing the attachment it became.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function upload(array $input): array
    {
        $sourceUrl = trim((string) ($input['source_url'] ?? ''));
        $base64 = trim((string) ($input['data_base64'] ?? ''));

        if ($error = $this->validateSource($sourceUrl, $base64)) {
            return ['ok' => false, 'error' => $error];
        }

        $this->loadWordPressUploadStack();

        $temporary = $sourceUrl !== ''
            ? $this->fetchUrl($sourceUrl)
            : $this->writeDecoded($base64);

        if (isset($temporary['error'])) {
            return ['ok' => false, 'error' => $temporary['error']];
        }

        $filename = sanitize_file_name(
            $this->filenameCandidate($input['filename'] ?? null, $sourceUrl)
        );

        if (! $this->hasExtension($filename)) {
            @unlink($temporary['path']);

            return [
                'ok' => false,
                'error' => 'Could not tell what kind of file this is. Pass filename including the extension, '
                    .'for example "hero-shot.jpg".',
            ];
        }

        $attachmentId = media_handle_sideload(
            ['name' => $filename, 'tmp_name' => $temporary['path']],
            0,
            $this->nullIfBlank($input['title'] ?? null),
        );

        if (is_wp_error($attachmentId)) {
            // media_handle_sideload unlinks tmp_name on success only, so a
            // rejected upload would otherwise leave the file behind.
            @unlink($temporary['path']);

            return ['ok' => false, 'error' => $attachmentId->get_error_message()];
        }

        $attachmentId = (int) $attachmentId;

        $this->applyMetadata($attachmentId, $input);

        return $this->describe($attachmentId);
    }

    /**
     * Exactly one source, and it has to be one this can actually fetch.
     *
     * Both-or-neither is rejected rather than silently preferring one, because a
     * session that passed both has a mistaken idea of what it is uploading and
     * picking for it would put the wrong image on a live page.
     */
    public function validateSource(string $sourceUrl, string $base64): ?string
    {
        if ($sourceUrl === '' && $base64 === '') {
            return 'Provide either source_url or data_base64.';
        }

        if ($sourceUrl !== '' && $base64 !== '') {
            return 'Provide only one of source_url or data_base64, not both.';
        }

        if ($sourceUrl !== '' && ! $this->isFetchableUrl($sourceUrl)) {
            return 'source_url must be an http or https URL.';
        }

        return null;
    }

    /**
     * Only http(s). Without this, a file:// or data: URL would make the site
     * read its own disk on behalf of whoever is driving the session.
     */
    public function isFetchableUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && parse_url($url, PHP_URL_HOST) !== null;
    }

    /**
     * Strip a data-URI preamble if one came along.
     *
     * A model asked for "the base64" reasonably often produces
     * `data:image/png;base64,iVBOR…`, and decoding that verbatim yields bytes
     * that are not the image. Accepting both spellings is cheaper than an error
     * message nobody reads.
     */
    public function stripDataUri(string $data): string
    {
        if (preg_match('#^data:[^;,]*(;[^;,]+)*;base64,#i', $data, $matches) === 1) {
            return substr($data, strlen($matches[0]));
        }

        return $data;
    }

    /**
     * The name the file should land under, before sanitising.
     *
     * Falls back to the URL's own basename so a caller that passes a plain URL
     * still gets something recognisable in the library rather than a generic
     * name that makes the media grid useless.
     */
    public function filenameCandidate(mixed $filename, string $sourceUrl): string
    {
        $given = trim((string) ($filename ?? ''));

        if ($given !== '') {
            return $given;
        }

        $path = (string) parse_url($sourceUrl, PHP_URL_PATH);
        $base = trim(basename($path));

        // basename('/media/') is 'media', not '' — so a URL ending in a slash
        // yields a directory name with no extension, which WordPress rejects
        // with a security message that says nothing about the real cause.
        // Requiring a dot keeps that case on the explicit path below.
        return str_contains($base, '.') ? $base : 'upload';
    }

    /**
     * WordPress decides what a file is from its extension, so a name without one
     * is refused by media_handle_sideload() as a disallowed file type — an error
     * that sends the reader looking at mime allowlists rather than at the name.
     */
    public function hasExtension(string $filename): bool
    {
        return (string) pathinfo($filename, PATHINFO_EXTENSION) !== '';
    }

    /**
     * @return array{path?: string, error?: string}
     */
    private function fetchUrl(string $url): array
    {
        $path = download_url($url);

        if (is_wp_error($path)) {
            return ['error' => 'Could not download source_url: '.$path->get_error_message()];
        }

        return ['path' => (string) $path];
    }

    /**
     * @return array{path?: string, error?: string}
     */
    private function writeDecoded(string $base64): array
    {
        $decoded = base64_decode($this->stripDataUri($base64), true);

        if ($decoded === false) {
            return ['error' => 'data_base64 is not valid base64.'];
        }

        if ($decoded === '') {
            return ['error' => 'data_base64 decoded to an empty file.'];
        }

        if (strlen($decoded) > self::MAX_DECODED_BYTES) {
            return ['error' => sprintf(
                'The decoded file is %s, over the %s limit for an inline upload. Upload it in wp-admin instead, or pass a source_url.',
                size_format(strlen($decoded)),
                size_format(self::MAX_DECODED_BYTES),
            )];
        }

        $path = wp_tempnam();

        if (! $path || file_put_contents($path, $decoded) === false) {
            return ['error' => 'Could not write the decoded file to a temporary location.'];
        }

        return ['path' => $path];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyMetadata(int $attachmentId, array $input): void
    {
        // Alt text is a separate postmeta row rather than part of the
        // attachment post, so media_handle_sideload cannot set it.
        if ($alt = $this->nullIfBlank($input['alt_text'] ?? null)) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
        }

        $caption = $this->nullIfBlank($input['caption'] ?? null);
        $description = $this->nullIfBlank($input['description'] ?? null);

        if ($caption === null && $description === null) {
            return;
        }

        $update = ['ID' => $attachmentId];

        if ($caption !== null) {
            $update['post_excerpt'] = $caption;
        }

        if ($description !== null) {
            $update['post_content'] = $description;
        }

        wp_update_post($update);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(int $attachmentId): array
    {
        $metadata = wp_get_attachment_metadata($attachmentId);

        return [
            'ok' => true,
            'attachment_id' => $attachmentId,
            'url' => (string) wp_get_attachment_url($attachmentId),
            'filename' => basename((string) get_attached_file($attachmentId)),
            'mime_type' => (string) get_post_mime_type($attachmentId),
            'width' => isset($metadata['width']) ? (int) $metadata['width'] : null,
            'height' => isset($metadata['height']) ? (int) $metadata['height'] : null,
            'alt_text' => (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true),
            'title' => get_the_title($attachmentId),
        ];
    }

    /**
     * media_handle_sideload() and wp_update_post() both treat '' as "set this
     * field to empty" rather than "leave it alone", so a blank has to become a
     * null before it reaches them.
     */
    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * media_handle_sideload() and download_url() live in wp-admin includes,
     * which are not loaded on a REST request — the usual cause of a fatal in
     * code that works when tried in wp-admin.
     */
    private function loadWordPressUploadStack(): void
    {
        foreach (['file.php', 'media.php', 'image.php'] as $include) {
            require_once ABSPATH.'wp-admin/includes/'.$include;
        }
    }
}
