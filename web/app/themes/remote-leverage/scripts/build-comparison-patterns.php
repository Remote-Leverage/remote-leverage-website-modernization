<?php

/**
 * Generates the per-page comparison patterns from the mother pattern.
 *
 * The mother (patterns/comparison-full.php) holds every section any comparison
 * page needs, each tagged with metadata.name. Each page here prescinds from the
 * sections it doesn't use and overrides the competitor-specific data.
 *
 * Run with: wp eval-file scripts/build-comparison-patterns.php
 */
$motherPath = get_theme_file_path('patterns/comparison-full.php');
$src = file_get_contents($motherPath);
$mother = parse_blocks(substr($src, strpos($src, '<!-- wp:')));
$mother = array_values(array_filter($mother, fn ($b) => ($b['blockName'] ?? null) !== null));

$pages = [
    'comparison-wing-full' => [
        'title' => 'Full Page - Comparison (Wing Assistant)',
        'desc' => 'Wing Assistant comparison page.',
        'page' => 407,
        'exclude' => ['hiring-easy', 'full-picture'],
        'competitor' => null,
    ],
    'comparison-wing-ads-full' => [
        'title' => 'Full Page - Comparison (Wing Assistant Ads)',
        'desc' => 'Ads variant: condensed tables, no competitor explainer or competitor roles carousel.',
        'page' => 408,
        'exclude' => ['hiring-easy', 'full-picture', 'how-competitor-works', 'competitor-roles'],
        'competitor' => null,
        // The ads variant runs condensed tables; recovered from production.
        'tables' => ['at-a-glance' => 0, 'comparing-costs' => 1, 'money-goes' => 2,
            'screening' => 3, 'replacement-policies' => 4, 'full-comparison' => 5],
        'tableSource' => 'comparison-wing-ads-tables.json',
        'overrides' => [
            // The ads page puts two specialist roles in the talent carousel where the
            // main Wing page shows three admin assistants.
            'talent-carousel' => [
                'profiles_1_role' => 'Sales Rep',
                'profiles_2_role' => 'Marketing Specialist',
            ],
            // Its best-for cards argue the hands-on/hands-off split rather than the
            // seniority-and-culture one the main page runs.
            'best-for' => [
                'card_1_body' => '<ul><li><strong>Hands-On Founders:</strong> Want to personally interview and select from 4-6 vetted candidates, not get assigned whoever’s available.</li>'
                    .'<li><strong>Cost-Conscious Operators:</strong> Want 100% of the agreed wage reaching their VA, with no bundled agency markup.</li>'
                    .'<li><strong>Long-Term Builders:</strong> Want to hire and manage their VA directly, with no non-solicitation clause locking them out later.</li></ul>',
                'card_2_body' => '<ul><li><strong>Hands-Off Delegators:</strong> Want basic task support without interviewing, hiring, or managing anyone themselves.</li>'
                    .'<li><strong>Short-Term Testers:</strong> Want to trial VA support without committing to a long-term hire or direct relationship.</li></ul>',
            ],
        ],
    ],
    'comparison-athena-full' => [
        'title' => 'Full Page - Comparison (Athena)',
        'desc' => 'Athena comparison page.',
        'page' => 382,
        'exclude' => ['built-for-control', 'screening', 'replacement-policies', 'best-for', 'full-comparison'],
        'competitor' => ['from' => 'Wing Assistant', 'to' => 'Athena', 'short' => ['Wing' => 'Athena']],
        // Athena's own table data, keyed by section. Captured from the page before
        // it was converted; see scripts/comparison-athena-tables.json.
        'tables' => ['at-a-glance' => 0, 'comparing-costs' => 1, 'money-goes' => 2],
        'tableSource' => 'comparison-athena-tables.json',
        // Athena runs the guarantee before the talent sections and states the
        // placement-fee pair twice — once under "hiring easy", once after the steps.
        'insertAfter' => [
            'hiring-easy' => [[
                'from' => 'assurance-pair',
                'overrides' => [
                    'item_1_text' => 'A $100 deposit is required to start the search that’s deducted from the final fee or refunded if you don’t hire. If your VA doesn’t work out within 12 months, we replace them at no additional cost.',
                    'item_1_cta_text' => '',
                    'item_2_cta_text' => '',
                    'item_2_title' => 'The employment relationship<br>is direct',
                ],
            ]],
        ],
        'overrides' => [
            // Athena's hero leads on executive support and speed, not on the agency model.
            'hero' => [
                'subheadline' => '<strong>Remote Leverage gives you the same executive support without all the monthly fees.</strong> Get matched with top 1% talent from Latin America and the Caribbean in as little as 2–3 days, own the relationship with your VA from day one.',
            ],
            // Its two choice cards name the concrete terms (12-month contract, buyout fee)
            // rather than describing the two hiring models in the abstract.
            'solution-choice' => [
                'card_1_title' => 'Athena',
                'card_1_text' => 'A Philippines-based Executive Assistant, employed by Athena. You’re locked into a 12-month contract, a big portion of your monthly fee never reaches your assistant, and a $24,000 buyout fee if you ever want to hire them directly.',
                'card_2_title' => 'Remote Leverage',
                'card_2_text' => 'No contracts, no lock-in. Your VA is sourced mostly from Latin America, hired directly by you, and keeps 100% of their wage. Remote Leverage covers roles across sales, support, marketing, admin, e-commerce, and more',
            ],
            'comparing-costs' => [
                'description' => 'Remote Leverage costs a one-time fee, 40% of the VA’s yearly salary, or as low as $4,000 per VA with a 3-VA bundle and the VA is yours from day one. Athena charges $36,000/year, plus a $24,000 buyout if you ever want to hire directly.',
            ],
            // Production's Athena steps carry no card titles — just the four sentences.
            'hiring-easy' => [
                'cards_0_title' => '',
                'cards_1_title' => '',
                'cards_2_title' => '',
                'cards_3_title' => '',
            ],
            'money-goes' => [
                'description' => 'Most people don’t know exactly where their money goes when paying for a VA — they see one monthly number and assume it all goes to the person doing the work. In reality, how much actually reaches your assistant can vary a lot.',
            ],
            'how-rl-works' => [
                'headline' => 'You interview and select your favorite.',
                'subheadline' => 'Hire your perfect match only after interviewing candidates yourself.',
                'steps_0_title' => 'Hire directly, no ongoing fees',
                'steps_0_text' => 'You contract and manage the support you need – no middleman.',
                'steps_1_title' => 'We screen hundreds of applicants',
                'steps_1_text' => 'And present a shortlist of four to six candidates who match your brief, typically within 72 hours.',
                'steps_2_title' => 'Run payouts and track hours',
                'steps_2_text' => 'For individuals or entire teams. You interview the slate and choose who to hire. Add new countries and contractors easily',
                'steps_3_title' => 'You employ the VA directly.',
                'steps_3_text' => 'Our partner company handles payroll and compliance in the VA’s country of residence.',
            ],
            // The second statement of the pair says six months on Athena, not twelve.
            'assurance-pair' => [
                'item_1_text' => 'A deposit is required to start the search that’s deducted from the final fee or refunded if you don’t hire. If your VA doesn’t work out within six months, we replace them at no additional cost.',
                'item_2_title' => 'The employment relationship<br>is direct.',
            ],
            // Athena's explainer states its price and contract outright; the Wing one
            // describes a subscription tier structure.
            'how-competitor-works' => [
                'cards_0_title' => "Pricing model and what's included",
                'cards_0_text' => 'Athena charges $3,000 per month under a 12-month contract. This includes a matched Executive Assistant, along with payroll, taxes, and compliance handled by Athena on your behalf.',
                'cards_1_title' => 'How matching and onboarding work',
                'cards_1_text' => 'Once you sign on, Athena matches you with an Executive Assistant, typically within 2–3 weeks. New EAs complete a structured training program.',
                'cards_2_title' => 'The buyer Athena is built<br>for',
                'cards_2_text' => 'Athena is designed for founders and executives who want a single, dedicated Executive Assistant to support a wide range of tasks from scheduling to inbox management to general operations.',
            ],
            // Athena's talent band argues cost per head; the Wing one argues seniority.
            'talent-carousel' => [
                'body' => '<p>Finding the right talent means scaling smarter, not more expensively. Virtual assistants deliver top-tier support for a fraction of in-house costs. You save on salaries, benefits, and overhead while freeing your core team. That lets you reinvest cash into growth and focus on what truly moves the needle.</p>'
                    .'<p><strong>Result:</strong> You grow faster, spend less, and scale leaner with every VA you hire.</p>',
                'profiles_1_role' => 'Sales Representative',
                'profiles_2_role' => 'Social Media Manager',
            ],
            'talent-pool' => [
                'body' => '<p>Remote Leverage offers professionals in highly compatible time zones who cover a broad spectrum of roles – from executive assistants to operations specialists. This means faster turnaround, smoother collaboration, and no costly delays waiting for responses.</p>'
                    .'<p><strong>Result:</strong> You get the right talent, in the right time zone, for the right role – every time.</p>',
            ],
            // Athena's switch section is a three-step handover, not the custom-search pitch.
            'switch' => [
                'headline' => 'Already using Athena but want to switch? Here’s how',
                'subheadline' => 'If you’ve decided to switch from Athena to a direct hire, the cleanest path takes about four weeks. Here’s the order of operations:',
                'cards_0_title' => '',
                'cards_0_text' => 'Document the work Athena’s VA is doing now. The next person needs SOPs, not just a job description.',
                'cards_1_title' => '',
                'cards_1_text' => 'Book a consultation to tell us about the role, hours, required skills, ideal background. We’ll have a candidate slate to you in roughly 72 hours.',
                'cards_2_title' => '',
                'cards_2_text' => 'Plan an overlap window. Keep Athena’s VA in place for two to four weeks while you screen, interview, and onboard the new hire.',
            ],
        ],
        // Athena's more-affordable band is one image-and-copy unit, not the two
        // competitor cards the Wing pages run, so the section swaps block.
        'replaceBlock' => [
            // Athena sells one role, so its "roles supported" band is a paragraph
            // explaining that, not the industry carousel the Wing pages run.
            'competitor-roles' => [
                'blockName' => 'acf/media-copy',
                'prefix' => 'field_media_copy_block_',
                'data' => [
                    'headline' => 'Roles and Industries<br>Athena Supports',
                    'body' => '<p>Athena offers a single role — Executive Assistant — trained as a generalist to support founders and executives across a wide range of industries. Rather than matching clients with someone experienced in their specific field, Athena prepares every EA the same way, so they can adapt to different business needs as they come up. This means you won’t find role-specific specialists through Athena, like a bookkeeper for a finance firm or a credentialed assistant for a healthcare practice — every client works with the same type of generalist support, regardless of industry.</p>',
                    'image' => '',
                    'cta_text' => '',
                    'cta_url' => '#booking-footer',
                ],
            ],
            'more-affordable' => [
                'blockName' => 'acf/media-copy',
                'prefix' => 'field_media_copy_block_',
                'data' => [
                    'headline' => 'Which is more affordable, Remote Leverage or Athena',
                    'body' => '<p><strong>Remote Leverage is the more affordable option and that gap only grows over time.</strong> Athena’s fees keep adding up every year, while Remote Leverage’s one-time fee means costs stay flat the longer you keep your VA.</p>',
                    'image' => 549,
                    'image_position' => 'left',
                    'cta_text' => '',
                    'cta_url' => '#booking-footer',
                ],
            ],
        ],
        // Athena carries the standard hire-va FAQ set, not the competitor-specific one.
        'faqSet' => 'hireVa4Faqs',
        // The review wall is headed plainly here; only the Wing pages prefix the brand.
        'replace' => ['Remote Leverage Client Reviews' => 'Client Reviews'],
    ],
];

