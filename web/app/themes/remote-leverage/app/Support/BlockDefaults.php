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
     */
    public static function themeImg(string $path): string
    {
        return esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/' . ltrim($path, '/'), 'https'));
    }

    /**
     * Resolve a hire-va-4 image to its canonical HTTPS URL.
     */
    public static function hireVaImg(string $file): string
    {
        return self::themeImg('hire-va-4/' . ltrim($file, '/'));
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
                $text
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
        $data['_' . $fieldName] = $fieldKey;
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

        return '<!-- wp:acf/' . $slug . ' ' . json_encode($blockAttrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
    }

    public static function imgBase(): string
    {
        return get_template_directory_uri() . '/public/images/home';
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
                'img' => $img . '/magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp',
                'title' => 'Administrative &<br>Executive Assistants',
                'desc' => 'Executive support for busy founders and teams.',
            ],
            [
                'img' => $img . '/magnific_wPmw8Jk7EI-1.webp',
                'title' => 'Healthcare &<br>Medical Assistants',
                'desc' => 'Healthcare professionals supporting clinics and practices.',
            ],
            [
                'img' => $img . '/magnific_ubzu0aUQLD-1.webp',
                'title' => 'Sales & Growth<br>Marketing Talents',
                'desc' => 'Professionals focused on growth, leads, and revenue.',
            ],
            [
                'img' => $img . '/magnific_YVjYLdkWeC-1.webp',
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
                    'img' => $img . '/hour.webp',
                    'title' => '$6-10 /hr',
                    'desc' => 'Access experienced professionals at highly competitive rates. Most administrative, support, sales, and marketing roles can be filled within this range.',
                ],
                [
                    'img' => $img . '/lower-cost.webp',
                    'title' => '70% Lower Costs',
                    'desc' => 'Reduce hiring costs without sacrificing quality. Reinvest the savings into growth, marketing, product development, or additional hires.',
                ],
                [
                    'img' => $img . '/day-average.webp',
                    'title' => '4-Day Average',
                    'desc' => 'From opening a role to reviewing qualified candidates in days, not weeks. Our recruiting process is designed for speed without compromising quality.',
                ],
                [
                    'img' => $img . '/quality.webp',
                    'title' => 'Vetted for Quality',
                    'desc' => 'Every candidate is screened for English proficiency, experience, communication skills, and role-specific expertise before reaching your inbox.',
                ],
            ];
        }

        return [
            [
                'img' => $img . '/Latin-american.webp',
                'title' => 'Top-tier talents from Latin America and EU',
                'desc' => 'Access exceptional global talent. We identify skilled professionals with the communication, expertise, and reliability needed to make an immediate impact.',
            ],
            [
                'img' => $img . '/no-contracts.webp',
                'title' => 'No contracts<br>No obligations',
                'desc' => 'Evaluate talent, interview candidates, and see our process firsthand before making any commitment. The decision is always yours.',
            ],
            [
                'img' => $img . '/ongoing-middleman.webp',
                'title' => 'No ongoing<br>middleman fees',
                'desc' => 'You hire talent directly into your business. No payroll markups, monthly management fees, or recurring commissions.',
            ],
            [
                'img' => $img . '/payment.webp',
                'title' => "No payment if we don't find the right talent",
                'desc' => 'Our incentives are aligned with yours. We only succeed when you make a successful hire, so we focus relentlessly on finding the right fit.',
            ],
            [
                'img' => $img . '/payment-compliance.webp',
                'title' => 'Payments, compliance,<br>onboarding support',
                'desc' => 'Our Contractor Management solution simplifies onboarding, contracts, payroll, and compliance for international talent.',
            ],
            [
                'img' => $img . '/one-dashboard.webp',
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
                'image' => $img . '/PRES-Property-Management.jpg',
                'duration' => '00:38',
                'quote' => '“I can\'t say enought about how every step of the way it just wowed me.”',
                'company' => 'PRES Property Management',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => $img . '/Coldwell-Banker.jpg',
                'duration' => '00:19',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'company' => 'Coldwell Banker',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => $img . '/Carbon-Solutions-Group.jpg',
                'duration' => '00:55',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'company' => 'Carbon Solutions Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => $img . '/The-Zen-Zone-Wellness.jpg',
                'duration' => '04:06',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'company' => 'The Zen Zone Wellness',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => $img . '/Color-Job.jpg',
                'duration' => '01:56',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'company' => 'Color Job',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => $img . '/Connect-Church-Colorado.jpg',
                'duration' => '02:45',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'company' => 'Connect Church Colorado',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => $img . '/Cash-is-King.jpg',
                'duration' => '02:39',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'company' => 'Cash is King',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => $img . '/Liberty-Hill.jpg',
                'duration' => '02:31',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                'company' => 'Liberty Hill',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => $img . '/RE-MAX.jpg',
                'duration' => '01:02',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it\'s been a game changer for me.”',
                'company' => 'RE / MAX',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => $img . '/Realty-One-Group.jpg',
                'duration' => '01:43',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'company' => 'Realty One Group',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => $img . '/OneUp-Sportz-01.jpg',
                'duration' => '01:06',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577598',
                'image' => $img . '/OneUp-Sportz.jpg',
                'duration' => '01:40',
                'quote' => '“It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome.”',
                'company' => 'OneUp Sportz',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => $img . '/Greener-Hill-Psychiatric.jpg',
                'duration' => '05:59',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'company' => 'Greener Hill Psychiatric',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => $img . '/Diamond-Detox.jpg',
                'duration' => '00:43',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'company' => 'Diamond Detox',
            ],
            [
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => $img . '/Ad-Center-360.jpg',
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
            ['src' => $img . '/brrrr-1.webp', 'alt' => 'BRRRR'],
            ['src' => $img . '/carbon-1.webp', 'alt' => 'Carbon Solutions'],
            ['src' => $img . '/Q-BitNewLogo-Photoroom-1.webp', 'alt' => 'Q-Bit'],
            ['src' => $img . '/boe-1.webp', 'alt' => 'BOE'],
            ['src' => $img . '/greener-hill-1.webp', 'alt' => 'Greener Hill'],
            ['src' => $img . '/Prestige-Landscaping-1.webp', 'alt' => 'Prestige Landscaping'],
            ['src' => $img . '/garuz-1-1.webp', 'alt' => 'Garuz'],
            ['src' => $img . '/vercasa-1.webp', 'alt' => 'Vercasa'],
            ['src' => $img . '/adcenter-2.webp', 'alt' => 'Ad Center 360'],
            ['src' => $img . '/liberty-hill-1.webp', 'alt' => 'Liberty Hill'],
            ['src' => $img . '/chick-fil-a-logo-1.webp', 'alt' => 'Chick-fil-A'],
            ['src' => $img . '/rl-adp.webp', 'alt' => 'ADP'],
            ['src' => $img . '/rl-mainstreet.webp', 'alt' => 'Mainstreet'],
            ['src' => $img . '/rl-farmers.webp', 'alt' => 'Farmers Insurance'],
            ['src' => $img . '/rl-college-hunks.webp', 'alt' => 'College Hunks'],
            ['src' => $img . '/remax-1.webp', 'alt' => 'RE/MAX'],
            ['src' => $img . '/coldwell-1.webp', 'alt' => 'Coldwell Banker'],
            ['src' => $img . '/sivia-law-white-306w-1.webp', 'alt' => 'Sivia Law'],
            ['src' => $img . '/zone-4-1.webp', 'alt' => 'Zone 4'],
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
                'logo' => $img . '/rappi_logo-Small.webp',
                'bg' => $img . '/Frame-132-1.webp',
            ],
            [
                'name' => 'Lucas Mendes',
                'title' => 'Marketing Manager',
                'desc' => 'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization with proven track record scaling pipeline.',
                'logo' => $img . '/clickup.webp',
                'bg' => $img . '/Frame-133-1.webp',
            ],
            [
                'name' => 'Noah Martinez',
                'title' => 'Sales Representative',
                'desc' => 'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies.',
                'logo' => $img . '/image-2.webp',
                'bg' => $img . '/Frame-135-1.webp',
            ],
            [
                'name' => 'Diego Navarro',
                'title' => 'Sales Representative',
                'desc' => 'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management to convert qualified leads.',
                'logo' => $img . '/image-11.webp',
                'bg' => $img . '/Frame-135-2.webp',
            ],
            [
                'name' => 'André Vilalobos',
                'title' => 'Graphic Designer',
                'desc' => '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.',
                'logo' => $img . '/State-Farm-01.webp',
                'bg' => $img . '/con-07.webp',
            ],
            [
                'name' => 'Juliana Silva',
                'title' => 'Lead Generation (SDR)',
                'desc' => '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.',
                'logo' => $img . '/mercado.webp',
                'bg' => $img . '/cont-02.webp',
            ],
            [
                'name' => 'Valeria Andrea',
                'title' => 'Medical Assistant',
                'desc' => '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.',
                'logo' => $img . '/Allstate-01.webp',
                'bg' => $img . '/con-05.webp',
            ],
            [
                'name' => 'Laura Valentina',
                'title' => 'Customer Support',
                'desc' => '+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles.',
                'logo' => $img . '/image-12-1.webp',
                'bg' => $img . '/con-08.webp',
            ],
            [
                'name' => 'Sofía Pérez',
                'title' => 'Marketing Assistant',
                'desc' => '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness.',
                'logo' => $img . '/Frame-74-1.webp',
                'bg' => $img . '/cont-03.webp',
            ],
            [
                'name' => 'Luana Dias',
                'title' => 'Executive Assistant',
                'desc' => '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive\'s time like it\'s my own.',
                'logo' => $img . '/NU-bank-01.webp',
                'bg' => $img . '/con-06.webp',
            ],
            [
                'name' => 'Sarah Martinez',
                'title' => 'Sr Executive Assistant',
                'desc' => 'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.',
                'logo' => $img . '/1655873088shopify-logo-transparent.webp',
                'bg' => $img . '/Frame-131-1.webp',
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
        return get_template_directory_uri() . '/public/images/hire-va-4';
    }

    // --- HIRE-VA-4: ROLES GRID ---
    public static function rolesGridCards(): array
    {
        $img = self::imgBaseHireVa4();
        return [
            [
                'title' => 'Administrative',
                'desc' => 'Inbox, calendar, invoices, data entry. The daily upkeep taken off your plate.',
                'img' => $img . '/Woman_looking_camera_smiling_2K_202607171433-1.png',
            ],
            [
                'title' => 'Lead Generation',
                'desc' => 'Outreach calls, emails, texting, and follow-up that keeps your pipeline full.',
                'img' => $img . '/Frame-1092.png',
            ],
            [
                'title' => 'Sales (SDR)',
                'desc' => 'Qualifies leads, runs demos, and follows through until it\'s a closed deal.',
                'img' => $img . '/Screenshot-2026-07-17-at-2.03.18-p.m.-1.png',
            ],
            [
                'title' => 'Social Media',
                'desc' => 'Posts, replies, and community management that keeps your brand active.',
                'img' => $img . '/Screenshot-2026-07-17-at-2.02.42-p.m.-1.png',
            ],
            [
                'title' => 'Marketing',
                'desc' => 'Runs and optimizes your paid campaigns across Meta, Google, and LinkedIn.',
                'img' => $img . '/Frame-1092-1.png',
            ],
            [
                'title' => 'Graphic Design',
                'desc' => 'Social creative, decks, and brand assets that look like an in-house hire made them.',
                'img' => $img . '/Screenshot-2026-07-17-at-2.01.07-p.m.-1.png',
            ],
            [
                'title' => 'Customer Support',
                'desc' => 'Tickets, questions, and vendor calls handled so your customers stay happy.',
                'img' => $img . '/man-dressed-casual-wearing-glasses-studio-shot-copy-space-2.png',
            ],
            [
                'title' => 'Custom Role',
                'desc' => 'Something specific in mind? Tell us the role — we\'ve likely filled it before.',
                'img' => $img . '/Frame-216.png',
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
                'image' => $img . '/PRES-Property-Management.jpg',
                'duration' => '00:38',
            ],
            [
                'company' => 'Coldwell Banker',
                'quote' => '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                'video_url' => 'https://vimeo.com/1067577369',
                'image' => $img . '/Coldwell-Banker.jpg',
                'duration' => '00:19',
            ],
            [
                'company' => 'Carbon Solutions Group',
                'quote' => '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                'video_url' => 'https://vimeo.com/1067577665',
                'image' => $img . '/Carbon-Solutions-Group.jpg',
                'duration' => '00:55',
            ],
            [
                'company' => 'The Zen Zone Wellness',
                'quote' => '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                'video_url' => 'https://vimeo.com/1067577549',
                'image' => $img . '/The-Zen-Zone-Wellness.jpg',
                'duration' => '04:06',
            ],
            [
                'company' => 'Color Job',
                'quote' => '“The transition of working with you guys was absolutely smooth and amazing.”',
                'video_url' => 'https://vimeo.com/1067577688',
                'image' => $img . '/Color-Job.jpg',
                'duration' => '01:56',
            ],
            [
                'company' => 'Connect Church Colorado',
                'quote' => '“She was just perfect, everything that we were looking for we found it in her.”',
                'video_url' => 'https://vimeo.com/1067577383',
                'image' => $img . '/Connect-Church-Colorado.jpg',
                'duration' => '02:45',
            ],
            [
                'company' => 'Cash is King',
                'quote' => '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                'video_url' => 'https://vimeo.com/1067577489',
                'image' => $img . '/Cash-is-King.jpg',
                'duration' => '02:39',
            ],
            [
                'company' => 'Liberty Hill',
                'quote' => '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates”',
                'video_url' => 'https://vimeo.com/1067577248',
                'image' => $img . '/Liberty-Hill.jpg',
                'duration' => '02:31',
            ],
            [
                'company' => 'RE / MAX',
                'quote' => '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it’s been a game changer for me.”',
                'video_url' => 'https://vimeo.com/1067577464',
                'image' => $img . '/RE-MAX.jpg',
                'duration' => '01:02',
            ],
            [
                'company' => 'Realty One Group',
                'quote' => '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                'video_url' => 'https://vimeo.com/1067577620',
                'image' => $img . '/Realty-One-Group.jpg',
                'duration' => '01:43',
            ],
            [
                'company' => 'OneUp Sportz',
                'quote' => '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                'video_url' => 'https://vimeo.com/1067577228',
                'image' => $img . '/OneUp-Sportz-01.jpg',
                'duration' => '01:06',
            ],
            [
                'company' => 'Greener Hill Psychiatric',
                'quote' => '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                'video_url' => 'https://vimeo.com/1067577293',
                'image' => $img . '/Greener-Hill-Psychiatric.jpg',
                'duration' => '05:59',
            ],
            [
                'company' => 'Diamond Detox',
                'quote' => '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                'video_url' => 'https://vimeo.com/1067577442',
                'image' => $img . '/Diamond-Detox.jpg',
                'duration' => '00:43',
            ],
            [
                'company' => 'Ad Center 360',
                'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                'video_url' => 'https://vimeo.com/1067577645',
                'image' => $img . '/Ad-Center-360.jpg',
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
}
