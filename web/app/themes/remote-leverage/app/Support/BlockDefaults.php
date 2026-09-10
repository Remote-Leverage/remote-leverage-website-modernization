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
    }

    /**
     * Rewrite <img> tags in rendered post content (e.g. block/pattern content that was
     * saved with a .png/.jpg src before a .webp sibling existed) to prefer WebP.
     */
    public static function filterContentImgTag(string $filteredImage): string
    {
        if (! preg_match('/\ssrc=(["\'])(.*?)\1/i', $filteredImage, $m)) {
            return $filteredImage;
        }

        $originalSrc = html_entity_decode($m[2]);
        $newSrc = self::preferWebp($originalSrc);

        if ($newSrc === $originalSrc) {
            return $filteredImage;
        }

        return str_replace($m[0], ' src='.$m[1].esc_attr($newSrc).$m[1], $filteredImage);
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

        $webpPath = preg_replace('/\.'.preg_quote($ext, '/').'$/i', '.webp', $path);
        $webpUrl = preg_replace('/\.'.preg_quote($ext, '/').'$/i', '.webp', $url);

        if (is_file($webpPath)) {
            return $webpUrl;
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

        $saved = $editor->save($destPath, 'image/webp');

        return ! is_wp_error($saved) && is_file($destPath);
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
        if (is_file($themePath)) {
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
                if (is_file($path)) {
                    return esc_url(set_url_scheme(rtrim($url, '/').'/'.$name.'.'.$ext, 'https'));
                }
            }
        }

        return esc_url(set_url_scheme(self::imgBase().'/'.$file, 'https'));
    }

    /**
     * Resolve a hire-va-4 image to its canonical HTTPS URL.
     */
    public static function hireVaImg(string $file): string
    {
        return self::homeImg(ltrim($file, '/'));
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

    public static function encodeRepeater(string $fieldName, string $fieldKey, array $rows, array &$data = []): array
    {
        $data[$fieldName] = count($rows);
        $data['_'.$fieldName] = $fieldKey;
        foreach ($rows as $i => $row) {
            foreach ($row as $subfield => $val) {
                $encodedVal = self::getAttachmentId($val);
                $data["{$fieldName}_{$i}_{$subfield}"] = $encodedVal;
                $data["_{$fieldName}_{$i}_{$subfield}"] = "{$fieldKey}_{$subfield}";
            }
        }

        return $data;
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

    public static function renderDepartmentCards(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_department_cards_block_cards', self::departmentCards(), $data);

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

    public static function renderFeatureCards(string $columns = '3', array $overrides = []): string
    {
        $data = [
            'columns' => $columns,
            '_columns' => 'field_feature_cards_block_columns',
        ];
        self::encodeRepeater('cards', 'field_feature_cards_block_cards', self::featureCards($columns), $data);

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

    public static function renderDataTable(array $overrides = []): string
    {
        $data = [
            'col_1_header' => 'DIY',
            '_col_1_header' => 'field_data_table_block_col_1_header',
            'col_2_header' => 'Remote Leverage',
            '_col_2_header' => 'field_data_table_block_col_2_header',
        ];
        self::encodeRepeater('rows', 'field_data_table_block_rows', self::dataTableRows(), $data);

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
                'img' => self::homeImg('Frame-1092.png'),
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

    public static function renderRolesGrid(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('cards', 'field_roles_grid_block_cards', self::rolesGridCards(), $data);

        return self::patternBlock('roles-grid', array_merge($data, $overrides));
    }

    // --- HIRE-VA-4: PROCESS STEPS ---
    public static function hireVa4ProcessSteps(): array
    {
        return [
            [
                'num' => '01',
                'title' => 'Tell us your<br>ideal hire',
                'desc' => 'Book a 15-minute consultation. Describe the role, skills, and experience you need. Remote Leverage handles posting, screening, and interviewing candidates on your behalf.',
            ],
            [
                'num' => '02',
                'title' => 'Meet your<br>top 1% shortlist',
                'desc' => 'Within 48–72 hours, receive 4–6 pre-vetted, fluent English-speaking candidates. You interview, you choose. No contracts, no commitments — you only pay if you hire.',
            ],
            [
                'num' => '03',
                'title' => 'We handle pay<br>& compliance',
                'desc' => 'You hire your favorite, and they are immediately integrated into your Lano payroll and compliance dashboard. No misclassification risk. No surprises.',
            ],
        ];
    }

    public static function renderHireVa4ProcessSteps(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('steps', 'field_process_steps_block_steps', self::hireVa4ProcessSteps(), $data);

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

    public static function renderHireVa4Testimonials(array $overrides = []): string
    {
        $data = [];
        self::encodeRepeater('testimonials', 'field_testimonials_block_testimonials', self::hireVa4Testimonials(), $data);

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

    public static function renderHireVa4Faq(array $overrides = []): string
    {
        $data = [];
        $faqs = self::hireVa4Faqs();
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
}