/** Replace a section's table rows with [label, competitorValue, rlValue] triples. */
function rl_set_rows(array $block, array $rows): array
{
    $name = $block['blockName'] ?? '';

    // Sections often wrap their table in a core/group — descend to the ACF block.
    if ($name !== 'acf/data-table' && $name !== 'acf/cost-comparison') {
        if (! empty($block['innerBlocks'])) {
            $block['innerBlocks'] = array_map(fn ($b) => rl_set_rows($b, $rows), $block['innerBlocks']);
        }

        return $block;
    }

    $d = $block['attrs']['data'];
    $n = count($rows);

    if ($name === 'acf/data-table') {
        foreach (array_keys($d) as $k) {
            if (preg_match('/^_?rows_\\d+_/', $k)) {
                unset($d[$k]);
            }
        }
        $d['rows'] = $n;
        foreach ($rows as $i => [$label, $a, $b]) {
            $d["rows_{$i}_feature"] = $label;
            $d["_rows_{$i}_feature"] = 'field_data_table_block_rows_feature';
            $d["rows_{$i}_diy"] = $a;
            $d["_rows_{$i}_diy"] = 'field_data_table_block_rows_diy';
            $d["rows_{$i}_rl"] = $b;
            $d["_rows_{$i}_rl"] = 'field_data_table_block_rows_rl';
        }
    } else {
        $K = 'field_cost_comparison_block_';
        foreach (array_keys($d) as $k) {
            if (preg_match('/^_?table_[12]_rows_\\d+_/', $k)) {
                unset($d[$k]);
            }
        }
        $d['table_1_rows'] = $n;
        $d['table_2_rows'] = $n;
        foreach ($rows as $i => [$label, $a, $b]) {
            foreach (['1' => $a, '2' => $b] as $t => $val) {
                $d["table_{$t}_rows_{$i}_label"] = $label;
                $d["_table_{$t}_rows_{$i}_label"] = $K."table_{$t}_rows_label";
                $d["table_{$t}_rows_{$i}_value"] = $val;
                $d["_table_{$t}_rows_{$i}_value"] = $K."table_{$t}_rows_value";
            }
        }
    }

    $block['attrs']['data'] = $d;

    return $block;
}

