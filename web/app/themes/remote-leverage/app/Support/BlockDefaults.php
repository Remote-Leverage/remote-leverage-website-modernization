<?php

declare(strict_types=1);

namespace App\Support;

class BlockDefaults
{
    /**
     * Register ACF hooks to ensure blocks have their demo content editable.
     */
    public static function init(): void
    {
        add_filter('acf/load_value', [self::class, 'filterLoadValue'], 10, 3);
        add_filter('acf/format_value/type=image', [self::class, 'filterImageFormatValue'], 20, 3);
        add_filter('wp_content_img_tag', [self::class, 'filterContentImgTag']);
        add_filter('the_content', [self::class, 'rewriteLocalAbsoluteUrls'], 1);
        add_filter('the_excerpt', [self::class, 'rewriteLocalAbsoluteUrls'], 1);
        add_filter('the_content', [self::class, 'repairStarRatingAria'], 2);
        add_filter('the_excerpt', [self::class, 'repairStarRatingAria'], 2);
        add_filter('wp_get_attachment_url', [self::class, 'rewriteLocalAbsoluteUrls']);
    }

    /**
     * Gutenberg patterns saved locally bake in http://remoteleverage-v2.test (and
     * similar) absolute URLs. Rewrite them to the current home URL so staging
     * does not emit mixed-content http requests (PageSpeed Best Practices).
     */
    public static function rewriteLocalAbsoluteUrls(mixed $html, mixed $home = null): string
    {
        if (! is_string($html) || $html === '') {
            return is_string($html) ? $html : '';
        }

        $homeUrl = is_string($home) && preg_match('#^https?://#', $home) === 1
            ? $home
            : (function_exists('home_url') ? home_url() : '');
        $homeUrl = rtrim((string) $homeUrl, '/');
        if ($homeUrl === '') {
            return $html;
        }

        $from = [
            'http://remoteleverage-v2.test',
            'https://remoteleverage-v2.test',
            'http://127.0.0.1:8080',
            'https://127.0.0.1:8080',
            'http://localhost:8080',
            'https://localhost:8080',
        ];

        $html = str_replace($from, $homeUrl, $html);

        $host = parse_url($homeUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '' && str_starts_with($homeUrl, 'https://')) {
            $html = str_replace('http://'.$host, 'https://'.$host, $html);
        }

        return $html;
    }

    /**
     * aria-label is not allowed on a generic div. Saved Gutenberg HTML from
     * the trust-and-impact pattern used that; add role="img" so the name is valid.
     */
    public static function repairStarRatingAria(mixed $html): string
    {
        if (! is_string($html) || $html === '') {
            return is_string($html) ? $html : '';
        }

        return str_replace(
            '<div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" aria-label="5 out of 5 stars">',
            '<div style="display:flex;gap:4px;color:#9F53E7;margin-bottom:1rem;" role="img" aria-label="5 out of 5 stars">',
            $html,
        );
    }

    /**
     * Rewrite <img> tags in rendered post content (e.g. block/pattern content that was
     * saved with a .png/.jpg src before a .webp sibling existed) to prefer WebP.
     */
    public static function filterContentImgTag(string $filteredImage): string
    {
        $image = $filteredImage;

        if (preg_match('/\ssrc=(["\'])(.*?)\1/i', $image, $m)) {
            $originalSrc = html_entity_decode($m[2]);
            $newSrc = self::preferWebp($originalSrc);

            if ($newSrc !== $originalSrc) {
                $image = str_replace($m[0], ' src='.$m[1].esc_attr($newSrc).$m[1], $image);
            }
        }

        // A matching srcset candidate always outranks src, so rewriting src alone did
        // nothing for any content image WordPress had already given a srcset — the browser
        // went on downloading the PNG and the WebP was never requested.
        //
        // Splitting on "," is safe here because sanitize_file_name() strips commas from
        // every uploaded filename, so no candidate URL can contain one.
        if (preg_match('/\ssrcset=(["\'])(.*?)\1/i', $image, $m)) {
            $candidates = [];

            foreach (explode(',', html_entity_decode($m[2])) as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '') {
                    continue;
                }

                $parts = preg_split('/\s+/', $candidate, 2);
                $url = self::preferWebp($parts[0]);

                $candidates[] = isset($parts[1]) ? $url.' '.$parts[1] : $url;
            }

            if ($candidates !== []) {
                $image = str_replace($m[0], ' srcset='.$m[1].esc_attr(implode(', ', $candidates)).$m[1], $image);
            }
        }