/** Recursively rewrite the competitor name in every string value. */
function rl_rename(array $block, array $map): array
{
    $walk = function ($v) use (&$walk, $map) {
        if (is_string($v)) {
            foreach ($map as $from => $to) {
                $v = str_replace($from, $to, $v);
            }

            return $v;
        }
        if (is_array($v)) {
            return array_map($walk, $v);
        }

        return $v;
    };

    if (! empty($block['attrs']['data'])) {
        $block['attrs']['data'] = $walk($block['attrs']['data']);
    }
    if (! empty($block['innerHTML'])) {
        $block['innerHTML'] = $walk($block['innerHTML']);
    }
    if (! empty($block['innerContent'])) {
        $block['innerContent'] = $walk($block['innerContent']);
    }
    if (! empty($block['innerBlocks'])) {
        $block['innerBlocks'] = array_map(fn ($b) => rl_rename($b, $map), $block['innerBlocks']);
    }

    return $block;
}

/**
 * Set ACF data fields on a section, descending through wrapper groups to reach the
 * ACF block. Field keys are only invented for fields the block does not already
 * carry, so an override never rewrites a key the mother already declares.
 */
function rl_set_fields(array $block, array $fields, ?string $prefix = null): array
{
    if (! empty($block['attrs']['data'])) {
        $d = $block['attrs']['data'];
        $guess = $prefix ?: rl_field_prefix($block['blockName'] ?? '');
        foreach ($fields as $k => $v) {
            $d[$k] = $v;
            if (! isset($d["_{$k}"]) && $guess) {
                $d["_{$k}"] = $guess.$k;
            }
        }
        $block['attrs']['data'] = $d;

        return $block;
    }

    if (! empty($block['innerBlocks'])) {
        $block['innerBlocks'] = array_map(fn ($b) => rl_set_fields($b, $fields, $prefix), $block['innerBlocks']);
    }

    return $block;
}