        return $image;
    }

    /**
     * Map a local site URL (theme public dir or wp-content/uploads) back to its filesystem path.
     */
    public static function urlToPath(string $url): ?string
    {
        $url = set_url_scheme($url, 'https');

        $bases = [
            rtrim(set_url_scheme(content_url(), 'https'), '/') => rtrim(WP_CONTENT_DIR, '/'),
            rtrim(set_url_scheme(get_template_directory_uri(), 'https'), '/') => rtrim(get_theme_file_path(), '/'),
        ];

        foreach ($bases as $baseUrl => $baseDir) {
            if ($baseUrl !== '' && str_starts_with($url, $baseUrl)) {
                return $baseDir.substr($url, strlen($baseUrl));
            }
        }

        return null;
    }

    /**
     * Given a local image URL, return its .webp equivalent when one exists (or can be generated),
     * otherwise return the URL unchanged. Never throws — a failed conversion just skips optimization.
     */
    public static function preferWebp(string $url): string
    {
        if (empty($url)) {
            return $url;
        }

        $parsedPath = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            return $url;
        }

        $path = self::urlToPath($url);
        if (! $path || ! is_file($path)) {
            return $url;
        }

        // Two sources differing only by extension emit an appended webp name
        // (Frame-76-5.jpg.webp) rather than a swapped one, because the swapped form would
        // collide — see the hasStemCollision() note in vite/theme-images.js. Prefer the
        // appended file when it exists so each source keeps its own conversion.
        if (is_file($path.'.webp')) {
            $webpPath = $path.'.webp';
            $webpUrl = $url.'.webp';
        } else {
            $webpPath = preg_replace('/\.'.preg_quote($ext, '/').'$/i', '.webp', $path);
            $webpUrl = preg_replace('/\.'.preg_quote($ext, '/').'$/i', '.webp', $url);
        }

        // A zero-byte file means an earlier conversion failed part-way. Treat it as absent
        // and retry, otherwise every later request serves the broken empty image.
        if (is_file($webpPath)) {
            if (filesize($webpPath) > 0) {
                return $webpUrl;
            }

            @unlink($webpPath);
        }

        if (self::generateWebp($path, $webpPath)) {
            return $webpUrl;
        }

        return $url;
    }

    protected static function generateWebp(string $sourcePath, string $destPath): bool
    {
        if (! function_exists('wp_get_image_editor')) {
            return false;
        }

        $editor = wp_get_image_editor($sourcePath);
        if (is_wp_error($editor)) {
            return false;
        }

        try {
            $saved = $editor->save($destPath, 'image/webp');
        } catch (\Throwable $e) {
            @unlink($destPath);

            return false;
        }

        if (is_wp_error($saved) || ! is_file($destPath) || filesize($destPath) === 0) {
            // Never leave a truncated file behind: preferWebp would hand it out forever.
            @unlink($destPath);

            return false;
        }

        return true;
    }

    /**
     * Prevent ACF from stripping direct image URL strings to false when attachment ID is not used.
     */
    public static function filterImageFormatValue(mixed $value, int|string $postId, array $field): mixed
    {
        if (empty($value)) {
            $raw = function_exists('acf_get_value') ? acf_get_value($postId, $field) : null;
            if (is_string($raw) && (str_starts_with($raw, 'http') || str_starts_with($raw, '/'))) {
                return $raw;
            }
        }

        return $value;
    }

    /**
     * Static cache of filename => attachment ID.
     */
    protected static ?array $attachmentMap = null;

    public static function attachmentMap(): array
    {
        if (self::$attachmentMap !== null) {
            return self::$attachmentMap;
        }

        self::$attachmentMap = [];
        global $wpdb;
        if ($wpdb) {
            $rows = $wpdb->get_results("SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment'", defined('ARRAY_A') ? \ARRAY_A : 'ARRAY_A');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $fn = basename($row['guid']);
                    self::$attachmentMap[$fn] = (int) $row['ID'];
                }
            }
        }

        return self::$attachmentMap;
    }

    /**
     * If a value is an image path/URL that matches an existing WP media attachment, return its ID.
     */
    public static function getAttachmentId(mixed $value): mixed
    {
        if (! is_string($value) || empty($value)) {
            return $value;
        }

        $parsedPath = parse_url($value, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['webp', 'png', 'jpg', 'jpeg', 'svg', 'gif'], true)) {
            return $value;
        }

        // Theme-hosted page art (public/images/**) has no media-library counterpart, so it
        // must never be resolved through the attachment map. The map is keyed on basename
        // alone, and filenames collide readily — an unrelated Frame-76.png in uploads was
        // shadowing this page's Frame-76.png and serving a completely different picture,
        // with nothing to indicate it had happened.
        if (str_contains($parsedPath, '/themes/')) {
            return $value;
        }

        $filename = basename($parsedPath);
        $map = self::attachmentMap();

        return $map[$filename] ?? $value;
    }

    /**
     * Resolve any image in public/images/ to its canonical HTTPS URL.
     * Homepage art lives on EFS under uploads/home (and dated upload folders),
     * not in the gitignored theme public/ directory.
     */
    public static function themeImg(string $path): string
    {
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'home/')) {
            return self::homeImg(substr($path, 5));
        }

        $themePath = get_theme_file_path('public/images/'.$path);
        if (self::isUsableImage($themePath)) {
            return esc_url(set_url_scheme(get_template_directory_uri().'/public/images/'.$path, 'https'));
        }

        return self::homeImg(basename($path));
    }

    /**
     * Directory URL for homepage media stored on EFS.
     */
    public static function imgBase(): string
    {
        return rtrim(content_url('/uploads/home'), '/');
    }

    /**
     * Resolve a homepage image, preferring EFS uploads then the theme public dir.
     * Accepts a .webp name and falls back to png/jpg if that is what was uploaded.
     */
    public static function homeImg(string $file): string
    {
        $file = ltrim($file, '/');
        $name = pathinfo($file, PATHINFO_FILENAME);
        $dirs = [
            WP_CONTENT_DIR.'/uploads/home' => content_url('/uploads/home'),
            WP_CONTENT_DIR.'/uploads/hire-va-4' => content_url('/uploads/hire-va-4'),
            WP_CONTENT_DIR.'/uploads/2026/09' => content_url('/uploads/2026/09'),
            WP_CONTENT_DIR.'/uploads/2026/07' => content_url('/uploads/2026/07'),
            WP_CONTENT_DIR.'/uploads/2026/06' => content_url('/uploads/2026/06'),
            WP_CONTENT_DIR.'/uploads/2026/05' => content_url('/uploads/2026/05'),
            WP_CONTENT_DIR.'/uploads/2026/04' => content_url('/uploads/2026/04'),
            get_theme_file_path('public/images/home') => get_template_directory_uri().'/public/images/home',
            get_theme_file_path('public/images/hire-va-4') => get_template_directory_uri().'/public/images/hire-va-4',
        ];

        foreach ($dirs as $dir => $url) {
            foreach (['webp', 'png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $path = $dir.'/'.$name.'.'.$ext;
                if (self::isUsableImage($path)) {
                    return esc_url(set_url_scheme(rtrim($url, '/').'/'.$name.'.'.$ext, 'https'));
                }
            }
        }

        return esc_url(set_url_scheme(self::imgBase().'/'.$file, 'https'));
    }

    /**
     * Whether a candidate path is a file the browser can actually draw.
     *
     * Existence is not enough. Staging's EFS uploads have carried a zero-byte
     * `home/Frame-1092.webp` more than once: it answers 200 with content-length 0, so the
     * browser renders alt text while every check that only asks `is_file()` reports the asset
     * present. Because the uploads directories are searched before the theme's own copies, one
     * empty file there permanently shadowed a perfectly good `public/images/hire-va-4/`
     * original on every page using that block.
     *
     * Treating an empty file as absent lets the search fall through to the next candidate,
     * which fixes this class of failure wherever it appears rather than one filename at a time.
     * preferWebp() has guarded its own conversions this way all along; homeImg(), themeImg()
     * and resolveImageUrl() simply never got the same treatment.
     *
     * filesize() costs nothing extra here — PHP serves it from the stat cache is_file() just
     * populated for the same path.
     */
    private static function isUsableImage(string $path): bool
    {
        return is_file($path) && filesize($path) > 0;
    }

    /**
     * Resolve a hire-va-4 image to its canonical HTTPS URL.
     */
    public static function hireVaImg(string $file): string
    {
        return self::homeImg(ltrim($file, '/'));
    }

    /**
     * Basename => attachment ID for every file in the media library.
     *
     * Built once per request from a single query rather than one lookup per
     * asset: the samples presets alone resolve ~109 paths on one page render.
     *
     * @var array<string, int>|null
     */
    private static ?array $attachmentsByFilename = null;

    /**
     * Resolve a legacy production uploads path to this environment's real URL.
     *
     * The sample-applicant and case-study presets carry paths captured from
     * production in Bedrock form (`/app/uploads/2025/08/Victor-Mexico.mp3`).
     * Production is a classic install serving `/wp-content/uploads/`, and v2's
     * media library was never given these files — so the literal strings
     * resolved on neither host, which is why five pages lost their audio,
     * video and CV links at once.
     *
     * Matching is by filename, not by full path, so a file re-uploaded under a
     * different year/month folder still resolves. Returns '' when nothing is
     * found: an empty src is detectable by both the caller and a parity
     * screenshot, whereas a dead URL renders as a silently broken player.
     */
    public static function mediaUrl(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Decode before matching: a legacy path may still arrive percent-encoded,
        // while the media library stores the decoded filename. (The CV screenshots
        // that shipped with an encoded space were renamed on 2026-09-16 — the CDN
        // would not serve them on staging or production — but the decode stays, as
        // nothing stops an encoded path being passed in.)
        $relative = ltrim((string) (parse_url($path, PHP_URL_PATH) ?: $path), '/');
        $relative = preg_replace('#^(app|wp-content)/uploads/#', '', $relative) ?? $relative;
        $relative = rawurldecode($relative);
        $filename = basename($relative);

        $id = self::attachmentsByFilename()[$filename] ?? null;

        if ($id !== null && function_exists('wp_get_attachment_url')) {
            $url = wp_get_attachment_url($id);

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        // Present on disk but not registered as an attachment — still servable.
        // This is the branch that actually carries the sample-applicant media:
        // those files were synced into uploads/ without being registered as
        // attachments, so the library lookup above finds nothing.
        if (defined('WP_CONTENT_DIR') && self::isUsableImage(WP_CONTENT_DIR.'/uploads/'.$relative)) {
            // Re-encode per segment: $relative was decoded for the filesystem
            // probe, and filenames here really do contain spaces.
            $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));

            return content_url('/uploads/'.$encoded);
        }

        return '';
    }

    /**
     * @return array<string, int>
     */
    private static function attachmentsByFilename(): array
    {
        if (self::$attachmentsByFilename !== null) {
            return self::$attachmentsByFilename;
        }

        self::$attachmentsByFilename = [];

        global $wpdb;

        if (! isset($wpdb) || ! is_object($wpdb) || ! method_exists($wpdb, 'get_results')) {
            return self::$attachmentsByFilename;
        }

        // One query returning both columns: two separate get_col() calls are not
        // guaranteed to come back in the same order, so zipping them by index
        // would pair filenames with the wrong IDs.
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' ORDER BY post_id ASC"
        );

        foreach ((array) $rows as $row) {
            $file = is_object($row) ? ($row->meta_value ?? '') : ($row['meta_value'] ?? '');

            if (! is_string($file) || $file === '') {
                continue;
            }

            $id = (int) (is_object($row) ? ($row->post_id ?? 0) : ($row['post_id'] ?? 0));

            $base = basename($file);

            // Lowest ID wins, so an original beats a later duplicate upload that
            // WordPress suffixed with -1, -2 and so on.
            self::$attachmentsByFilename[$base] ??= $id;

            // An image wider than the big-image threshold is registered as
            // "name-scaled.ext" while the untouched original keeps "name.ext" on
            // disk. Presets reference the original, so index both spellings at the
            // same attachment — otherwise the lookup misses and the caller falls
            // through to a file that only exists where the upload happened.
            $unscaled = self::unscaledName($base);

            if ($unscaled !== null) {
                self::$attachmentsByFilename[$unscaled] ??= $id;
            }
        }

        return self::$attachmentsByFilename;
    }

    /**
     * The original filename behind a WordPress "-scaled" registration, or null.
     *
     * An image past the big-image threshold is stored as "name-scaled.ext" while
     * the untouched original keeps "name.ext" on disk.
     */
    public static function unscaledName(string $basename): ?string
    {
        return preg_match('/^(.+)-scaled(\.[A-Za-z0-9]+)$/', $basename, $m) === 1
            ? $m[1].$m[2]
            : null;
    }

    /**
     * Drop the cached attachment map. Tests only.
     */
    public static function flushMediaCache(): void
    {
        self::$attachmentsByFilename = null;
    }

    /**
     * Resolve an image value (attachment ID, URL string, or ACF image array) to a valid URL string.
     */
    public static function resolveImageUrl(mixed $image): string
    {
        if (empty($image)) {
            return '';
        }

        if (is_numeric($image)) {
            return (string) (wp_get_attachment_url((int) $image) ?: '');
        }

        if (is_array($image)) {
            return (string) ($image['url'] ?? '');
        }

        return (string) $image;
    }

    /**
     * Decode and clean text that might contain HTML entities or unslashed JSON unicode escapes (e.g. u003c, u0026).
     */
    public static function cleanText(mixed $text): string
    {
        if (! is_string($text) || empty($text)) {
            return (string) $text;
        }

        if (str_contains($text, 'u003c') || str_contains($text, 'u0026') || str_contains($text, 'u0022')) {
            $text = str_replace(
                ['u0026amp;', 'u0026', 'u0022', 'u003c', 'u003e'],
                ['&', '&', '"', '<', '>'],
                $text,
            );
        }

        return $text;
    }

    /**
     * Provide default rows when a block repeater field has never been populated.
     */
    public static function filterLoadValue(mixed $value, int|string|null $postId, array $field): mixed
    {
        if ($value !== null && $value !== '' && $value !== false) {
            return $value;
        }

        // If user explicitly deleted all rows and saved, ACF metadata is '0'
        if (! empty($postId) && function_exists('acf_get_metadata') && acf_get_metadata($postId, $field['name']) === '0') {
            return [];
        }

        $fieldName = $field['name'] ?? '';
        $fieldKey = $field['key'] ?? '';

        return match ($fieldName) {
            'steps' => self::formatRepeaterForAcf('field_process_steps_block_steps', self::steps()),
            'cards' => match ($fieldKey) {
                'field_department_cards_block_cards' => self::formatRepeaterForAcf('field_department_cards_block_cards', self::departmentCards()),
                'field_feature_cards_block_cards' => self::formatRepeaterForAcf('field_feature_cards_block_cards', self::featureCards('3')),
                'field_roles_grid_block_cards' => self::formatRepeaterForAcf('field_roles_grid_block_cards', self::rolesGridCards()),
                default => $value,
            },
            'rows' => self::formatRepeaterForAcf('field_data_table_block_rows', self::dataTableRows()),
            'testimonials' => self::formatRepeaterForAcf('field_testimonials_block_testimonials', self::testimonials()),
            'faqs' => self::formatRepeaterForAcf('field_accordion_faq_block_faqs', self::faqsForAcf()),
            'logos' => self::formatRepeaterForAcf('field_client_logos_marquee_block_logos', self::logos()),
            'talent_cards' => self::formatRepeaterForAcf('field_talent_marquee_block_talent_cards', self::talentCards()),
            default => $value,
        };
    }

    public static function formatRepeaterForAcf(string $parentKey, array $rows): array
    {
        $formatted = [];
        foreach ($rows as $row) {
            $formattedRow = [];
            foreach ($row as $k => $v) {
                $encodedVal = self::getAttachmentId($v);
                $formattedRow[$k] = $encodedVal;
                $formattedRow["{$parentKey}_{$k}"] = $encodedVal;
            }
            $formatted[] = $formattedRow;
        }

        return $formatted;
    }

    /**
     * ACF-encode repeater rows into a block's `data` attributes.
     *
     * Handles nested repeaters: a sub-field whose value is a *list of arrays* is
     * encoded as its own repeater under the composed name, which is what
     * `acf/talent-dossier-carousel` needs for the `tools` strip inside each card.
     * Without this, a nested list was passed through getAttachmentId() as a raw
     * array and ACF could not load it — the strip silently rendered empty.
     *
     * An ACF image array (`['url' => …, 'id' => …]`) is associative, not a list,
     * so it is never mistaken for nested rows.
     */
    public static function encodeRepeater(string $fieldName, string $fieldKey, array $rows, array &$data = []): array
    {
        $data[$fieldName] = count($rows);
        $data['_'.$fieldName] = $fieldKey;
        foreach ($rows as $i => $row) {
            foreach ($row as $subfield => $val) {
                $subKey = "{$fieldKey}_{$subfield}";

                if (self::isNestedRepeater($val)) {
                    self::encodeRepeater("{$fieldName}_{$i}_{$subfield}", $subKey, $val, $data);

                    continue;
                }

                $data["{$fieldName}_{$i}_{$subfield}"] = self::getAttachmentId($val);
                $data["_{$fieldName}_{$i}_{$subfield}"] = $subKey;
            }
        }

        return $data;
    }

    /**
     * Whether a sub-field value is a nested repeater's rows — a non-empty list
     * whose every element is an array.
     */
    private static function isNestedRepeater(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $row) {
            if (! is_array($row)) {
                return false;
            }
        }

        return true;
    }

    public static function patternBlock(string $slug, array $data = [], array $attrs = []): string
    {
        $blockAttrs = array_merge([
            'name' => "acf/{$slug}",
            'data' => $data,
            'align' => '',
            'mode' => 'preview',
        ], $attrs);

        return '<!-- wp:acf/'.$slug.' '.json_encode($blockAttrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' /-->';
    }

    // --- PROCESS STEPS ---
    public static function steps(): array
    {
        return [
            [
                'num' => '01',
                'title' => 'Tell us your<br>ideal hire',
                'desc' => 'Tell us who you need. We handle sourcing, screening, and vetting candidates so you can focus on choosing the right person.',
            ],
            [
                'num' => '02',
                'title' => 'Meet your<br>top 1% shortlist',
                'desc' => 'Within 48–72 hours, receive 4–6 candidates pre-vetted for skill, experience, and fit. You interview, you choose. No commitments, no pressure.',
            ],
            [
                'num' => '03',
                'title' => 'Make your<br>selection',
                'desc' => 'Make your selection and get back to growing your business. We handle the details so your new hire can hit the ground running.',
            ],
        ];
    }

    public static function renderProcessSteps(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('steps', 'field_process_steps_block_steps', self::steps(), $data);

        return self::patternBlock('process-steps', array_merge($data, $overrides));
    }

    /**
     * The six profiles shown in the partner-page hero row, identical on both partner pages.
     *
     * @return array<int, array{name: string, title: string, desc: string, bg: string, logo: string}>
     */
    public static function partnerTalentCards(): array
    {
        $rows = [
            ['con-07.png', 'André Vilalobos', 'Graphic Designer', '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.', 'State-Farm-01.png'],
            ['cont-02.png', 'Juliana Silva', 'Lead Generation (SDR)', '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.', 'mercado.png'],
            ['con-05.png', 'Valeria Andrea', 'Medical / Healthcare', '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.', 'Allstate-01.png'],
            ['con-08.png', 'Laura Valentina', 'Customer Support', '+4 years of experience in B2B SaaS customer support, I’ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles while handling email, chat, and phone support.', 'Bank-of-America-01.png'],
            ['cont-03.png', 'Sofía Pérez', 'Marketing', '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness and customer engagement.', 'Frame-74-1.png'],
            ['con-06.png', 'Luana Dias', 'Executive Assistant', '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive’s time like it’s my own.', 'NU-bank-01.png'],
        ];

        return array_map(static fn (array $r): array => [
            'name' => $r[1],
            'title' => $r[2],
            'desc' => $r[3],
            'bg' => self::pageImg('partners', $r[0]),
            'logo' => self::pageImg('partners', $r[4]),
        ], $rows);
    }

    /**
     * @param  array<int, string>  $paragraphs
     * @param  array<int, array{value: string, label: string, icon?: string}>  $stats
     */
    public static function renderPartnerHero(array $overrides, array $paragraphs = [], array $stats = []): string
    {
        $data = self::withFieldKeys('partner_hero_block', $overrides);

        self::encodeRepeater(
            'paragraphs',
            'field_partner_hero_block_paragraphs',
            array_map(static fn (string $text): array => ['text' => $text], $paragraphs),
            $data,
        );

        self::encodeRepeater('stats', 'field_partner_hero_block_stats', $stats, $data);

        return self::patternBlock('partner-hero', $data, ['align' => 'full']);
    }

    /** @param  array<int, array{title: string, text: string}>  $steps */
    public static function renderProgressSteps(array $overrides, array $steps): string
    {
        $data = self::withFieldKeys('progress_steps_block', $overrides);
        self::encodeRepeater('steps', 'field_progress_steps_block_steps', $steps, $data);

        return self::patternBlock('progress-steps', $data, ['align' => 'full']);
    }

    /** Render acf/media-copy with field keys attached so overrides actually apply. */
    public static function renderMediaCopy(array $overrides = []): string
    {
        return self::patternBlock('media-copy', self::withFieldKeys('media_copy_block', $overrides), ['align' => 'full']);
    }

    /** Render acf/cta-banner with field keys attached. */
    public static function renderCtaBanner(array $overrides = []): string
    {
        return self::patternBlock('cta-banner', self::withFieldKeys('cta_banner_block', $overrides), ['align' => 'full']);
    }

    /**
     * ACF only reads a block-comment value when its `_<name>` field key sits beside it.
     * Without this, an override is silently dropped and the block renders its defaults.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function withFieldKeys(string $group, array $values): array
    {
        $data = [];

        foreach ($values as $name => $value) {
            $data[$name] = $value;
            $data['_'.$name] = 'field_'.$group.'_'.$name;
        }

        return $data;
    }

    /** Theme-file URL for an asset that belongs to a single migrated page. */
    public static function pageImg(string $page, string $file): string
    {
        return get_theme_file_uri('public/images/'.$page.'/'.$file);
    }

    /**
     * The 2026 Impact Report placement counts, in production's order (row-wise across
     * three columns). Kept here so the block renders correctly with no fields set.
     *
     * @return array<int, array{flag: string, country: string, count: string}>
     */
    public static function countryPlacements(): array
    {
        $rows = [
            ['Mexico', '317', 'mexico.png'], ['Colombia', '185', 'Colombia.png'], ['Honduras', '145', 'Honduras.png'],
            ['Jamaica', '140', 'jamaica.png'], ['Brazil', '102', 'Brazil.png'], ['Costa Rica', '95', 'Costa_rica.png'],
            ['Nicaragua', '90', 'Nicaragua.png'], ['El Salvador', '85', 'El-Salvador.png'], ['D. Republic', '82', 'D.-Repblic.png'],
            ['Guatemala', '68', 'Guatemala.png'], ['Argentina', '56', 'Argentina.png'], ['Ecuador', '38', 'ecuador.png'],
            ['Peru', '37', 'Peru.png'], ['Panama', '34', 'Panama.png'], ['Belize', '31', 'Belize.png'],
            ['Bolivia', '19', 'Bolivia.png'], ['Chile', '17', 'chile.png'], ['T. and Tobago', '8', 'T.-and-Tobago.png'],
            ['Paraguay', '8', 'Paraguay.png'], ['Barbados', '7', 'barbados.png'], ['Guyana', '7', 'Guyana.png'],
            ['Uruguay', '6', 'Uruguay.png'], ['St. Lucia', '5', 'St.-Lucia.png'], ['Dominica', '2', 'Dominica.png'],
            ['A. and Barbuda', '1', 'A.-and-Barbuda.png'], ['C. Islands', '1', 'C.-Islands.png'], ['Curacao', '1', 'Curacao.png'],
        ];

        return array_map(static fn (array $r): array => [
            'country' => $r[0],
            'count' => $r[1],
            'flag' => self::pageImg('impact-report-2026/flags', $r[2]),
        ], $rows);
    }

    /**
     * @param  array|null  $cards  Replaces the preset cards entirely (keys: icon, title, text).
     */
    public static function renderRolesCarousel(array $overrides = [], ?array $cards = null): string
    {
        $data = [];

        if ($cards !== null) {
            self::encodeRepeater('cards', 'field_roles_carousel_block_cards', $cards, $data);
        }

        foreach (['headline', 'subheadline'] as $text) {
            if (isset($overrides[$text])) {
                $data['_'.$text] = 'field_roles_carousel_block_'.$text;
            }
        }

        return self::patternBlock('roles-carousel', array_merge($data, $overrides), ['align' => 'full']);
    }

    /** @param  array|null  $rows  Replaces the preset countries entirely. */
    public static function renderCountryPlacements(array $overrides = [], ?array $rows = null): string
    {
        $data = [];
        self::encodeRepeater('rows', 'field_country_placements_block_rows', $rows ?? self::countryPlacements(), $data);

        return self::patternBlock('country-placements', array_merge($data, $overrides), ['align' => 'full']);
    }

    // --- DEPARTMENT CARDS ---
    public static function departmentCards(): array
    {
        $img = self::imgBase();

        return [
            [
                'img' => self::homeImg('magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp'),
                'title' => 'Administrative &<br>Executive Assistants',
                'desc' => 'Executive support for busy founders and teams.',
            ],
            [
                'img' => self::homeImg('magnific_wPmw8Jk7EI-1.webp'),
                'title' => 'Healthcare &<br>Medical Assistants',
                'desc' => 'Healthcare professionals supporting clinics and practices.',
            ],
            [
                'img' => self::homeImg('magnific_ubzu0aUQLD-1.webp'),
                'title' => 'Sales & Growth<br>Marketing Talents',
                'desc' => 'Professionals focused on growth, leads, and revenue.',
            ],
            [
                'img' => self::homeImg('magnific_YVjYLdkWeC-1.webp'),
                'title' => 'Operations &<br>Finance Professionals',
                'desc' => 'Experts in finance, operations, and business support.',
            ],
        ];
    }

    /** @param  array|null  $cards  Replaces the preset cards entirely (see renderFeatureCards). */
    public static function renderDepartmentCards(array $overrides = [], ?array $cards = null): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_department_cards_block_cards', $cards ?? self::departmentCards(), $data);

        return self::patternBlock('department-cards', array_merge($data, $overrides));
    }

    // --- FEATURE CARDS ---
    public static function featureCards(string $columns = '3'): array
    {
        $img = self::imgBase();

        if ($columns === '4') {
            return [
                [
                    'img' => self::homeImg('hour.webp'),
                    'title' => '$6-10 /hr',
                    'desc' => 'Access experienced professionals at highly competitive rates. Most administrative, support, sales, and marketing roles can be filled within this range.',
                ],
                [
                    'img' => self::homeImg('lower-cost.webp'),
                    'title' => '70% Lower Costs',
                    'desc' => 'Reduce hiring costs without sacrificing quality. Reinvest the savings into growth, marketing, product development, or additional hires.',
                ],
                [
                    'img' => self::homeImg('day-average.webp'),
                    'title' => '4-Day Average',
                    'desc' => 'From opening a role to reviewing qualified candidates in days, not weeks. Our recruiting process is designed for speed without compromising quality.',
                ],
                [
                    'img' => self::homeImg('quality.webp'),
                    'title' => 'Vetted for Quality',
                    'desc' => 'Every candidate is screened for English proficiency, experience, communication skills, and role-specific expertise before reaching your inbox.',
                ],
            ];
        }

        return [
            [
                'img' => self::homeImg('Latin-american.webp'),
                'title' => 'Top-tier talents from Latin America and EU',
                'desc' => 'Access exceptional global talent. We identify skilled professionals with the communication, expertise, and reliability needed to make an immediate impact.',
            ],
            [
                'img' => self::homeImg('no-contracts.webp'),
                'title' => 'No contracts<br>No obligations',
                'desc' => 'Evaluate talent, interview candidates, and see our process firsthand before making any commitment. The decision is always yours.',
            ],
            [
                'img' => self::homeImg('ongoing-middleman.webp'),
                'title' => 'No ongoing<br>middleman fees',
                'desc' => 'You hire talent directly into your business. No payroll markups, monthly management fees, or recurring commissions.',
            ],
            [
                'img' => self::homeImg('payment.webp'),
                'title' => "No payment if we don't find the right talent",
                'desc' => 'Our incentives are aligned with yours. We only succeed when you make a successful hire, so we focus relentlessly on finding the right fit.',
            ],
            [
                'img' => self::homeImg('payment-compliance.webp'),
                'title' => 'Payments, compliance,<br>onboarding support',
                'desc' => 'Our Contractor Management solution simplifies onboarding, contracts, payroll, and compliance for international talent.',
            ],
            [
                'img' => self::homeImg('one-dashboard.webp'),
                'title' => 'One dashboard<br>for your entire team',
                'desc' => 'Manage payroll, contracts, compliance, and workforce reporting from a single platform. Stay organized as your global team grows.',
            ],
        ];
    }

    /**
     * @param  array|null  $cards  Replaces the preset cards entirely. Raw arrays cannot be
     *                             passed through $overrides, because the encoded preset keys
     *                             would still win — so custom cards go here.
     * @param  string  $ratio  Optional card image aspect as "w/h" (e.g. '413/152').
     */
    public static function renderFeatureCards(string $columns = '3', array $overrides = [], ?array $cards = null, string $ratio = '', string $variant = 'inset', string $surface = 'solid'): string
    {
        $data = [
            'columns' => $columns,
            '_columns' => 'field_feature_cards_block_columns',
        ];

        if ($ratio !== '') {
            $data['ratio'] = $ratio;
            $data['_ratio'] = 'field_feature_cards_block_ratio';
        }

        if ($variant !== 'inset') {
            $data['variant'] = $variant;
            $data['_variant'] = 'field_feature_cards_block_variant';
        }

        if ($surface !== 'solid') {
            $data['surface'] = $surface;
            $data['_surface'] = 'field_feature_cards_block_surface';
        }

        self::encodeRepeater('cards', 'field_feature_cards_block_cards', $cards ?? self::featureCards($columns), $data);

        return self::patternBlock('feature-cards', array_merge($data, $overrides));
    }

    // --- DATA TABLE ---
    public static function dataTableRows(): array
    {
        return [
            ['feature' => 'Time to Hire', 'diy' => '4 - 8 weeks', 'rl' => '72 hrs'],
            ['feature' => 'Vetting Quality', 'diy' => 'Hit or miss', 'rl' => 'Top 1% pre-screened'],
            ['feature' => 'Payroll & taxes', 'diy' => 'DIY or expensive local lawyer', 'rl' => 'Fully managed'],
            ['feature' => 'Compliance risk', 'diy' => 'High - misclassification, local laws', 'rl' => 'Zero - 170+ countries covered'],
            ['feature' => 'Ongoing fees', 'diy' => 'Often 30-50% monthly markup', 'rl' => 'One-time flat fee only'],
            ['feature' => 'Replacement guarantee', 'diy' => 'None', 'rl' => '12-months, no extra costs'],
            ['feature' => 'Centralized reporting', 'diy' => 'Spreadsheets', 'rl' => 'Dashboard to manage your team'],
        ];
    }

    /** @param  array|null  $rows  Replaces the preset rows entirely (see renderFeatureCards). */
    public static function renderDataTable(array $overrides = [], ?array $rows = null): string
    {
        $data = [
            'col_1_header' => 'DIY',
            '_col_1_header' => 'field_data_table_block_col_1_header',
            'col_2_header' => 'Remote Leverage',
            '_col_2_header' => 'field_data_table_block_col_2_header',
        ];
        self::encodeRepeater('rows', 'field_data_table_block_rows', $rows ?? self::dataTableRows(), $data);

        return self::patternBlock('data-table', array_merge($data, $overrides));
    }

    // --- TRUST STATS ---
    public static function renderTrustStats(array $overrides = []): string
    {
        $data = [
            'onboarded_count' => '+2500',
            '_onboarded_count' => 'field_trust_stats_block_onboarded_count',
            'countries_count' => '+50',
            '_countries_count' => 'field_trust_stats_block_countries_count',
            'economic_impact' => 'USD 41,920,000',
            '_economic_impact' => 'field_trust_stats_block_economic_impact',
            'timeframe' => "Last 12\nMonths",
            '_timeframe' => 'field_trust_stats_block_timeframe',
        ];

        return self::patternBlock('trust-stats', array_merge($data, $overrides));
    }

    // --- TESTIMONIALS ---
    public static function testimonials(): array
    {
        $img = self::imgBase();

        return [
            [
                'video_url' => 'https://vimeo.com/1067577208',
                'image' => self::homeImg('PRES-Property-Management.jpg'),
                'duration' => '00:38',
                'quote' => '“I can\'t say enought about how every step of the way it just wowed me.”',
                'company' => 'PRES Property Management',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => self::homeImg('Coldwell-Banker.jpg'),
                'duration' => '00:19',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'company' => 'Coldwell Banker',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => self::homeImg('Carbon-Solutions-Group.jpg'),
                'duration' => '00:55',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'company' => 'Carbon Solutions Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => self::homeImg('The-Zen-Zone-Wellness.jpg'),
                'duration' => '04:06',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'company' => 'The Zen Zone Wellness',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => self::homeImg('Color-Job.jpg'),
                'duration' => '01:56',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'company' => 'Color Job',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => self::homeImg('Connect-Church-Colorado.jpg'),
                'duration' => '02:45',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'company' => 'Connect Church Colorado',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => self::homeImg('Cash-is-King.jpg'),
                'duration' => '02:39',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'company' => 'Cash is King',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => self::homeImg('Liberty-Hill.jpg'),
                'duration' => '02:31',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                'company' => 'Liberty Hill',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => self::homeImg('RE-MAX.jpg'),
                'duration' => '01:02',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it\'s been a game changer for me.”',
                'company' => 'RE / MAX',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => self::homeImg('Realty-One-Group.jpg'),
                'duration' => '01:43',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'company' => 'Realty One Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => self::homeImg('OneUp-Sportz-01.jpg'),
                'duration' => '01:06',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577598',
                'image' => self::homeImg('OneUp-Sportz.jpg'),
                'duration' => '01:40',
                'quote' => '“It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => self::homeImg('Greener-Hill-Psychiatric.jpg'),
                'duration' => '05:59',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'company' => 'Greener Hill Psychiatric',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => self::homeImg('Diamond-Detox.jpg'),
                'duration' => '00:43',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'company' => 'Diamond Detox',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => self::homeImg('Ad-Center-360.jpg'),
                'duration' => '00:36',
                'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                'company' => 'Ad Center 360',
            ],
        ];
    }

    public static function renderTestimonials(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('testimonials', 'field_testimonials_block_testimonials', self::testimonials(), $data);

        return self::patternBlock('testimonials', array_merge($data, $overrides));
    }

    // --- ACCORDION FAQ ---
    public static function faqs(): array
    {
        return array_map(function ($item) {
            return [
                'q' => $item['question'],
                'a' => $item['answer'],
            ];
        }, self::faqsForAcf());
    }

    public static function faqsForAcf(): array
    {
        return [
            [
                'question' => 'What countries do you hire from?',
                'answer' => '<p>We focus on four key regions:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p class="mt-3">Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their exceptional English fluency, strong cultural alignment, and convenient time zone overlap with North America.</p>',
            ],
            [
                'question' => 'How do taxes & payroll work when hiring Virtual Assistants?',
                'answer' => '<p>Your VA is an independent contractor, so there\'s no payroll involved. If you\'d rather not manage contractor agreements, documentation, and international payments yourself, our Contractor of Record (COR) add-on puts Remote Leverage in the contracting seat – we handle onboarding, verified time tracking, and cross-border payment, and you get a single invoice.</p>',
            ],
            [
                'question' => 'How do you get paid?',
                'answer' => '<p>It\'s simple – we charge a one-time flat fee, but only after you\'ve found your perfect match.</p><p class="mt-2">Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>',
            ],
            [
                'question' => 'What\'s the difference between Staffing and Recruiting Agencies?',
                'answer' => '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly.</p>',
            ],
            [
                'question' => 'What if I have questions and need help after hiring?',
                'answer' => '<p>After hiring your Virtual Assistant, you\'ll have access to a dedicated Customer Success Manager who will help ensure your success with reviewing performance, monitoring progress, training guidance, and any other requests.</p>',
            ],
            [
                'question' => 'What if they don\'t turn out to be a good fit?',
                'answer' => '<p>We offer a 12-month replacement guarantee at no extra cost and unlimited candidate interviews to ensure you find the best match.</p>',
            ],
            [
                'question' => 'How is their English and Communication skills?',
                'answer' => '<p>We maintain extremely high standards for English fluency. All candidates must submit an English voice recording, and we only select those with fluent English and minimal accents.</p>',
            ],
            [
                'question' => 'Can I start with Part-time?',
                'answer' => '<p>Yes, you can start with either part-time or full-time. The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>',
            ],
            [
                'question' => 'What time zone will they be working in?',
                'answer' => '<p>Your Virtual Assistant will work according to your schedule and time zone. They\'re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>',
            ],
            [
                'question' => 'How much does the average Virtual Assistant cost?',
                'answer' => '<p>Virtual Assistant\'s hourly rates depend on skills, experience and region:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li><strong>Entry Level:</strong> $6-$10 per hour</li><li><strong>Highly Experienced:</strong> $11-$15 per hour</li></ul><p class="mt-3">The hourly rate you agree to pay goes directly to your Virtual Assistant.</p>',
            ],
        ];
    }

    public static function renderAccordionFaq(array $overrides = []): string
    {
        $data = [
            'headline' => 'Frequently Asked Questions',
            '_headline' => 'field_accordion_faq_block_headline',
        ];
        self::encodeRepeater('faqs', 'field_accordion_faq_block_faqs', self::faqsForAcf(), $data);

        return self::patternBlock('accordion-faq', array_merge($data, $overrides));
    }

    // --- CLIENT LOGOS ---
    public static function logos(): array
    {
        $img = self::imgBase();

        return [
            ['src' => self::homeImg('brrrr-1.webp'), 'alt' => 'BRRRR'],
            ['src' => self::homeImg('carbon-1.webp'), 'alt' => 'Carbon Solutions'],
            ['src' => self::homeImg('Q-BitNewLogo-Photoroom-1.webp'), 'alt' => 'Q-Bit'],
            ['src' => self::homeImg('boe-1.webp'), 'alt' => 'BOE'],
            ['src' => self::homeImg('greener-hill-1.webp'), 'alt' => 'Greener Hill'],
            ['src' => self::homeImg('Prestige-Landscaping-1.webp'), 'alt' => 'Prestige Landscaping'],
            ['src' => self::homeImg('garuz-1-1.webp'), 'alt' => 'Garuz'],
            ['src' => self::homeImg('vercasa-1.webp'), 'alt' => 'Vercasa'],
            ['src' => self::homeImg('adcenter-2.webp'), 'alt' => 'Ad Center 360'],
            ['src' => self::homeImg('liberty-hill-1.webp'), 'alt' => 'Liberty Hill'],
            ['src' => self::homeImg('chick-fil-a-logo-1.webp'), 'alt' => 'Chick-fil-A'],
            ['src' => self::homeImg('rl-adp.webp'), 'alt' => 'ADP'],
            ['src' => self::homeImg('rl-mainstreet.webp'), 'alt' => 'Mainstreet'],
            ['src' => self::homeImg('rl-farmers.webp'), 'alt' => 'Farmers Insurance'],
            ['src' => self::homeImg('rl-college-hunks.webp'), 'alt' => 'College Hunks'],
            ['src' => self::homeImg('remax-1.webp'), 'alt' => 'RE/MAX'],
            ['src' => self::homeImg('coldwell-1.webp'), 'alt' => 'Coldwell Banker'],
            ['src' => self::homeImg('sivia-law-white-306w-1.webp'), 'alt' => 'Sivia Law'],
            ['src' => self::homeImg('zone-4-1.webp'), 'alt' => 'Zone 4'],
        ];
    }

    public static function renderClientLogosMarquee(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('logos', 'field_client_logos_marquee_block_logos', self::logos(), $data);

        return self::patternBlock('client-logos-marquee', array_merge($data, $overrides), ['align' => 'full']);
    }

    /**
     * The 2026 homepage's magenta CTA pill, as one class list.
     *
     * Lives here rather than only in blocks/partials/cta-pill.blade.php because the reviews
     * wall needs the same pill on a <button> carrying Alpine state, and the partial renders an
     * <a>. One source beats two copies drifting apart. `app/` is inside Tailwind's @source
     * globs (see resources/css/app.css), so the scanner sees these literals.
     *
     * Measured off Homepage V3.png: 348x61, fill #F90066, label 19px/700 uppercase with
     * tracking -0.45px, and a 1.5px ring 4px outside the fill.
     */
    public static function ctaPillClasses(string $extra = ''): string
    {
        $base = 'group inline-flex items-center justify-center gap-9 rounded-full bg-brand-magenta '
            .'py-[19px] pl-10 pr-8 font-display text-[17px] font-bold uppercase leading-none '
            .'tracking-[-0.45px] text-white outline outline-[1.5px] outline-offset-4 '
            .'outline-brand-magenta transition-all duration-200 hover:bg-brand-magenta-hover '
            .'hover:outline-brand-magenta-hover focus:outline-brand-magenta focus-visible:ring-2 '
            .'focus-visible:ring-brand-magenta focus-visible:ring-offset-2 sm:text-[19px]';

        return trim($base.' '.$extra);
    }

    /**
     * The guarantee card's three reassurance items, as production words them.
     *
     * The first line is the one that varies: production qualifies the guarantee as six months
     * extended to twelve, while the 2026 homepage states a flat twelve. Both headings read
     * "12-Month Replacement Guarantee", so the qualifier is that page's claim rather than a
     * typo — which is why it is overridable per page instead of corrected here.
     *
     * @return array<int, array{title: string, text: string}>
     */
    public static function guaranteeReassuranceItems(): array
    {
        return [
            [
                'title' => 'Not the right fit?',
                'text' => 'Free replacement any time in the first 6 months, extended to 12.',
            ],
            [
                'title' => 'No long-term contracts.',
                'text' => 'One flat fee, only if you hire.',
            ],
            [
                'title' => 'A dedicated manager helps with onboarding, training, and tracking',
                'text' => '',
            ],
        ];
    }

    // --- HOME HERO (2026 homepage) ---

    /**
     * The hero's six checklist items, in the single order that serves both breakpoints.
     *
     * @return array<int, string>
     */
    public static function homeHeroChecklist(): array
    {
        return [
            'No Contracts, No Ongoing Fees',
            'Interview Before You Hire',
            '12-Month Replacement Guarantee',
            '30% Discount on Future Hires',
            'Hire Direct, No Middleman',
            'Interview in 48 Hours',
        ];
    }

    /**
     * The three hero talent cards, in placement order: back-left, front-centre, back-right.
     *
     * Three, not the comp's four — the fourth (André Vilalobos) carried no portrait and existed
     * only to peek out from behind another card. With every card now showing a face, a hidden
     * filler card had nothing to add.
     *
     * Portraits are hero-specific art, supplied 2026-09-16 and normalised into
     * resources/images/pages/home/hero/: each was trimmed of its transparent margin and set to
     * a common 560px height, because the three arrived framed very differently and the same CSS
     * height would otherwise have rendered three different figure sizes.
     *
     * Flags come from resources/images/pages/home/flags/, copied from the sales-talents and
     * flags sets rather than the 19px home/<country>.png icons, which are too small to hold up.
     * The comp draws rectangular emoji flags; these are the theme's circular set, chosen over
     * emoji so the glyph does not change shape between Apple, Windows and Android. The two
     * vector flags in the source sets are 1.1MB (Mexico) and 567KB (Argentina) — detailed coats
     * of arms — so Brazil and Argentina take the 76px PNGs and only Colombia, which is 753
     * bytes of SVG, stays vector.
     *
     * @return array<int, array<string, string>>
     */
    public static function homeHeroCards(): array
    {
        return [
            [
                'name' => 'Mariana Costa',
                'role' => 'Sales Assistant',
                'flag' => self::pageImg('home', 'flags/argentina.png'),
                'photo' => self::pageImg('home', 'hero/hero-mariana.png'),
            ],
            [
                'name' => 'Luana Dias',
                'role' => 'Administrative Assistant',
                'flag' => self::pageImg('home', 'flags/colombia.svg'),
                'photo' => self::pageImg('home', 'hero/hero-luana.png'),
            ],
            [
                'name' => 'Bruno Carvalho',
                'role' => 'Sr Executive Assistant',
                'flag' => self::pageImg('home', 'flags/brazil.png'),
                'photo' => self::pageImg('home', 'hero/hero-bruno.png'),
            ],
        ];
    }

    public static function renderHomeHero(array $overrides = []): string
    {
        return self::patternBlock('home-hero', $overrides, ['align' => 'full']);
    }

    // --- TALENT MARQUEE ---
    public static function talentCards(): array
    {
        $img = self::imgBase();

        return [
            [
                'name' => 'Daniela Costa',
                'title' => 'Executive Assistant',
                'desc' => 'Experienced Executive Assistant specializing in executive support, meeting coordination, travel planning, and operational workflows. Known for exceptional organization.',
                'logo' => self::homeImg('rappi_logo-Small.webp'),
                'bg' => self::homeImg('Frame-132-1.webp'),
            ],
            [
                'name' => 'Lucas Mendes',
                'title' => 'Marketing Manager',
                'desc' => 'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization with proven track record scaling pipeline.',
                'logo' => self::homeImg('clickup.webp'),
                'bg' => self::homeImg('Frame-133-1.webp'),
            ],
            [
                'name' => 'Noah Martinez',
                'title' => 'Sales Representative',
                'desc' => 'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies.',
                'logo' => self::homeImg('image-2.webp'),
                'bg' => self::homeImg('Frame-135-1.webp'),
            ],
            [
                'name' => 'Diego Navarro',
                'title' => 'Sales Representative',
                'desc' => 'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management to convert qualified leads.',
                'logo' => self::homeImg('image-11.webp'),
                'bg' => self::homeImg('Frame-135-2.webp'),
            ],
            [
                'name' => 'André Vilalobos',
                'title' => 'Graphic Designer',
                'desc' => '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.',
                'logo' => self::homeImg('State-Farm-01.webp'),
                'bg' => self::homeImg('con-07.webp'),
            ],
            [
                'name' => 'Juliana Silva',
                'title' => 'Lead Generation (SDR)',
                'desc' => '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.',
                'logo' => self::homeImg('mercado.webp'),
                'bg' => self::homeImg('cont-02.webp'),
            ],
            [
                'name' => 'Valeria Andrea',
                'title' => 'Medical Assistant',
                'desc' => '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.',
                'logo' => self::homeImg('Allstate-01.webp'),
                'bg' => self::homeImg('con-05.webp'),
            ],
            [
                'name' => 'Laura Valentina',
                'title' => 'Customer Support',
                'desc' => '+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles.',
                'logo' => self::homeImg('image-12-1.webp'),
                'bg' => self::homeImg('con-08.webp'),
            ],
            [
                'name' => 'Sofía Pérez',
                'title' => 'Marketing Assistant',
                'desc' => '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness.',
                'logo' => self::homeImg('Frame-74-1.webp'),
                'bg' => self::homeImg('cont-03.webp'),
            ],
            [
                'name' => 'Luana Dias',
                'title' => 'Executive Assistant',
                'desc' => '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive\'s time like it\'s my own.',
                'logo' => self::homeImg('NU-bank-01.webp'),
                'bg' => self::homeImg('con-06.webp'),
            ],
            [
                'name' => 'Sarah Martinez',
                'title' => 'Sr Executive Assistant',
                'desc' => 'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.',
                'logo' => self::homeImg('1655873088shopify-logo-transparent.webp'),
                'bg' => self::homeImg('Frame-131-1.webp'),
            ],
        ];
    }

    public static function renderTalentMarquee(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('talent_cards', 'field_talent_marquee_block_talent_cards', self::talentCards(), $data);

        return self::patternBlock('talent-marquee', array_merge($data, $overrides), ['align' => 'full']);
    }

    public static function imgBaseHireVa4(): string
    {
        return rtrim(content_url('/uploads/home'), '/');
    }

    // --- HIRE-VA-4: ROLES GRID ---
    public static function rolesGridCards(): array
    {
        $img = self::imgBaseHireVa4();

        return [
            [
                'title' => 'Administrative',
                'desc' => 'Inbox, calendar, invoices, data entry. The daily upkeep taken off your plate.',
                'img' => self::homeImg('Woman_looking_camera_smiling_2K_202607171433-1.png'),
            ],
            [
                'title' => 'Lead Generation',
                'desc' => 'Outreach calls, emails, texting, and follow-up that keeps your pipeline full.',
                // Served from the theme, not via homeImg(): CloudFront cached a zero-length
                // response for /app/uploads/home/Frame-1092.webp and ignores query strings, so
                // that URL cannot be un-poisoned from outside AWS. A new path is a new cache
                // key. Keeping it under the theme also puts it out of reach of the uploads
                // search order that let one bad file shadow a good one in the first place.
                'img' => self::pageImg('home', 'roles/lead-generation.png'),
            ],
            [
                'title' => 'Sales (SDR)',
                'desc' => 'Qualifies leads, runs demos, and follows through until it\'s a closed deal.',
                'img' => self::homeImg('Screenshot-2026-07-17-at-2.03.18-p.m.-1.png'),
            ],
            [
                'title' => 'Social Media',
                'desc' => 'Posts, replies, and community management that keeps your brand active.',
                'img' => self::homeImg('Screenshot-2026-07-17-at-2.02.42-p.m.-1.png'),
            ],
            [
                'title' => 'Marketing',
                'desc' => 'Runs and optimizes your paid campaigns across Meta, Google, and LinkedIn.',
                'img' => self::homeImg('Frame-1092-1.png'),
            ],
            [
                'title' => 'Graphic Design',
                'desc' => 'Social creative, decks, and brand assets that look like an in-house hire made them.',
                'img' => self::homeImg('Screenshot-2026-07-17-at-2.01.07-p.m.-1.png'),
            ],
            [
                'title' => 'Customer Support',
                'desc' => 'Tickets, questions, and vendor calls handled so your customers stay happy.',
                'img' => self::homeImg('man-dressed-casual-wearing-glasses-studio-shot-copy-space-2.png'),
            ],
            [
                'title' => 'Custom Role',
                'desc' => 'Something specific in mind? Tell us the role — we\'ve likely filled it before.',
                'img' => self::homeImg('Frame-216.png'),
            ],
        ];
    }

    public static function renderRolesGrid(array $overrides = [], ?array $cards = null): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_roles_grid_block_cards', $cards ?? self::rolesGridCards(), $data);

        return self::patternBlock('roles-grid', array_merge($data, $overrides));
    }

    // --- HIRE-VA-4: PROCESS STEPS ---
    /**
     * "Our Hiring Process" steps as production renders them on /hire-va-4/.
     *
     * Corrected 2026-09-15: these had drifted to Lano-partnership copy ("We
     * handle pay & compliance", a Lano payroll dashboard in step 3) that
     * production does not show on this page.
     */
    public static function hireVa4ProcessSteps(): array
    {
        return [
            [
                'num' => '01',
                'title' => 'Tell us your<br>ideal hire',
                'desc' => 'Book a quick call to tell us the support you need to grow. Don’t have a job description? No problem. Tell us the bottlenecks in your business, and we’ll solve them.',
            ],
            [
                'num' => '02',
                'title' => 'Meet your<br>top 1% shortlist',
                'desc' => 'Within 48–72 hours, receive 4–6 pre-vetted, English-fluent candidates matched for skill, experience, and fit. We do the hard part. You just interview and hire your favorite.',
            ],
            [
                'num' => '03',
                'title' => 'We handle pay<br>& compliance',
                'desc' => 'In a single session, interview all candidates. Pick the best fit and hire directly. Can’t pick just 1? You don’t have to. At 70% savings, hire a team for the price of 1 U.S. hire.',
            ],
        ];
    }

    /**
     * The same three steps as /hire-for-less/ titles them. Production runs the
     * two pages off one section with different step headings, so this shares
     * hireVa4ProcessSteps()'s bodies rather than restating them.
     */
    public static function hireForLessProcessSteps(): array
    {
        $titles = [
            'Tell Us Your<br>Ideal Hire',
            'We Screen<br>Your Shortlist',
            'Interview and<br>Hire Your Favorite',
        ];

        return array_map(
            static fn (array $step, string $title) => [...$step, 'title' => $title],
            self::hireVa4ProcessSteps(),
            $titles,
        );
    }

    public static function renderHireVa4ProcessSteps(array $overrides = [], ?array $steps = null): string
    {
        $data = [];
        self::encodeRepeater('steps', 'field_process_steps_block_steps', $steps ?? self::hireVa4ProcessSteps(), $data);

        return self::patternBlock('process-steps', array_merge($data, $overrides));
    }

    // --- HIRE-VA-4: TESTIMONIALS ---
    public static function hireVa4Testimonials(): array
    {
        $img = self::imgBaseHireVa4();

        return [
            [
                'company' => 'PRES Property Management',
                'quote' => '“I can\'t say enough about how every step of the way it just wowed me.”',
                'video_url' => 'https://vimeo.com/1067577208',
                'image' => self::homeImg('PRES-Property-Management.jpg'),
                'duration' => '00:38',
            ],
            [
                'company' => 'Coldwell Banker',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => self::homeImg('Coldwell-Banker.jpg'),
                'duration' => '00:19',
            ],
            [
                'company' => 'Carbon Solutions Group',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => self::homeImg('Carbon-Solutions-Group.jpg'),
                'duration' => '00:55',
            ],
            [
                'company' => 'The Zen Zone Wellness',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => self::homeImg('The-Zen-Zone-Wellness.jpg'),
                'duration' => '04:06',
            ],
            [
                'company' => 'Color Job',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => self::homeImg('Color-Job.jpg'),
                'duration' => '01:56',
            ],
            [
                'company' => 'Connect Church Colorado',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => self::homeImg('Connect-Church-Colorado.jpg'),
                'duration' => '02:45',
            ],
            [
                'company' => 'Cash is King',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => self::homeImg('Cash-is-King.jpg'),
                'duration' => '02:39',
            ],
            [
                'company' => 'Liberty Hill',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates”',
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => self::homeImg('Liberty-Hill.jpg'),
                'duration' => '02:31',
            ],
            [
                'company' => 'RE / MAX',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it’s been a game changer for me.”',
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => self::homeImg('RE-MAX.jpg'),
                'duration' => '01:02',
            ],
            [
                'company' => 'Realty One Group',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => self::homeImg('Realty-One-Group.jpg'),
                'duration' => '01:43',
            ],
            [
                'company' => 'OneUp Sportz',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => self::homeImg('OneUp-Sportz-01.jpg'),
                'duration' => '01:06',
            ],
            [
                'company' => 'Greener Hill Psychiatric',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => self::homeImg('Greener-Hill-Psychiatric.jpg'),
                'duration' => '05:59',
            ],
            [
                'company' => 'Diamond Detox',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => self::homeImg('Diamond-Detox.jpg'),
                'duration' => '00:43',
            ],
            [
                'company' => 'Ad Center 360',
                'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => self::homeImg('Ad-Center-360.jpg'),
                'duration' => '00:36',
            ],
        ];
    }

    /**
     * The six reviews production actually shows under "Client Reviews" on
     * /hire-va-4/ and /hire-for-less/, in production's order.
     *
     * The full hireVa4Testimonials() set is 14, which rendered five grid rows
     * against production's two and made the page ~1460px taller than it should
     * be. The wider wall still belongs on /reviews/, which is why this selects
     * rather than shortening the source list.
     */
    public static function hireVa4FeaturedTestimonials(): array
    {
        $order = [
            'PRES Property Management',
            'Carbon Solutions Group',
            'Coldwell Banker',
            'The Zen Zone Wellness',
            'Connect Church Colorado',
            'Color Job',
        ];

        $byCompany = array_column(self::hireVa4Testimonials(), null, 'company');

        return array_values(array_filter(array_map(
            static fn (string $company) => $byCompany[$company] ?? null,
            $order,
        )));
    }

    public static function renderHireVa4Testimonials(array $overrides = [], ?array $testimonials = null): string
    {
        $data = [];
        self::encodeRepeater('testimonials', 'field_testimonials_block_testimonials', $testimonials ?? self::hireVa4Testimonials(), $data);

        return self::patternBlock('testimonials', array_merge($data, $overrides));
    }

    // --- HIRE-VA-4: FAQS ---
    public static function hireVa4Faqs(): array
    {
        return [
            [
                'q' => 'What countries do you hire from?',
                'a' => '<p>We focus on four key regions:</p><ul><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p>Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their exceptional English fluency with minimal accents, strong cultural alignment with US business practices, and convenient time zone overlap with North America.</p>',
            ],
            [
                'q' => 'How do taxes & payroll work when hiring Virtual Assistants?',
                'a' => '<p>Our partner company takes care of all payroll and compliance requirements for your Virtual Assistant. This means you can focus on growing your business while they handle tax compliance, payroll processing, legal requirements, and international payment regulations.</p>',
            ],
            [
                'q' => 'How do you get paid?',
                'a' => '<p>It’s simple – we charge a one-time flat fee, but only after you’ve found your perfect match. Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>',
            ],
            [
                'q' => 'What\'s the difference between Staffing and Recruiting Agencies?',
                'a' => '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly. This attracts higher-quality talent and eliminates ongoing middleman costs.</p>',
            ],
            [
                'q' => 'What if I have questions and need help after hiring?',
                'a' => '<p>After hiring your Virtual Assistant, you’ll have access to a dedicated Customer Success Manager who will help ensure your success with reviewing performance, monitoring progress, training guidance, and any other questions.</p>',
            ],
            [
                'q' => 'What if they don\'t turn out to be a good fit?',
                'a' => '<p>While it’s rare to have issues since candidates are thoroughly vetted by both our team and you, we offer a 12-month replacement guarantee at no extra cost and unlimited candidate interviews to ensure you find the best match.</p>',
            ],
            [
                'q' => 'How is their English and Communication skills?',
                'a' => '<p>We maintain extremely high standards for English fluency. All candidates must submit an English voice recording, and we only select those with fluent English and minimal accents. Only the best communicators make it through our screening.</p>',
            ],
            [
                'q' => 'Can I start with Part-time?',
                'a' => '<p>Yes, you can start with either part-time or full-time. The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>',
            ],
            [
                'q' => 'What time zone will they be working in?',
                'a' => '<p>Your Virtual Assistant will work according to your schedule and time zone. They’re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>',
            ],
            [
                'q' => 'How much does the average Virtual Assistant cost?',
                'a' => '<p>Virtual Assistant hourly rates depend on their skills and experience: Entry Level is $6-$10 per hour, and Highly Experienced is $11-$15 per hour. The hourly rate you agree to pay goes directly to your Virtual Assistant.</p>',
            ],
        ];
    }

    public static function renderHireVa4Faq(array $overrides = [], ?array $faqs = null): string
    {
        $data = [];
        $faqs = $faqs ?? self::hireVa4Faqs();
        $data['faqs'] = count($faqs);
        $data['_faqs'] = 'field_accordion_faq_block_faqs';
        foreach ($faqs as $i => $item) {
            $data["faqs_{$i}_question"] = $item['q'];
            $data["_faqs_{$i}_question"] = 'field_accordion_faq_block_faqs_question';
            $data["faqs_{$i}_answer"] = $item['a'];
            $data["_faqs_{$i}_answer"] = 'field_accordion_faq_block_faqs_answer';
        }

        return self::patternBlock('accordion-faq', array_merge($data, $overrides));
    }

    /**
     * Full client testimonial set for the /vathankyou/ post-booking page —
     * migrated from the legacy site's equivalent page. Order matches the
     * source page. Where a testimonial already existed in testimonials()
     * (same company), reuses that image instead of re-downloading it.
     */
    public static function vaThankYouTestimonials(): array
    {
        return [
            [
                'video_url' => 'https://vimeo.com/1067577208',
                'image' => self::homeImg('PRES-Property-Management.jpg'),
                'duration' => '00:38',
                'quote' => '“I can\'t say enought about how every step of the way it just wowed me.”',
                'company' => 'PRES Property Management',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => self::homeImg('Coldwell-Banker.jpg'),
                'duration' => '00:19',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'company' => 'Coldwell Banker',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => self::homeImg('Carbon-Solutions-Group.jpg'),
                'duration' => '00:55',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'company' => 'Carbon Solutions Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => self::homeImg('The-Zen-Zone-Wellness.jpg'),
                'duration' => '04:06',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'company' => 'The Zen Zone Wellness',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => self::homeImg('Color-Job.jpg'),
                'duration' => '01:56',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'company' => 'Color Job',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => self::homeImg('Connect-Church-Colorado.jpg'),
                'duration' => '02:45',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'company' => 'Connect Church Colorado',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => self::homeImg('Cash-is-King.jpg'),
                'duration' => '02:39',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'company' => 'Cash is King',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => self::homeImg('Liberty-Hill.jpg'),
                'duration' => '02:31',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates”',
                'company' => 'Liberty Hill',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => self::homeImg('RE-MAX.jpg'),
                'duration' => '01:02',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it’s been a game changer for me.”',
                'company' => 'RE / MAX',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => self::homeImg('Realty-One-Group.jpg'),
                'duration' => '01:43',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'company' => 'Realty One Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => self::homeImg('OneUp-Sportz.jpg'),
                'duration' => '01:06',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577598',
                'image' => self::homeImg('OneUp-Sportz.jpg'),
                'duration' => '01:40',
                'quote' => '“It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => self::homeImg('Greener-Hill-Psychiatric.jpg'),
                'duration' => '05:59',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'company' => 'Greener Hill Psychiatric',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => self::homeImg('Diamond-Detox.jpg'),
                'duration' => '00:43',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'company' => 'Diamond Detox',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => self::homeImg('Ad-Center-360.jpg'),
                'duration' => '00:36',
                'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                'company' => 'Ad Center 360',
            ],
            [
                'video_url' => 'https://vimeo.com/1126185287',
                'image' => self::homeImg('On-The-Outskirts.jpg'),
                'duration' => '01:45',
                'quote' => '“...he went out and found, I think, four applicants for me, all of which I thought would have been a good fit.”',
                'company' => 'On The Outskirts',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042650',
                'image' => self::homeImg('JLV-Construction.jpg'),
                'duration' => '01:29',
                'quote' => '“I mean for us Let us say as far as our growth you guys are in our Plan to contact us to start growing.”',
                'company' => 'JLV Construction',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042663',
                'image' => self::homeImg('Rewind-Bar.jpg'),
                'duration' => '00:38',
                'quote' => '“I\'ll give remote leverage 10 out of 10, man, from that, that\'s my personal experience and I would hire these guys again.”',
                'company' => 'Rewind Bar',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578625',
                'image' => self::homeImg('Immigration-Law-PLLC.jpg'),
                'duration' => '01:27',
                'quote' => '“Then after that second round of interviews, I put forth the offers to the candidates and then all three candidates accepted the position.”',
                'company' => 'Immigration Law PLLC',
            ],
            [
                'video_url' => 'https://vimeo.com/1123540522',
                'image' => self::homeImg('TNT-Creative-Ventures.jpg'),
                'duration' => '01:27',
                'quote' => '“We did hear about like good stories about VAs, how they helped companies grow because they took care of a lot of things that we missed.”',
                'company' => 'TNT Creative Ventures',
            ],
            [
                'video_url' => 'https://vimeo.com/1078577953',
                'image' => self::homeImg('Prospera-Restaurant-Solutions.jpg'),
                'duration' => '01:44',
                'quote' => '“Because if I want to hire someone I want someone that actually cares for the company who they work for and not just to get a paycheck.”',
                'company' => 'Prospera Restaurant Solutions',
            ],
            [
                'video_url' => 'https://vimeo.com/1077824150',
                'image' => self::homeImg('Charo-Enterprise-LLC.jpg'),
                'duration' => '01:26',
                'quote' => '“It\'s not only what they promised, but also what they delivered.”',
                'company' => 'Charo Enterprise LLC',
            ],
            [
                'video_url' => 'https://vimeo.com/1123540482',
                'image' => self::homeImg('The-Acre-Hub.jpg'),
                'duration' => '01:05',
                'quote' => '“Take a chance because it was really easy to do, really easy to log in and great follow-up service, especially with Miguel.”',
                'company' => 'The Acre Hub',
            ],
            [
                'video_url' => 'https://vimeo.com/1135276084',
                'image' => self::homeImg('Securely-Insured.jpg'),
                'duration' => '01:01',
                'quote' => '“Being able to find somebody to help our clients and also build those relationships is a huge thing for us.”',
                'company' => 'Securely Insured',
            ],
            [
                'video_url' => 'https://vimeo.com/1127905641',
                'image' => self::homeImg('ACR-Maintenance-Contractors.jpg'),
                'duration' => '01:54',
                'quote' => '“If you\'re looking for a high caliber, high producing type of employee, I would say this is the way to go.”',
                'company' => 'ACR Maintenance Contractors',
            ],
            [
                'video_url' => 'https://vimeo.com/1078887578',
                'image' => self::homeImg('Decoded-Clouds.jpg'),
                'duration' => '00:46',
                'quote' => '“It was also very efficient because I was able to just have one call and go through each candidate individually.”',
                'company' => 'Decoded Clouds',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042676',
                'image' => self::homeImg('Swift-Mortgage-Company.jpg'),
                'duration' => '01:04',
                'quote' => '“...I ended up hiring two at that one first-time interview, and it was great.”',
                'company' => 'Swift Mortgage Company',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042637',
                'image' => self::homeImg('Blue-Sky-ATM.jpg'),
                'duration' => '00:30',
                'quote' => '“The level of person that you\'re hiring for the amount of money that you\'re paying is a great deal.”',
                'company' => 'Blue Sky ATM',
            ],
            [
                'video_url' => 'https://vimeo.com/1125135332',
                'image' => self::homeImg('Mobile-Mixologist.jpg'),
                'duration' => '02:18',
                'quote' => '“Overall the experience has been really nice. It happened faster than I expected and now I\'m just kind of trying to get everything on board.”',
                'company' => 'Mobile Mixologist',
            ],
            [
                'video_url' => 'https://vimeo.com/1097826630',
                'image' => self::homeImg('The-Legal-Kid-Foundation.jpg'),
                'duration' => '04:02',
                'quote' => '“Even though things may not have worked out with the first person, you guys still gave me an opportunity to come back and find the right fit.”',
                'company' => 'The Legal Kid Foundation',
            ],
            [
                'video_url' => 'https://vimeo.com/1140407807',
                'image' => self::homeImg('THT-Realtors.jpg'),
                'duration' => '01:36',
                'quote' => '“Nick was phenomenal. He listened very well to what our needs were and the six candidates he provided for us were spot on.”',
                'company' => 'THT Realtors',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042691',
                'image' => self::homeImg('SoCal-Mortgage.jpg'),
                'duration' => '01:23',
                'quote' => '“I actually, after, you know, working with her and seeing the candidates that she gave me, I was like, wait, I like this.”',
                'company' => 'SoCal Mortgage',
            ],
            [
                'video_url' => 'https://vimeo.com/1099642433',
                'image' => self::homeImg('soccer-stars.jpg'),
                'duration' => '02:17',
                'quote' => '“...the payoff that we\'ve had has been absolutely invaluable, like incredible.”',
                'company' => 'soccer stars',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578536',
                'image' => self::homeImg('Doran-Industries-LLC.jpg'),
                'duration' => '01:00',
                'quote' => '“Usually I\'m having to go through hundreds of resumes, spend a ton of money on marketing to get that position out in the world.”',
                'company' => 'Doran Industries LLC',
            ],
            [
                'video_url' => 'https://vimeo.com/1077824139',
                'image' => self::homeImg('Mountain-View-Headache-and-Spine.jpg'),
                'duration' => '01:28',
                'quote' => '“...we went originally with wanting one VA, but we liked both of them so much we ended up giving both of them a position.”',
                'company' => 'Mountain View Headache and Spine',
            ],
            [
                'video_url' => 'https://vimeo.com/1140407850',
                'image' => self::homeImg('Bench-Accounting.jpg'),
                'duration' => '01:08',
                'quote' => '“We\'ve hired 31 people in the last four months with Remote Leverage, pretty incredible numbers.”',
                'company' => 'Bench Accounting',
            ],
            [
                'video_url' => 'https://vimeo.com/1095866154?',
                'image' => self::homeImg('Haus-of-Her-Studios.jpg'),
                'duration' => '02:41',
                'quote' => '“I just wanted somebody to help me find the right person that has experience in finding the right people to kind of just make the process quicker.”',
                'company' => 'Haus of Her Studios',
            ],
            [
                'video_url' => 'https://vimeo.com/1125135365',
                'image' => self::homeImg('NVR-Solutions.jpg'),
                'duration' => '04:13',
                'quote' => '“Here, with Remote Leverage, I can get somebody working full-time. I think it would have been just too costly to get somebody in the US.”',
                'company' => 'NVR Solutions',
            ],
            [
                'video_url' => 'https://vimeo.com/1091100199',
                'image' => self::homeImg('Bixby-Electric-Inc.jpg'),
                'duration' => '01:03',
                'quote' => '“All honesty, better than half of my employees that live in the United States.”',
                'company' => 'Bixby Electric Inc.',
            ],
            [
                'video_url' => 'https://vimeo.com/1128233521',
                'image' => self::homeImg('Jeremi-039-s-Auto-Repair.jpg'),
                'duration' => '02:08',
                'quote' => '“After doing the math, I realized this one hire costing me a little bit less would allow me to bring on two more skilled technicians.”',
                'company' => 'Jeremi&#039;s Auto Repair',
            ],
            [
                'video_url' => 'https://vimeo.com/1161422210',
                'image' => self::homeImg('Addiction-Recovery-Service.jpg'),
                'duration' => '01:06',
                'quote' => '“I was impressed with their reputation in that regard and the fact that they didn\'t take salary away from the person that I employed.”',
                'company' => 'Addiction Recovery Service',
            ],
            [
                'video_url' => 'https://vimeo.com/1094334377',
                'image' => self::homeImg('JBA-Equities.jpg'),
                'duration' => '02:30',
                'quote' => '“Also something to also point out is that, you know, we\'re, we\'re obviously looking to, to move quickly.”',
                'company' => 'JBA Equities',
            ],
            [
                'video_url' => 'https://vimeo.com/1078889153',
                'image' => self::homeImg('Providence-Wave-Group.jpg'),
                'duration' => '02:19',
                'quote' => '“You know, the form itself, quite frankly, there was more flexibility with remote leverage because not only the ongoing support, but how easy it was to onboard.”',
                'company' => 'Providence Wave Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1093298736',
                'image' => self::homeImg('Helpful-Home-Buyers.jpg'),
                'duration' => '02:21',
                'quote' => '“I had four or five applicants that were ready for me to review within less than a week.”',
                'company' => 'Helpful Home Buyers',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578563',
                'image' => self::homeImg('Juliana-Makeup-Hair-Group.jpg'),
                'duration' => '01:31',
                'quote' => '“Then he helped me through again, like with picking the right person based on my needs.”',
                'company' => 'Juliana Makeup + Hair Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1083042716',
                'image' => self::homeImg('Ware-Landscaping.jpg'),
                'duration' => '01:03',
                'quote' => '“...we were really happy that the six that we got, that there were definitely two in the first round that stood out to us.”',
                'company' => 'Ware Landscaping',
            ],
            [
                'video_url' => 'https://vimeo.com/1091100174',
                'image' => self::homeImg('Fast-Real-Estate.jpg'),
                'duration' => '02:31',
                'quote' => '“I\'m always big on doing it right the first time so you don\'t got to do it twice.”',
                'company' => 'Fast Real Estate',
            ],
            [
                'video_url' => 'https://vimeo.com/1088297246',
                'image' => self::homeImg('The-Wright-Legal-Group.jpg'),
                'duration' => '01:16',
                'quote' => '“...the quality of candidates was much higher, in my opinion. We got people who, in fact, we wanted to really hire.”',
                'company' => 'The Wright Legal Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1088297231',
                'image' => self::homeImg('The-Landscape-Company.jpg'),
                'duration' => '02:05',
                'quote' => '“It\'s a price point that you could maybe get two or three VAs doing three different tasks for the same price as America.”',
                'company' => 'The Landscape Company',
            ],
            [
                'video_url' => 'https://vimeo.com/1131671096',
                'image' => self::homeImg('Perfect-Finish.jpg'),
                'duration' => '01:12',
                'quote' => '“It\'s been, hands down, the best thing that\'s helped his business.”',
                'company' => 'Perfect Finish',
            ],
            [
                'video_url' => 'https://vimeo.com/1135276131',
                'image' => self::homeImg('California-Sleep-Solutions.jpg'),
                'duration' => '01:25',
                'quote' => '“The whole process, I\'d say we spent about 15 minutes with each applicant that ended up with two candidates being hired on.”',
                'company' => 'California Sleep Solutions',
            ],
            [
                'video_url' => 'https://vimeo.com/1101824762',
                'image' => self::homeImg('Hawaiian-Philanthropy.jpg'),
                'duration' => '02:39',
                'quote' => '“...the quality that you get is so much higher than I\'ve seen before.”',
                'company' => 'Hawaiian Philanthropy',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578583',
                'image' => self::homeImg('My-Bug-Expert.jpg'),
                'duration' => '00:54',
                'quote' => '“I think she\'ll do very well just like our other ones.”',
                'company' => 'My Bug Expert',
            ],
            [
                'video_url' => 'https://vimeo.com/1093298749',
                'image' => self::homeImg('CAM-Property-Services.jpg'),
                'duration' => '01:38',
                'quote' => '“I think within two or three weeks, we had the four others hired.”',
                'company' => 'CAM Property Services',
            ],
            [
                'video_url' => 'https://vimeo.com/1078575353',
                'image' => self::homeImg('Ascent-Multifamily.jpg'),
                'duration' => '02:04',
                'quote' => '“...I believe that you probably saved me a lot of time just in bringing the applicants, the VAs to us that were somewhat vetted or totally vetted.”',
                'company' => 'Ascent Multifamily',
            ],
            [
                'video_url' => 'https://vimeo.com/1136876675',
                'image' => self::homeImg('DDV-Law.jpg'),
                'duration' => '00:59',
                'quote' => '“Some of the things that I picked up about Nora were things you can\'t teach.”',
                'company' => 'DDV Law',
            ],
            [
                'video_url' => 'https://vimeo.com/1104693332?',
                'image' => self::homeImg('Watson-Psychiatry.jpg'),
                'duration' => '01:35',
                'quote' => '“It was so cool because we got to rate each candidate from a zero to 10.”',
                'company' => 'Watson Psychiatry',
            ],
            [
                'video_url' => 'https://vimeo.com/1095866122',
                'image' => self::homeImg('The-Young-Agency.jpg'),
                'duration' => '01:57',
                'quote' => '“I just thought, wow, how lucky I am to have found Sarah and it\'s just been a joy working with her so far.”',
                'company' => 'The Young Agency',
            ],
            [
                'video_url' => 'https://vimeo.com/1097826693',
                'image' => self::homeImg('Reno-Weight-Loss.jpg'),
                'duration' => '02:00',
                'quote' => '“Then she started on Tuesday and from working with other recruiting companies, like that was pretty fast.”',
                'company' => 'Reno Weight Loss',
            ],
            [
                'video_url' => 'https://vimeo.com/1080057295',
                'image' => self::homeImg('Ricky-Ricardo-Home-Repairs.jpg'),
                'duration' => '02:23',
                'quote' => '“So this was frankly, I feel like this was the thing to save my business.”',
                'company' => 'Ricky Ricardo Home Repairs',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578641',
                'image' => self::homeImg('ProPharma-Distribution.jpg'),
                'duration' => '01:12',
                'quote' => '“I would highly recommend, especially with the guarantees that you provide, and you\'ve done everything that you\'ve said, so I would definitely encourage.”',
                'company' => 'ProPharma Distribution',
            ],
            [
                'video_url' => 'https://vimeo.com/1161422285',
                'image' => self::homeImg('Gotta-Guy.jpg'),
                'duration' => '00:48',
                'quote' => '“She came with the experience that we were looking for with the background that was very relevant to the role that we were looking to fill.”',
                'company' => 'Gotta Guy',
            ],
            [
                'video_url' => 'https://vimeo.com/1086578602',
                'image' => self::homeImg('Anchorage-Care-Coordination.jpg'),
                'duration' => '00:58',
                'quote' => '“Brie was just so accommodating and nice to me that I chose you guys because of that.”',
                'company' => 'Anchorage Care Coordination',
            ],
            [
                'video_url' => 'https://vimeo.com/1088297429',
                'image' => self::homeImg('Ware-Disposal.jpg'),
                'duration' => '02:26',
                'quote' => '“We\'re procuring so much business, which is the reason that we came to need these virtual assistants via your guys\' organization.”',
                'company' => 'Ware Disposal',
            ],
            [
                'video_url' => 'https://vimeo.com/1078888486',
                'image' => self::homeImg('Smiley-Injury-Law.jpg'),
                'duration' => '02:29',
                'quote' => '“She has experience in a much larger firm, doing a lot more volume.”',
                'company' => 'Smiley Injury Law',
            ],
            [
                'video_url' => 'https://vimeo.com/1134210766',
                'image' => self::homeImg('RCA-Zone-HVAC.jpg'),
                'duration' => '01:03',
                'quote' => '“We did hire one of those six candidates, which was great.”',
                'company' => 'RCA Zone HVAC',
            ],
            [
                'video_url' => 'https://vimeo.com/1134210729',
                'image' => self::homeImg('PHYSX-Promotions.jpg'),
                'duration' => '01:02',
                'quote' => '“Really just, I think already been like a big, big improvement and it\'s gonna be like a big kind of asset to our team.”',
                'company' => 'PHYSX Promotions',
            ],
            [
                'video_url' => 'https://vimeo.com/1161422249',
                'image' => self::homeImg('Indoor-Air-Programs.jpg'),
                'duration' => '01:02',
                'quote' => '“Actually, I loved the first candidate and I didn\'t think it could get better until I heard the second candidate.”',
                'company' => 'Indoor Air Programs',
            ],
            [
                'video_url' => 'https://vimeo.com/1126185254',
                'image' => self::homeImg('Emporia-Consulting-LLC.jpg'),
                'duration' => '03:30',
                'quote' => '“...we were able to find the selected applicant on the first call.”',
                'company' => 'Emporia Consulting LLC',
            ],
            [
                'video_url' => 'https://vimeo.com/1078575324',
                'image' => self::homeImg('Sankofa-Company.jpg'),
                'duration' => '01:27',
                'quote' => '“Honestly, you can ask David. I was sold within the first 10 seconds. I\'m like, yeah, this is my person.”',
                'company' => 'Sankofa Company',
            ],
            [
                'video_url' => 'https://vimeo.com/1131671060',
                'image' => self::homeImg('My-Repair-App.jpg'),
                'duration' => '01:00',
                'quote' => '“AI agreed on the same person. Great experience all around. We ended up hiring an individual named Jose.”',
                'company' => 'My Repair App',
            ],
            [
                'video_url' => 'https://vimeo.com/1136876707',
                'image' => self::homeImg('Chick-fil-A.jpg'),
                'duration' => '00:46',
                'quote' => '“Everybody that we interviewed are professionals, years of experience, and we\'re in their field for quite a while. Really promising!”',
                'company' => 'Chick-fil-A',
            ],
            [
                'video_url' => 'https://vimeo.com/1078575337',
                'image' => self::homeImg('My-Local-Car-Wash.jpg'),
                'duration' => '02:20',
                'quote' => '“After she hopped back off, I immediately was like, this person\'s a 12.”',
                'company' => 'My Local Car Wash',
            ],
            [
                'video_url' => 'https://vimeo.com/1094334359',
                'image' => self::homeImg('Reliable-Receptionist.jpg'),
                'duration' => '02:05',
                'quote' => '“Once that realization came to be, it made it easier to see that there could be distinct advantages to hiring remotely and offshore.”',
                'company' => 'Reliable Receptionist',
            ],
            [
                'video_url' => 'https://vimeo.com/1101824789',
                'image' => self::homeImg('Clean-Cozy-Home.jpg'),
                'duration' => '02:32',
                'quote' => '“I\'m very happy with the individual that I selected, and she just started last Friday.”',
                'company' => 'Clean Cozy Home',
            ],
            [
                'video_url' => 'https://vimeo.com/1161422231',
                'image' => self::homeImg('Verum-AV-Solutions.jpg'),
                'duration' => '00:59',
                'quote' => '“We were very lucky and fortunate that in the first round of candidates, we found the person that we were looking for.”',
                'company' => 'Verum AV Solutions',
            ],
            [
                'video_url' => 'https://vimeo.com/1088657235',
                'image' => self::homeImg('Conservice.jpg'),
                'duration' => '01:23',
                'quote' => '“He he rounded up five applicants in under a week and all five of them were good.”',
                'company' => 'Conservice',
            ],
        ];
    }

    /**
     * Full 77-item video testimonial grid used on the /reviews/ page
     * (identical dataset to vaThankYouTestimonials(), migrated 1:1 from
     * the live site's "Client Reviews" section).
     */
    public static function renderReviewsTestimonials(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('testimonials', 'field_testimonials_block_testimonials', self::vaThankYouTestimonials(), $data);

        return self::patternBlock('testimonials', array_merge($data, $overrides));
    }

    // --- VA PRICING TALENT MARQUEE (vapricing / reviews hero) ---
    public static function vaPricingTalentCards(): array
    {
        return [
            [
                'name' => 'Agustín Sosa',
                'title' => 'Legal Assistant',
                'desc' => '5+ years of experience as a detail-oriented Legal Assistant, skilled in document preparation, case management, legal research, and client communication, supporting attorneys in corporate and litigation matters.',
                'logo' => self::homeImg('Frame-74.png'),
                'bg' => self::homeImg('cont-01.png'),
            ],
            [
                'name' => 'Fernando Almeida',
                'title' => 'Sales (BDR)',
                'desc' => '3+ years of experience in B2B SaaS outbound sales. Consistently top 10% of the team. Fluent in cold calling, cold emailing, and LinkedIn outreach.',
                'logo' => self::homeImg('amazon-01.png'),
                'bg' => self::homeImg('con-04.png'),
            ],
            [
                'name' => 'André Vilalobos',
                'title' => 'Graphic Designer',
                'desc' => '8+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.',
                'logo' => self::homeImg('State-Farm-01.png'),
                'bg' => self::homeImg('con-07.png'),
            ],
            [
                'name' => 'Juliana Silva',
                'title' => 'Lead Generation (SDR)',
                'desc' => '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.',
                'logo' => self::homeImg('mercado.png'),
                'bg' => self::homeImg('cont-02.png'),
            ],
            [
                'name' => 'Laura Valentina',
                'title' => 'Customer Support',
                'desc' => '+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles while handling email, chat, and phone support.',
                'logo' => self::homeImg('Bank-of-America-01.png'),
                'bg' => self::homeImg('con-08.png'),
            ],
            [
                'name' => 'Valeria Andrea',
                'title' => 'Medical / Healthcare',
                'desc' => '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.',
                'logo' => self::homeImg('Allstate-01.png'),
                'bg' => self::homeImg('con-05.png'),
            ],
            [
                'name' => 'Sofía Pérez',
                'title' => 'Marketing',
                'desc' => '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness and customer engagement.',
                'logo' => self::homeImg('Frame-74-1.png'),
                'bg' => self::homeImg('cont-03.png'),
            ],
            [
                'name' => 'Luana Dias',
                'title' => 'Executive Assistant',
                'desc' => '3+ years of experience supporting C-level executives in fast-paced environments. I thrive on organization, anticipate needs, and protect my executive\'s time like it\'s my own.',
                'logo' => self::homeImg('NU-bank-01.png'),
                'bg' => self::homeImg('con-06.png'),
            ],
        ];
    }

    public static function renderVaPricingTalentMarquee(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('talent_cards', 'field_talent_marquee_block_talent_cards', self::vaPricingTalentCards(), $data);

        return self::patternBlock('talent-marquee', array_merge($data, $overrides), ['align' => 'full']);
    }

    public static function renderVaPricingTalentGrid(array $overrides = []): string
    {
        return self::renderTalentGrid($overrides);
    }

    /** @param  array|null  $cards  Replaces the preset talent cards entirely. */
    public static function renderTalentGrid(array $overrides = [], ?array $cards = null): string
    {
        $data = [];
        self::encodeRepeater('talent_cards', 'field_talent_grid_block_talent_cards', $cards ?? self::vaPricingTalentCards(), $data);

        return self::patternBlock('talent-grid', array_merge($data, $overrides));
    }

    /** Stacked numbered step cards (headline, subheadline, steps[number,title,text,image]). */
    public static function renderProcessStepCards(array $steps, array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('steps', 'field_process_step_cards_block_steps', $steps, $data);

        return self::patternBlock('process-step-cards', array_merge($data, $overrides));
    }

    // --- ROLES PRICING GRID (vapricing / reviews funnel) ---
    public static function rolesPricingGridCards(): array
    {
        return [
            [
                'photo' => self::homeImg('Group-81.jpg'),
                'title' => 'Administrative Assistant',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Virtual Administrative Assistants makes your work life easier by:',
                'tasks' => implode("\n", [
                    'Managing phone and email communication',
                    'Daily admin to keep businesses running',
                    'Organizing calendars, setting appointments',
                    'Handling invoices and payments',
                    'They own daily admin so you can focus on more important tasks.',
                ]),
                'tools' => json_encode([
                    self::homeImg('office-1.png'),
                    self::homeImg('google_work-1.png'),
                    self::homeImg('slack-1.png'),
                    self::homeImg('asana-1.png'),
                    self::homeImg('trello-1.png'),
                    self::homeImg('zoom-1.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-82.jpg'),
                'title' => 'Lead Generation (SDR)',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Lead Generation Virtual Assistants find new customers for your business by:',
                'tasks' => implode("\n", [
                    'Calling new & old prospects',
                    'Emailing and texting leads',
                    'Following up on leads',
                    'Booking sales meetings',
                    'They are great at connecting with people to grow your business.',
                ]),
                'tools' => json_encode([
                    self::homeImg('11-2.png'),
                    self::homeImg('hubspot-1.png'),
                    self::homeImg('Logos_tools-1.png'),
                    self::homeImg('out-1.png'),
                    self::homeImg('reply-1.png'),
                    self::homeImg('zoom-2.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-83.jpg'),
                'title' => 'Sales (BDR)',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Sales Virtual Assistants help you generate and close sales by:',
                'tasks' => implode("\n", [
                    'Finding new leads with calls and emails',
                    'Following up with interested leads',
                    'Setting up sales meetings',
                    'Giving sales presentations and demos',
                    'They communicate effectively and guide customers towards a purchase.',
                ]),
                'tools' => json_encode([
                    self::homeImg('apolo-1.png'),
                    self::homeImg('hubspot-1.png'),
                    self::homeImg('Logos_tools-2.png'),
                    self::homeImg('Logos_tools-1.png'),
                    self::homeImg('reply-1.png'),
                    self::homeImg('zoom-2.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-85.jpg'),
                'title' => 'Medical / Healthcare',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Medical / Healthcare Assistants help practices of all sizes grow by:',
                'tasks' => implode("\n", [
                    'Improve patient scheduling, intake',
                    'Reduce no-shows',
                    'Process medical records',
                    'Handle insurance claims',
                    'They are HIPAA compliant and help you focus on patient care and experience.',
                ]),
                'tools' => json_encode([
                    self::homeImg('ada-1.png'),
                    self::homeImg('advancedmd-1.png'),
                    self::homeImg('boomerang-1.png'),
                    self::homeImg('Logos_tools-3.png'),
                    self::homeImg('tomorrow-1.png'),
                    self::homeImg('office-1.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-87-1.jpg'),
                'title' => 'Marketing',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Marketing Virtual Assistants maximize your advertising budget and reach by:',
                'tasks' => implode("\n", [
                    'Strategize omnichannel campaigns',
                    'Managing your paid ad campaigns',
                    'Analyzing data to optimize spending',
                    'Preparing ROI reports on marketing campigns',
                    'They make every ad dollar count and bring quality leads to your business.',
                ]),
                'tools' => json_encode([
                    self::homeImg('hubspot-1.png'),
                    self::homeImg('advancedmd-1-1.png'),
                    self::homeImg('Braze.png'),
                    self::homeImg('Customer.png'),
                    self::homeImg('Logos_tools-1.png'),
                    self::homeImg('office-1-1.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-89.jpg'),
                'title' => 'Legal Assistant',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Legal Virtual Assistant help attorneys and law firms reclaim billable hours by:',
                'tasks' => implode("\n", [
                    'Handling client communications',
                    'Preparing & drafting documents',
                    'Managing billing and invoicing',
                    'Organizing calendars and dockets',
                    'They provide specialized support to help you focus on practing law.',
                ]),
                'tools' => json_encode([
                    self::homeImg('Clio.png'),
                    self::homeImg('Drive.png'),
                    self::homeImg('Imanage.png'),
                    self::homeImg('Legalon.png'),
                    self::homeImg('Surepoint.png'),
                    self::homeImg('Zapier.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-91.jpg'),
                'title' => 'Bookkeeping Assistant',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Bookkeeping Virtual Assistants help businesses stay on top of their finances by:',
                'tasks' => implode("\n", [
                    'Recording and categorizing transactions',
                    'Reconciling financial statements',
                    'Managing accounts payable & receivable',
                    'Generating financial reports',
                    'They keep your books accurate so you can focus on scaling your business.',
                ]),
                'tools' => json_encode([
                    self::homeImg('book.png'),
                    self::homeImg('xero.png'),
                    self::homeImg('puzzle.png'),
                    self::homeImg('intuit.png'),
                    self::homeImg('rillet.png'),
                    self::homeImg('zenI.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
            [
                'photo' => self::homeImg('Group-93.jpg'),
                'title' => 'Executive Assistant',
                'price' => '$6-$10 Per Hour',
                'intro' => 'Executive Virtual Assistants help busy leaders maximize their productivity by:',
                'tasks' => implode("\n", [
                    'Managing calendars & meetings',
                    'Drafting emails & correspondence',
                    'Coordinating projects & deadlines',
                    'Anticipating leadership needs',
                    'They act as your right hand so you can focus on leading.',
                ]),
                'tools' => json_encode([
                    self::homeImg('ChatGPT.png'),
                    self::homeImg('Clickup.png'),
                    self::homeImg('clockwise.png'),
                    self::homeImg('Jotform.png'),
                    self::homeImg('Notion.png'),
                    self::homeImg('Zapier.png'),
                ]),
                'cta_text' => 'Interview Assistants',
                'cta_url' => '#booking-footer',
            ],
        ];
    }

    public static function renderRolesPricingGrid(array $overrides = []): string
    {
        $data = [
            'headline' => 'Virtual Assistant Roles',
            '_headline' => 'field_roles_pricing_grid_block_headline',
        ];
        self::encodeRepeater('cards', 'field_roles_pricing_grid_block_cards', self::rolesPricingGridCards(), $data);

        $merged = array_merge($data, $overrides);

        // Carry the ACF key alongside any scalar override, the way _headline does.
        // Without it the value rides on the block's data array alone, which is one
        // refactor away from being ignored.
        foreach (['variant', 'columns'] as $key) {
            if (array_key_exists($key, $merged)) {
                $merged['_'.$key] = 'field_roles_pricing_grid_block_'.$key;
            }
        }

        return self::patternBlock('roles-pricing-grid', $merged, ['align' => 'full']);
    }

    public static function renderAboutHero(array $overrides = []): string
    {
        $data = [
            'headline' => 'The world leader in staffing solutions',
            'subtitle' => 'Great talent changes everything. <strong>Remote Leverage makes global hiring easier.</strong> We find top 1% global talent, you hire direct.',
            'button_text' => 'BOOK A CONSULTATION',
            'button_url' => '#booking-footer',
            'badge_text' => '2.5K+ pre-vetted candidates',
            'trusted_title' => 'TRUSTED BY SCALING TEAMS GLOBALLY',
        ];

        return self::patternBlock('about-hero', array_merge($data, $overrides), ['align' => 'full']);
    }

    public static function renderAboutStats(array $overrides = []): string
    {
        $data = [
            'section_title' => 'Why businesses choose us',
        ];

        return self::patternBlock('about-stats', array_merge($data, $overrides));
    }

    public static function renderAboutNarrative(array $overrides = []): string
    {
        $data = [
            'title_prefix' => 'About',
            'title' => 'Remote Leverage',
            'badge_text' => 'Remote Leverage',
        ];

        return self::patternBlock('about-narrative', array_merge($data, $overrides));
    }

    public static function renderAboutTalentBanner(array $overrides = []): string
    {
        $data = [
            'banner_text' => 'Great talent changes everything',
        ];

        return self::patternBlock('about-talent-banner', array_merge($data, $overrides), ['align' => 'full']);
    }

    public static function renderResultsPreview(array $overrides = []): string
    {
        $data = [
            'section_title' => 'Real Businesses, Real Results',
            'section_desc' => 'Every business needs the same thing – talent that helps them move faster, cut costs, and grow with confidence.',
        ];

        return self::patternBlock('results-preview', array_merge($data, $overrides));
    }

    public static function sampleApplicantVideos(): array
    {
        return [
            [
                'name' => 'Nazarena T.',
                'role' => 'Customer Service Rep.',
                'country' => 'Spain',
                'flag' => '🇪🇸',
                'rate' => '$10/hr',
                'bio' => 'Motivated and proactive Master’s of Science in International Business student with a Bachelor’s Degree in International Relations. Recognized for adaptability, critical thinking, and communication skills. Eager to contribute to dynamic teams in global organizations while further developing professional expertise.',
                'poster_file' => 'public/images/samples/posters/Nazarena-T.png',
                'video_url' => '/app/uploads/2026/01/Nazarena-T.-Customer-Service-Representative.mp4',
                'resume_url' => '/app/uploads/2026/01/Screenshot-2026-01-31-at-5.01.04-PM.png',
            ],
            [
                'name' => 'Laura V.',
                'role' => 'Appointment Setter',
                'country' => 'Costa Rica',
                'flag' => '🇨🇷',
                'rate' => '$10/hr',
                'bio' => 'Customer-focused professional with over 3 years of experience in SaaS, onboarding, customer success, inside sales, and technical support. Proven track record of guiding customers and internal teams through onboarding journeys, managing escalations, and supporting sales and operational processes. Skilled in training delivery, accurate data management, and cross-functional collaboration with Sales, Product, Legal, and Finance teams. Certified in Appointment Setting through ProSetter Academy and committed to continuous process improvement and exceptional customer satisfaction.',
                'poster_file' => 'public/images/samples/posters/Screenshot-2026-07-21-122538-1.png',
                'video_url' => '/app/uploads/2026/01/Laura-G.-Appointment-Setter-Remote-Leverage.mp4',
                'resume_url' => '/app/uploads/2026/01/Laura-G.-Resume.png',
            ],
            [
                'name' => 'Gabriela P.',
                'role' => 'Salesperson',
                'country' => 'Dominican Republic',
                'flag' => '🇩🇴',
                'rate' => '$9/hr',
                'bio' => '',
                'poster_file' => 'public/images/samples/posters/Gabriela-P.png',
                'video_url' => '/app/uploads/2025/01/Gabby-P.-Dominican-Republic-Ready.mp4',
                'resume_url' => '/app/uploads/2025/04/1743631068559-b27107cd-0bda-401b-88cf-0dcff921e6ad-Resume-1_1.jpg',
            ],
            [
                'name' => 'Davleen S.',
                'role' => 'Customer Service Rep.',
                'country' => 'Dominican Republic',
                'flag' => '🇩🇴',
                'rate' => '$10/hr',
                'bio' => 'People-focused customer service and sales professional with a strong ability to build trust, understand client needs, and recommend solutions that drive results. Known for creating positive customer experiences and supporting teams with organization and follow-through.Fluent in English, French, and Spanish, with proven success working remotely and supporting Canadian & U.S.-based clients.',
                'poster_file' => 'public/images/samples/posters/Davleen-S.png',
                'video_url' => '/app/uploads/2026/01/Davleen-S.-Customer-Service-Rep.-Remote-Leverage-Video.mp4',
                'resume_url' => '/app/uploads/2026/01/Davleen-Resume-Customer-Service-Rep-Remote-Leverage.png',
            ],
            [
                'name' => 'Emily E.',
                'role' => 'Customer Service/Appointment Setter',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'bio' => 'Experienced professional with over 6 years of Customer Service expertise. Skilled in education, tutoring, ESL, and client relations. Demonstrated ability to multitask efficiently and excel under pressure.',
                'poster_file' => 'public/images/samples/posters/Emily-E.png',
                'video_url' => '/app/uploads/2026/01/Shumpei-K.-Data-Analyst-Remote-Leverage-1.mp4',
                'resume_url' => '/app/uploads/2026/01/Emily-Appointment-Setter-Resume-Remote-Leverage.png',
            ],
            [
                'name' => 'Mishelle R.',
                'role' => 'Customer Service Rep.',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$8/hr',
                'bio' => 'Dedicated customer service professional with eight years of experience. Solid team player with proven skills in establishing rapport with Company, Campaign, Agents and clients. Experience working with companies in Financial, Leasing, Medical and Customer service sectors.',
                'poster_file' => 'public/images/samples/posters/Mishelle-R.png',
                'video_url' => '/app/uploads/2025/01/Andrea-Mishelle-rodriguez-rosales-Customer-Service-Final-VEED.mp4',
                'resume_url' => '/app/uploads/2025/04/Mishelle-R.-Resume-.png',
            ],
            [
                'name' => 'Leonardo A.',
                'role' => 'Accountant / Bookkeeper / CFO',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$10/hr',
                'bio' => 'Founder of Academy for the preparation of prospective university students. Responsible for advertisement, acquisition of customers, development of material, and all business operations.',
                'poster_file' => 'public/images/samples/posters/Leonardo-A.png',
                'video_url' => '/app/uploads/2025/10/Leo-B.-Accounting-1.mp4',
                'resume_url' => '/app/uploads/2025/10/Leonardo-Resume-Brazil.png',
            ],
            [
                'name' => 'Keleme M.',
                'role' => 'Accountant / Bookkeeper / CPA',
                'country' => 'Philippines',
                'flag' => '🇵🇭',
                'rate' => '$9/hr',
                'bio' => 'Highly motivated and proactive PH, AU and US tax specialist, with an outstanding organizational skills and more than 4 years’ professional experience. Strong administrative professional, skilled in Sales, Marketing, Accreditation, Payroll, and Remittances seeking a role in a dynamic work environment.',
                'poster_file' => 'public/images/samples/posters/Keleme-M.png',
                'video_url' => '/app/uploads/2024/10/Keleme-Manang-Accounting-Philippines.mp4',
                'resume_url' => '/app/uploads/2024/10/Keleme-M.-Resume-pdf-724x1024.jpg',
            ],
            [
                'name' => 'Manuela R.',
                'role' => 'Lawyer / Paralegal / Property Mgmt',
                'country' => 'Argentina',
                'flag' => '🇦🇷',
                'rate' => '$12/hr',
                'bio' => 'I have 9 years of experience in the areas of customer service, project and property management, and executive support. I excel at fostering client relationships and providing exceptional administrative support. My diverse professional background has helped me develop strong organizational, communication, and problem-solving skills. I am adaptable, dependable, and committed to delivering high-quality outcomes in any professional environment.',
                'poster_file' => 'public/images/samples/posters/Screenshot-2026-07-21-134342-1-1.png',
                'video_url' => '/app/uploads/2025/01/Manuela-Romani-Lawyer-Final-VEED.mp4',
                'resume_url' => '/app/uploads/2025/04/Manuela-R.-Resume.png',
            ],
            [
                'name' => 'Melissa S.',
                'role' => 'Executive Assistant/Operations Manager',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$11/hr',
                'bio' => 'Executive Assistant with 20+ years of experience supporting executives and leadership teams across media, healthcare, logistics, engineering, and financial services. Expert in complex calendar/email management, confidential correspondence, board and trustee meeting preparation, travel logistics, and cross-functional coordination. Known for proactive problem-solving, meticulous organization, and enabling leaders to focus on strategic priorities.',
                'poster_file' => 'public/images/samples/posters/Melissa-S.png',
                'video_url' => '/app/uploads/2025/11/Melissa-S.-EA.mp4',
                'resume_url' => '/app/uploads/2025/11/Screenshot-2025-11-01-at-7.12.45-PM.png',
            ],
            [
                'name' => 'Sebastian D.',
                'role' => 'Digital Marketing Specialist / SEO',
                'country' => 'Ecuador',
                'flag' => '🇪🇨',
                'rate' => '$10/hr',
                'bio' => 'All-rounded Digital Professional with over 5+ years of combined experience in business development, digital marketing, web development, e-commerce, social media marketing, content and copywriting. I have embarked on different types of projects in my life, from building tech startups and starting a business to working in digital agencies; all of which have led me to acquire a vast array of skills that allows me to perform in most of today’s competitive working environments.',
                'poster_file' => 'public/images/samples/posters/Sebastian-D.png',
                'video_url' => '/app/uploads/2025/01/Sebastian-Delgado-Ecuador-Final-VEED.mp4',
                'resume_url' => '/app/uploads/2025/04/Sebastian-D.-Resume.png',
            ],
            [
                'name' => 'Emma M.',
                'role' => 'Digital Marketing Specialist / SEO',
                'country' => 'Honduras',
                'flag' => '🇭🇳',
                'rate' => '$12/hr',
                'bio' => 'Innovative and tech-savvy digital marketing specialist with over 5 years of experience in content creation, SEO strategy, and social media management. Proven track record of driving measurable growth, launching impactful campaigns, and building strong client relationships. Native English and Spanish speaker with exceptional problem-solving and team collaboration skills, specializing in remote team management and project execution.',
                'poster_file' => 'public/images/samples/posters/Emma-M.png',
                'video_url' => '/app/uploads/2025/01/Emma-Mendoza-Digital-Marketer_-VEED.mp4',
                'resume_url' => '/app/uploads/2025/04/Emma-M.-Resume.png',
            ],
            [
                'name' => 'Kory E.',
                'role' => 'Graphic Designer',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$12/hr',
                'bio' => 'Accomplished graphic designer with over 10 years of specialized experience in high-stakes corporate collateral, visual branding, and digital marketing materials. Proven ability to excel in fast-paced environments, creating impactful assets that drive multi-million dollar business development initiatives. Proficient in Adobe Creative Suite, delivering exceptional results for teams across local, national, and international markets. Seeking a challenging remote role that leverages extensive design expertise and strong organizational skills.',
                'poster_file' => 'public/images/samples/posters/Kory-E.png',
                'video_url' => '/app/uploads/2026/01/Abbas_s-Video-Jan-31-2026.mp4',
                'resume_url' => '/app/uploads/2026/01/Screenshot-2026-01-31-at-4.36.13-PM.png',
            ],
            [
                'name' => 'Shumpei K.',
                'role' => 'Data Analyst',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$11/hr',
                'bio' => 'Experienced Data Analytics professional with a Master’s in Data Analytics and a Bachelor’s in Mathematics. Skilled in designing and implementing ETL pipelines, developing forecast models, and creating scalable data solutions. Passionate about leveraging Python, SQL, and cloud technologies to drive business insights.',
                'poster_file' => 'public/images/samples/posters/Shumpei-K.png',
                'video_url' => '/app/uploads/2026/01/Shumpei-K.-Data-Analyst-Remote-Leverage.mp4',
                'resume_url' => '/app/uploads/2026/01/Shumpei-Resume-Data-Analyst-Remote-Leverage.png',
            ],
        ];
    }

    public static function sampleApplicantAudio(): array
    {
        return [
            [
                'name' => 'Victor O.',
                'role' => 'SDR / Cold Calling',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/08/Victor-Mexico.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.40.37-PM-722x1024.png',
            ],
            [
                'name' => 'Shaun M.',
                'role' => 'SDR / Appointment Setting',
                'country' => 'Jamaica',
                'flag' => '🇯🇲',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/08/Shaun-Martin-Jamaica-.mp3',
                'resume_url' => '/app/uploads/2025/08/Screenshot-2025-08-04-at-1.13.45-PM.png',
            ],
            [
                'name' => 'Nicholas V.',
                'role' => 'SDR / BDR',
                'country' => 'Colombia',
                'flag' => '🇨🇴',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/08/Nicolas-V.-Colombia.mp3',
                'resume_url' => '/app/uploads/2025/08/Screenshot-2025-08-04-at-1.13.27-PM.png',
            ],
            [
                'name' => 'Mariana A.',
                'role' => 'SDR / Cold Calling',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Mariana-Abravanel-SDR-Brazil-Veed.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.40.37-PM-722x1024.png',
            ],
            [
                'name' => 'Chantal D.',
                'role' => 'SDR (B2B & B2C)',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Chantal-Vega-Mexico-BDR-Veed-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Chantal--722x1024.png',
            ],
            [
                'name' => 'Carolina M.',
                'role' => 'Sales Virtual Assistant / SDR',
                'country' => 'El Salvador',
                'flag' => '🌎',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Carolina-Portilo-Sales-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.02.48-PM-722x1024.png',
            ],
            [
                'name' => 'Jahvon J.',
                'role' => 'Sales & Cust. Serv. Real Estate',
                'country' => 'Jamaica',
                'flag' => '🇯🇲',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Jahvon-Johnson-Jamaica-Sales-Customer-Service.mp3',
                'resume_url' => '/app/uploads/2025/04/Jahvon-J.-Resume.png',
            ],
            [
                'name' => 'Kevin E.',
                'role' => 'Real Estate Cold Calling',
                'country' => 'US Citizen Living in Nicaragua',
                'flag' => '🌎',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Kevin-E.-Nicaragua-Cold-Calling-Real-Estate-VEED.mp3',
                'resume_url' => '#booking-footer',
            ],
            [
                'name' => 'Cristina H.',
                'role' => 'Executive Assistant',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Cristina-Hid-EA-Mexico-Veed-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.17.03-PM-722x1024.png',
            ],
            [
                'name' => 'Valerie G.',
                'role' => 'General Virtual Assistant',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Valeria-E.-Gracia-General-VA-Mexico.mp3',
                'resume_url' => '/app/uploads/2024/10/Valeria-G.-Resume-pdf-791x1024.jpg',
            ],
            [
                'name' => 'Beverly G.',
                'role' => 'Admin Virtual Assistant',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/09/Beverly-G.-Mexico-Admin-Mp3.mp3',
                'resume_url' => '/app/uploads/2024/09/Black-and-White-Corporate-Resume-5-791x1024.jpg',
            ],
            [
                'name' => 'Dana M.',
                'role' => 'Executive Assistant',
                'country' => 'Dominican Republic',
                'flag' => '🇩🇴',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Dana-Mahon-Dominican-Republic-Executive-Assistant-VEED-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.19.33-PM-722x1024.png',
            ],
            [
                'name' => 'Marcus L.',
                'role' => 'Admin VA / Property Manag.',
                'country' => 'Dominican Republic',
                'flag' => '🇩🇴',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Marcus-Admin-.mp3',
                'resume_url' => '/app/uploads/2024/09/Black-and-White-Corporate-Resume-6-791x1024.jpg',
            ],
            [
                'name' => 'Laura C.',
                'role' => 'Real Estate Ex. Assistant',
                'country' => 'Nicaragua',
                'flag' => '🇳🇮',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Laura-Celebretti-EA_Operations-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.26.38-PM-722x1024.png',
            ],
            [
                'name' => 'Joselyn M.',
                'role' => 'Property Management',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Joselyn-Maldonado-Property-Management-Mexico.mp3',
                'resume_url' => '#booking-footer',
            ],
            [
                'name' => 'Catherine A.',
                'role' => 'SDR / Appointment Setting',
                'country' => 'Philippines',
                'flag' => '🇵🇭',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/08/Catherine-VA-Admin-Philippines.mp3',
                'resume_url' => '/app/uploads/2024/09/Black-and-White-Corporate-Resume-13-791x1024.jpg',
            ],
            [
                'name' => 'Bianca V.',
                'role' => 'Customer Support',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/08/Vocaroo-1k3gmpyX6o2y.mp3',
                'resume_url' => '/app/uploads/2024/09/Black-and-White-Corporate-Resume-10-791x1024.jpg',
            ],
            [
                'name' => 'Maria V.',
                'role' => 'Customer Support',
                'country' => 'Guatemala',
                'flag' => '🇬🇹',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Maria-Villatoro-Guatamala-Customer-Service-VEED-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-3.54.22-PM-722x1024.png',
            ],
            [
                'name' => 'Pauline A.',
                'role' => 'Medical Scribe',
                'country' => 'Philippines',
                'flag' => '🇵🇭',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/11/Medical-Scribe.mp3',
                'resume_url' => '/app/uploads/2024/11/Pauline-Medical-Scribe-Resume-pdf-724x1024.jpg',
            ],
            [
                'name' => 'Martha C.',
                'role' => 'Medical Scribe / General Physician',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Martha-Centino-Medical-Scribe-General-Physician-Mexico-Veed-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.45.18-PM-722x1024.png',
            ],
            [
                'name' => 'Soffia R.',
                'role' => 'Medical Admin',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Soffia-Rojas-Medical-Veed-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.50.56-PM-722x1024.png',
            ],
            [
                'name' => 'Monica P.',
                'role' => 'Medical Doctor / Medical Operations Leader',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$12/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Monica-P-Medical-Doctor-Healthcare-Operations-Mexico.mp3',
                'resume_url' => '/app/uploads/2025/10/Screenshot-2025-10-21-at-4.59.08-PM.png',
            ],
            [
                'name' => 'Maria F.',
                'role' => 'Patient Intake / Coordinator',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Maria-Patient-Coordinator-Mexico-.mp3',
                'resume_url' => '/app/uploads/2025/10/Screenshot-2025-10-21-at-4.16.39-PM.png',
            ],
            [
                'name' => 'Andrea M.',
                'role' => 'Patient Coordinator',
                'country' => 'Chile',
                'flag' => '🌎',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Andrea-M-Patient-Coordinator-Chile.mp3',
                'resume_url' => '/app/uploads/2025/10/Screenshot-2025-10-21-at-4.52.05-PM.png',
            ],
            [
                'name' => 'Mara R.',
                'role' => 'Attorney at Law
Legal Assistant',
                'country' => 'Honduras',
                'flag' => '🇭🇳',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Mara-Rodriguez-Legal-Assistant-Attorney-at-Law-Mexico-.mp3',
                'resume_url' => '/app/uploads/2024/10/Mara-R-Resume-pdf-791x1024.jpg',
            ],
            [
                'name' => 'Pablo V.',
                'role' => 'Attorney at Law
Legal Assistant',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/06/Luis-Pablo-Vasquez-Attorney.mp3',
                'resume_url' => '/app/uploads/2025/06/Pablo-Resume.png',
            ],
            [
                'name' => 'Rocio S.',
                'role' => 'Attorney',
                'country' => 'Argentina',
                'flag' => '🇦🇷',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/06/Rocio-Soledad-Attorney.mp3',
                'resume_url' => '/app/uploads/2025/06/Rocio-S.-Resume.png',
            ],
            [
                'name' => 'Jenniffer G.',
                'role' => 'Senior Recruiter /
Human Resources',
                'country' => 'Guatemala',
                'flag' => '🇬🇹',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Jennifer-Recruiter-Guatamala-Veed.mp3',
                'resume_url' => '/app/uploads/2025/10/Jennifer-Resume-Guatamala.png',
            ],
            [
                'name' => 'Maria V.',
                'role' => 'Senior Recruiter',
                'country' => 'Nicaragua',
                'flag' => '🇳🇮',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Maria-Gabriela-Recruiter.mp3',
                'resume_url' => '/app/uploads/2024/10/Mara-R-Resume-pdf-791x1024.jpg',
            ],
            [
                'name' => 'Ana A.',
                'role' => 'Recruiter',
                'country' => 'Colombia',
                'flag' => '🇨🇴',
                'rate' => '$8/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/10/Ana-A-Recruiter.mp3',
                'resume_url' => '/app/uploads/2025/10/Ana-Resume-Colombia.png',
            ],
            [
                'name' => 'Karim G.',
                'role' => 'Data Analyst
Business Automation',
                'country' => 'Bolivia',
                'flag' => '🌎',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/06/Karim-Ghonem-Data-Analyst.mp3',
                'resume_url' => '/app/uploads/2025/06/Karim-G.-Resume.png',
            ],
            [
                'name' => 'Chukwudi E.',
                'role' => 'Data Analyst
Data Administrator',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/06/Chukwudi-Emeagi-Data-Analyst.mp3',
                'resume_url' => '/app/uploads/2025/06/Chukwudi-E-Resume.png',
            ],
            [
                'name' => 'Nassim A.',
                'role' => 'Data Analyst',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/06/Nassim-Abuxapqui-Data-Analyst.mp3',
                'resume_url' => '/app/uploads/2025/06/Nassim-A.-Resume.png',
            ],
            [
                'name' => 'Manny W.',
                'role' => 'Go High Level Automation Expert',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$13/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/12/GHL-Expert-V2.mp4',
                'resume_url' => '/app/uploads/2025/12/Manny-Resume-13.pdf',
            ],
            [
                'name' => 'Maria M.',
                'role' => 'Accountant / Bookkeeper',
                'country' => 'Dominican
Republic',
                'flag' => '🌎',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/04/Maria-Colmenares-Accountant-VEED.mp3',
                'resume_url' => '/app/uploads/2025/04/Maria-C-Resume-V2.png',
            ],
            [
                'name' => 'Jordana R.',
                'role' => 'Marketing Man. Facebook /
Google Ads',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2024/10/Jordana-R.-Marketing-Manager.mp3',
                'resume_url' => '/app/uploads/2025/04/Jordana-R.-Eesume.png',
            ],
            [
                'name' => 'Tricia O.',
                'role' => 'Project Manager',
                'country' => 'Costa Rica',
                'flag' => '🇨🇷',
                'rate' => '$11/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/08/Tricia-O.-Costa-Rica-PM.mp3',
                'resume_url' => '/app/uploads/2025/08/Screenshot-2025-08-04-at-7.12.44-PM.png',
            ],
            [
                'name' => 'Javier F.',
                'role' => 'Project Manager',
                'country' => 'Costa Rica',
                'flag' => '🇨🇷',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/08/Javier-F.-Costa-Rica-PM.mp3',
                'resume_url' => '/app/uploads/2025/08/Screenshot-2025-08-04-at-7.13.35-PM.png',
            ],
            [
                'name' => 'Paula O.',
                'role' => 'Project Manager',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2025/03/Paula-Oliveira-Project-Manager-Veed-VEED.mp3',
                'resume_url' => '/app/uploads/2025/03/Screenshot-2025-03-30-at-4.54.58-PM-722x1024.png',
            ],
            [
                'name' => 'Harry B.',
                'role' => 'Multimedia Design
(Graphics, Website, Video, etc.)',
                'country' => 'Mexico',
                'flag' => '🇲🇽',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '#',
                'resume_url' => 'https://tinigdesign.wixsite.com/tinigdesign/copy-of-harry-barotea',
            ],
            [
                'name' => 'Juan M.',
                'role' => 'Graphic Design',
                'country' => 'Argentina',
                'flag' => '🇦🇷',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '#',
                'resume_url' => 'https://www.behance.net/gallery/222979083/MY-PORTFOLIO',
            ],
            [
                'name' => 'Carlos P.',
                'role' => 'Graphic Design',
                'country' => 'Colombia',
                'flag' => '🇨🇴',
                'rate' => '$9/hr',
                'duration' => '0:45',
                'audio_url' => '#',
                'resume_url' => 'https://cuatro.myportfolio.com/work',
            ],
            [
                'name' => 'Tatiane L.',
                'role' => 'Senior Full Stack Engineer',
                'country' => 'Brazil',
                'flag' => '🇧🇷',
                'rate' => '$30/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2026/01/Tatiane-Voice-Recording.mp3',
                'resume_url' => '/app/uploads/2026/01/Tatiane-Resume.pdf',
            ],
            [
                'name' => 'Marvin A.',
                'role' => 'Software Developer',
                'country' => 'Honduras',
                'flag' => '🇭🇳',
                'rate' => '$10/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2026/01/Marvin-Voice-Recording.mp3',
                'resume_url' => '/app/uploads/2026/01/Marvin-Resume.pdf',
            ],
            [
                'name' => 'Harry T.',
                'role' => 'Full Stack Engineer',
                'country' => 'Colombia',
                'flag' => '🇨🇴',
                'rate' => '$20/hr',
                'duration' => '0:45',
                'audio_url' => '/app/uploads/2026/01/Harry-T.mp3',
                'resume_url' => '/app/uploads/2026/01/Harry-Resume.pdf',
            ],
        ];
    }

    public static function renderSampleApplicantVideos(array $overrides = []): string
    {
        $data = [
            'headline' => '',
        ];
        self::encodeRepeater('cards', 'field_sample_applicant_videos_block_cards', self::sampleApplicantVideos(), $data);

        return self::patternBlock('sample-applicant-videos', array_merge($data, $overrides), ['align' => 'full']);
    }

    public static function renderSampleApplicantAudio(array $overrides = []): string
    {
        $data = [
            'headline' => '',
        ];
        self::encodeRepeater('items', 'field_sample_applicant_audio_block_items', self::sampleApplicantAudio(), $data);

        return self::patternBlock('sample-applicant-audio', array_merge($data, $overrides), ['align' => 'full']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ------------------------------------------------------------------
    // P3 — /ecommerce-virtual-assistant/
    //
    // That page composes 17 sections out of blocks whose render* helpers took
    // only a flat $overrides array, so there was no way to hand them repeater
    // rows. Repeater data has to be ACF-encoded — a raw array passed as an
    // override is silently ignored and the block falls back to its presets,
    // which is how a page ships looking wired up but showing the wrong content.
    // This one helper encodes any repeater for any block rather than widening a
    // dozen signatures.
    // ------------------------------------------------------------------

    /**
     * Render a block, ACF-encoding one repeater into its attributes.
     *
     * @param  string  $slug  block slug without the `acf/` prefix
     * @param  string  $fieldName  the repeater's field name (e.g. `cards`)
     * @param  string  $fieldKey  the repeater's ACF key (e.g. `field_image_card_grid_block_cards`)
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $overrides  plain (non-repeater) fields
     * @param  array<string, mixed>  $attrs  block attributes such as `align`
     */
    public static function renderBlockWithRepeater(
        string $slug,
        string $fieldName,
        string $fieldKey,
        array $rows,
        array $overrides = [],
        array $attrs = [],
    ): string {
        $data = [];
        self::encodeRepeater($fieldName, $fieldKey, $rows, $data);

        return self::patternBlock($slug, array_merge($data, $overrides), $attrs);
    }

    /** Repeater field keys for the blocks the ecommerce page composes. */
    public const REPEATER_KEYS = [
        'feature-cards' => ['cards', 'field_feature_cards_block_cards'],
        'image-card-grid' => ['cards', 'field_image_card_grid_block_cards'],
        'results-preview' => ['cards', 'field_results_preview_block_cards'],
        'roles-pricing-grid' => ['cards', 'field_roles_pricing_grid_block_cards'],
        'sample-applicant-videos' => ['cards', 'field_sample_applicant_videos_block_cards'],
        'client-logos-marquee' => ['logos', 'field_client_logos_marquee_block_logos'],
        'talent-carousel' => ['profiles', 'field_talent_carousel_block_profiles'],
        'talent-marquee' => ['talent_cards', 'field_talent_marquee_block_talent_cards'],
        'partner-hero' => ['badges', 'field_partner_hero_block_badges'],
        'talent-dossier-carousel' => ['cards', 'field_talent_dossier_carousel_block_cards'],
        'stats-band' => ['stats', 'field_stats_band_block_stats'],
        'featured-posts' => ['cards', 'field_featured_posts_block_cards'],
    ];

    /**
     * Convenience wrapper around renderBlockWithRepeater() for the blocks listed
     * in REPEATER_KEYS, so a pattern names the block rather than its field key.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $attrs
     */
    public static function renderEcom(string $slug, array $rows, array $overrides = [], array $attrs = []): string
    {
        if (! isset(self::REPEATER_KEYS[$slug])) {
            throw new \InvalidArgumentException("No repeater key registered for block '{$slug}'.");
        }

        [$fieldName, $fieldKey] = self::REPEATER_KEYS[$slug];

        return self::renderBlockWithRepeater($slug, $fieldName, $fieldKey, $rows, $overrides, $attrs);
    }

    /** Page art for /ecommerce-virtual-assistant/, e.g. ecomImg('talent/Andres-M.jpg'). */
    public static function ecomImg(string $file): string
    {
        return self::pageImg('ecommerce-virtual-assistant', ltrim($file, '/'));
    }

    // P2 — FUNNEL / OPERATIONAL PAGES
    // Copy transcribed from production 2026-09-15. See PAGE-MIGRATION-STATUS.md §3 P2.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * /referral-program/ (production page 30560) hero copy.
     *
     * `stats` renders the two earnings figures production states in prose; `steps` feeds
     * acf/process-steps, which production keeps inline in the hero instead.
     *
     * @return array{subheadline: string, stats: array<int, array{value: string, label: string}>, steps: array<int, array{num: string, title: string, desc: string}>}
     */
    public static function referralProgramHero(): array
    {
        return [
            'subheadline' => 'Earn $1,000 when someone you refer hires through Remote Leverage — and they get $500 off their first hire.',
            'stats' => [
                ['value' => '$1,000', 'label' => 'You earn for every referral that hires'],
                ['value' => '$500', 'label' => 'They save on their first hire'],
            ],
            'steps' => [
                [
                    'num' => '01',
                    'title' => 'Sign up for the<br>Referral Program',
                    'desc' => 'Join in under a minute. No cost, no commitment, and no cap on how much you can earn.',
                ],
                [
                    'num' => '02',
                    'title' => 'Get your unique<br>referral link',
                    'desc' => 'We generate a link tied to your account so every business you send is credited to you.',
                ],
                [
                    'num' => '03',
                    'title' => 'Share it with<br>your network',
                    'desc' => 'Pass it to founders and operators who are hiring. They get $500 off their first hire.',
                ],
                [
                    'num' => '04',
                    'title' => 'Earn $1,000 when<br>they hire',
                    'desc' => 'Your commission qualifies as soon as your referral hires with us. Paid straight to you.',
                ],
            ],
        ];
    }

    public static function renderReferralProgramHero(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('stats', 'field_referral_program_hero_block_stats', self::referralProgramHero()['stats'], $data);

        return self::patternBlock(
            'referral-program-hero',
            array_merge($data, self::withFieldKeys('referral_program_hero_block', $overrides)),
            ['align' => 'full'],
        );
    }

    /** Render acf/process-steps with the /referral-program/ how-it-works steps. */
    public static function renderReferralProgramSteps(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('steps', 'field_process_steps_block_steps', self::referralProgramHero()['steps'], $data);

        return self::patternBlock('process-steps', array_merge($data, $overrides));
    }

    /**
     * The four alternating image/copy rows below the /referral-program/ hero.
     * Keys match the acf/media-copy fields.
     *
     * @return array<int, array{headline: string, body: string, image: string, image_position: string}>
     */
    public static function referralProgramRows(): array
    {
        $img = fn (string $file): string => self::pageImg('referral-program', $file);

        $rows = [
            [
                'We’re the Fastest Growing Talent Startup in 2026',
                '<p>In January alone, over 400 businesses signed up to hire through Remote Leverage—adding to a streak of rapid growth over the past several months.</p><p>As more founders turn to offshore talent to scale efficiently, we’re expanding fast and preparing to launch powerful new products and services throughout 2026 to support them even better.</p>',
                'Section-3-Images-1.png',
            ],
            [
                'Ethical &amp; Compliant Global Outsourcing',
                '<p>The roles we fill are among the most attractive opportunities available to global professionals.</p><p>Candidates work directly with U.S. employers, earn their full compensation with no commissions or middle-man cuts, and join teams where their impact is real and long-term.</p>',
                'Section-3-Images-2.png',
            ],
            [
                'Access to Unique Global Talent Markets',
                '<p>Remote Leverage gives U.S. teams access to elite talent from Latin America and other high-quality markets—working in your time zone, with strong English fluency and cultural alignment.</p><p>Our sourcing network and vetting process surface candidates most companies never reach, so you can hire faster and collaborate seamlessly from day one.</p>',
                'Section-3-Images-3.png',
            ],
            [
                'The Happiest Clients in Our Industry',
                '<p>Our clients don’t just say they’re happy, <a href="/reviews/" class="underline">they show it</a>.</p><p>With nearly a hundred video testimonials from real founders and operators, Remote Leverage has earned a reputation for delivering elite talent, fast hiring, and a risk-free experience that keeps customers coming back.</p>',
                'Section-3-Images-4.png',
            ],
        ];

        return array_values(array_map(fn (array $row, int $i): array => [
            'headline' => $row[0],
            'body' => $row[1],
            'image' => $img($row[2]),
            // Production alternates the art: rows 1 and 3 right, rows 2 and 4 left.
            'image_position' => $i % 2 === 0 ? 'right' : 'left',
        ], $rows, array_keys($rows)));
    }

    /**
     * Production /signedup/ (page 10848) confirmation panel copy.
     *
     * @return array{headline: string, intro_label: string, footnote: string, steps: array<int, array{label: string, text: string}>}
     */
    public static function signedUpPanel(): array
    {
        return [
            'headline' => 'Agreement Completed.',
            'intro_label' => 'Next steps:',
            'footnote' => 'We’ve hired hundreds of Virtual Assistants for various businesses all across the US.',
            'steps' => [
                [
                    'label' => 'Onboarding Meeting',
                    'text' => 'A Hiring Manager will contact you soon to book an onboarding meeting to fully understand your ideal candidate requirements.',
                ],
                [
                    'label' => 'Virtual Assistant Vetting',
                    'text' => 'After the onboarding meeting, we will vet 4-6 qualified applicants that match your criteria. This process typically takes 1-2 weeks as we have to go through hundreds of applicants and conduct multiple interviews with each applicant prior to matching them to the job you’re hiring for. In some cases, we can process it faster if we have applicants in our database that match your criteria.',
                ],
                [
                    'label' => 'Virtual Assistant Interviews',
                    'text' => 'Once we have 4-6 qualified applicants that match your criteria, you’ll be invited to interview them with the hiring manager. During this interview, you can ask any questions you want to find the best fit. You can also request to do a 2nd round of interviews with your top applicants, and even get another batch if you want to interview more people. Do note that any applicants you meet may be hired by another company at any time during the hiring process, as all applicants are actively seeking jobs.',
                ],
                [
                    'label' => 'Job Offer',
                    'text' => 'Once you meet your ideal candidate, we’ll help you craft a job offer to bring them onboard. We can also help with negotiating the hourly rate if needed.',
                ],
                [
                    'label' => 'Applicant <> Client Onboarding',
                    'text' => 'We will then set up another meeting to help you with onboarding the applicant.',
                ],
                [
                    'label' => 'Replacement Guarantee',
                    'text' => 'The Hiring Manager will support you for 12 months, handling any questions or replacements. If you have any questions along the way and need fast responses, feel free to email Admin@RemoteLeverage.com or call 408-403-5574',
                ],
            ],
        ];
    }

    public static function renderNextStepsPanel(array $overrides = []): string
    {
        $defaults = self::signedUpPanel();
        $data = self::withFieldKeys('next_steps_panel_block', [
            'badge_image' => self::pageImg('signedup', 'Satisfaction-badge.png'),
        ]);
        self::encodeRepeater('steps', 'field_next_steps_panel_block_steps', $defaults['steps'], $data);

        return self::patternBlock(
            'next-steps-panel',
            array_merge($data, self::withFieldKeys('next_steps_panel_block', $overrides)),
            ['align' => 'full'],
        );
    }

    public static function renderPaymentSuccessBanner(array $overrides = []): string
    {
        return self::patternBlock(
            'payment-success-banner',
            self::withFieldKeys('payment_success_banner_block', $overrides),
            ['align' => 'full'],
        );
    }

    public static function renderJotformEmbed(string $formId, array $overrides = []): string
    {
        return self::patternBlock(
            'jotform-embed',
            self::withFieldKeys('jotform_embed_block', array_merge(['form_id' => $formId], $overrides)),
            ['align' => 'full'],
        );
    }

    /**
     * The /signedup/ "Client Reviews" wall — production's sixteen videos, in production's
     * DOM order (page 10848, read 2026-09-15).
     *
     * Fifteen of the sixteen are already curated in the 77-entry vaThankYouTestimonials()
     * archive, so this selects them by Vimeo ID rather than duplicating their posters and
     * quotes. The sixteenth (1067577717, "Vercasa Review") is not in the archive; its poster
     * is the video's own frame, pulled from Vimeo's oEmbed endpoint.
     *
     * @return array<int, array<string, string>>
     */
    public static function signedUpTestimonials(): array
    {
        // Production's order, not the archive's.
        $order = [
            '1067577208', '1067577369', '1067577489', '1067577248',
            '1067577464', '1067577620', '1067577549', '1067577665',
            '1067577688', '1067577383', '1067577228', '1067577598',
            '1067577293', '1067577442', '1067577645', '1067577717',
        ];

        $byId = [];
        foreach (self::vaThankYouTestimonials() as $row) {
            if (preg_match('#/(\d+)$#', (string) ($row['video_url'] ?? ''), $m) === 1) {
                $byId[$m[1]] = $row;
            }
        }

        $byId['1067577717'] ??= [
            'video_url' => 'https://vimeo.com/1067577717',
            'image' => self::pageImg('signedup', 'Vercasa-Review.jpg'),
            'duration' => '00:27',
            'quote' => '“Vercasa Review”',
            'company' => 'Vercasa',
        ];

        return array_values(array_filter(array_map(
            fn (string $id): ?array => $byId[$id] ?? null,
            $order,
        )));
    }

    public static function renderSignedUpTestimonials(array $overrides = []): string
    {
        $data = self::withFieldKeys('testimonials_block', array_merge([
            // Production lays this wall out two across as bare 16:9 tiles with no
            // quote or company chrome, not the block's default three-across cards.
            'columns' => '2',
            'layout' => 'plain',
        ], $overrides));
        self::encodeRepeater('testimonials', 'field_testimonials_block_testimonials', self::signedUpTestimonials(), $data);

        return self::patternBlock('testimonials', $data);
    }

    public static function renderPaymentGateway(array $overrides = []): string
    {
        return self::patternBlock(
            'payment-gateway',
            self::withFieldKeys('payment_gateway_block', $overrides),
            ['align' => 'full'],
        );
    }

    /**
     * /services/ — the whole page is one `acf/offer-stack`, because production paints a single
     * gradient behind every card and a block per card would seam at each join.
     *
     * Each row takes the block's card sub-fields; `pills` is a nested repeater of `['text' => …]`
     * rows and is encoded as one by encodeRepeater().
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>  $overrides
     */
    public static function renderOfferStack(array $cards, array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_offer_stack_block_cards', $cards, $data);

        return self::patternBlock('offer-stack', array_merge($data, $overrides), ['align' => 'full']);
    }

    /**
     * Rows of gradient video cards (`acf/video-card-grid`).
     *
     * Each card is `['video_url' => …, 'title' => …, 'width' => 'full'|'half', …]`. `video_url`
     * must be the complete player URL: an unlisted Vimeo video is addressed by its ID *and*
     * its `h=` privacy hash, and a player missing the hash renders a restriction notice rather
     * than failing loudly. Transcribe production's `src`; do not rebuild it from the ID.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>  $overrides
     */
    public static function renderVideoCardGrid(array $cards, array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_video_card_grid_block_cards', $cards, $data);

        return self::patternBlock('video-card-grid', array_merge($data, $overrides), ['align' => 'full']);
    }
}