/** ACF field-key prefix for a block, derived from its registered name. */
function rl_field_prefix(string $blockName): string
{
    $map = [
        'acf/comparison-hero' => 'field_comparison_hero_block_',
        'acf/solution-choice' => 'field_solution_choice_block_',
        'acf/cost-comparison' => 'field_cost_comparison_block_',
        'acf/image-card-grid' => 'field_image_card_grid_block_',
        'acf/split-compare-cards' => 'field_split_compare_cards_block_',
        'acf/process-step-cards' => 'field_process_step_cards_block_',
        'acf/talent-carousel' => 'field_talent_carousel_block_',
        'acf/media-copy' => 'field_media_copy_block_',
        'acf/accordion-faq' => 'field_accordion_faq_block_',
        'acf/assurance-pair' => 'field_assurance_pair_',
        'acf/guarantee-card' => 'field_guarantee_card_',
        'acf/data-table' => 'field_data_table_block_',
        'acf/roles-carousel' => 'field_roles_carousel_block_',
    ];

    return $map[$blockName] ?? '';
}

/** Literal string swaps across a section's data and markup. */
function rl_replace(array $block, array $map): array
{
    return rl_rename($block, $map);
}

/** Re-encode a section's FAQ repeater from a BlockDefaults set. */
function rl_set_faqs(array $block, array $faqs): array
{
    if (($block['blockName'] ?? '') !== 'acf/accordion-faq') {
        if (! empty($block['innerBlocks'])) {
            $block['innerBlocks'] = array_map(fn ($b) => rl_set_faqs($b, $faqs), $block['innerBlocks']);
        }

        return $block;
    }

    $d = $block['attrs']['data'];
    foreach (array_keys($d) as $k) {
        if (preg_match('/^_?faqs_\\d+_/', $k)) {
            unset($d[$k]);
        }
    }
    $d['faqs'] = count($faqs);
    foreach (array_values($faqs) as $i => $faq) {
        $d["faqs_{$i}_question"] = $faq['q'];
        $d["_faqs_{$i}_question"] = 'field_accordion_faq_block_faqs_question';
        $d["faqs_{$i}_answer"] = $faq['a'];
        $d["_faqs_{$i}_answer"] = 'field_accordion_faq_block_faqs_answer';
    }
    $block['attrs']['data'] = $d;

    return $block;
}

foreach ($pages as $slug => $cfg) {
    $tableData = [];
    if (! empty($cfg['tableSource'])) {
        $tableData = json_decode(file_get_contents(__DIR__.'/'.$cfg['tableSource']), true) ?: [];
    }

    // Sections a page states twice are cloned from the mother before anything is
    // applied, so the copy picks up the same renames and overrides as the original.
    $byKey = [];
    foreach ($mother as $b) {
        $byKey[$b['attrs']['metadata']['name'] ?? ''] = $b;
    }

    $kept = [];
    foreach ($mother as $b) {
        $key = $b['attrs']['metadata']['name'] ?? '';
        if (in_array($key, $cfg['exclude'], true)) {
            continue;
        }

        $emit = [[$key, $b, []]];
        foreach ($cfg['insertAfter'][$key] ?? [] as $extra) {
            if (isset($byKey[$extra['from']])) {
                $emit[] = [$extra['from'], $byKey[$extra['from']], $extra['overrides'] ?? []];
            }
        }

        foreach ($emit as [$k, $block, $extraOverrides]) {
            if (isset($cfg['replaceBlock'][$k])) {
                $swap = $cfg['replaceBlock'][$k];
                $data = [];
                foreach ($swap['data'] as $f => $v) {
                    $data[$f] = $v;
                    $data["_{$f}"] = ($swap['prefix'] ?? rl_field_prefix($swap['blockName'])).$f;
                }
                $block = [
                    'blockName' => $swap['blockName'],
                    'attrs' => [
                        'name' => $swap['blockName'],
                        'data' => $data,
                        'align' => $swap['align'] ?? 'full',
                        'mode' => 'preview',
                        'metadata' => ['name' => $k],
                    ],
                    'innerBlocks' => [],
                    'innerHTML' => '',
                    'innerContent' => [],
                ];
            }

            if (isset($cfg['tables'][$k], $tableData[$cfg['tables'][$k]])) {
                $block = rl_set_rows($block, $tableData[$cfg['tables'][$k]]['rows']);
            }

            if (! empty($cfg['faqSet']) && $k === 'faq') {
                $block = rl_set_faqs($block, \App\Support\BlockDefaults::{$cfg['faqSet']}());
            }

            if (isset($cfg['overrides'][$k])) {
                $block = rl_set_fields($block, $cfg['overrides'][$k]);
            }
            if ($extraOverrides) {
                $block = rl_set_fields($block, $extraOverrides);
            }

            if ($cfg['competitor']) {
                $map = [$cfg['competitor']['from'] => $cfg['competitor']['to']] + ($cfg['competitor']['short'] ?? []);
                $block = rl_rename($block, $map);
            }

            if (! empty($cfg['replace'])) {
                $block = rl_replace($block, $cfg['replace']);
            }

            $kept[] = $block;
        }
    }

    $content = '';
    foreach ($kept as $b) {
        $content .= serialize_block($b);
    }

    $header = "<?php\n\n/**\n * Title: {$cfg['title']}\n * Slug: remote-leverage/{$slug}\n * Categories: remote-leverage\n"
        ." * Description: {$cfg['desc']}\n *\n * GENERATED from patterns/comparison-full.php — do not edit by hand.\n"
        ." * Run: wp eval-file scripts/build-comparison-patterns.php\n */\n?>\n\n";

    file_put_contents(get_theme_file_path("patterns/{$slug}.php"), $header.$content."\n");
    printf("%-30s %2d sections (excluded %d)\n", $slug, count($kept), count($cfg['exclude']));
}
