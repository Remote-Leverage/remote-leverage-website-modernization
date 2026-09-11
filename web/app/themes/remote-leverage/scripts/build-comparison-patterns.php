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
    ],
    'comparison-athena-full' => [
        'title' => 'Full Page - Comparison (Athena)',
        'desc' => 'Athena comparison page.',
        'page' => 382,
        'exclude' => ['built-for-control', 'screening', 'replacement-policies', 'best-for', 'full-comparison', 'competitor-roles'],
        'competitor' => ['from' => 'Wing Assistant', 'to' => 'Athena', 'short' => ['Wing' => 'Athena']],
        // Athena's own table data, keyed by section. Captured from the page before
        // it was converted; see scripts/comparison-athena-tables.json.
        'tables' => ['at-a-glance' => 0, 'comparing-costs' => 1, 'money-goes' => 2],
        'tableSource' => 'comparison-athena-tables.json',
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
        foreach (array_keys($d) as $k) { if (preg_match('/^_?rows_\\d+_/', $k)) { unset($d[$k]); } }
        $d['rows'] = $n;
        foreach ($rows as $i => [$label, $a, $b]) {
            $d["rows_{$i}_feature"] = $label; $d["_rows_{$i}_feature"] = 'field_data_table_block_rows_feature';
            $d["rows_{$i}_diy"] = $a;         $d["_rows_{$i}_diy"] = 'field_data_table_block_rows_diy';
            $d["rows_{$i}_rl"] = $b;          $d["_rows_{$i}_rl"] = 'field_data_table_block_rows_rl';
        }
    } else {
        $K = 'field_cost_comparison_block_';
        foreach (array_keys($d) as $k) { if (preg_match('/^_?table_[12]_rows_\\d+_/', $k)) { unset($d[$k]); } }
        $d['table_1_rows'] = $n;
        $d['table_2_rows'] = $n;
        foreach ($rows as $i => [$label, $a, $b]) {
            foreach (['1' => $a, '2' => $b] as $t => $val) {
                $d["table_{$t}_rows_{$i}_label"] = $label; $d["_table_{$t}_rows_{$i}_label"] = $K."table_{$t}_rows_label";
                $d["table_{$t}_rows_{$i}_value"] = $val;   $d["_table_{$t}_rows_{$i}_value"] = $K."table_{$t}_rows_value";
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
            foreach ($map as $from => $to) { $v = str_replace($from, $to, $v); }

            return $v;
        }
        if (is_array($v)) { return array_map($walk, $v); }

        return $v;
    };

    if (! empty($block['attrs']['data'])) { $block['attrs']['data'] = $walk($block['attrs']['data']); }
    if (! empty($block['innerHTML'])) { $block['innerHTML'] = $walk($block['innerHTML']); }
    if (! empty($block['innerContent'])) { $block['innerContent'] = $walk($block['innerContent']); }
    if (! empty($block['innerBlocks'])) {
        $block['innerBlocks'] = array_map(fn ($b) => rl_rename($b, $map), $block['innerBlocks']);
    }

    return $block;
}

foreach ($pages as $slug => $cfg) {
    $tableData = [];
    if (! empty($cfg['tableSource'])) {
        $tableData = json_decode(file_get_contents(__DIR__.'/'.$cfg['tableSource']), true) ?: [];
    }

    $kept = [];
    foreach ($mother as $b) {
        $key = $b['attrs']['metadata']['name'] ?? '';
        if (in_array($key, $cfg['exclude'], true)) { continue; }

        if (isset($cfg['tables'][$key], $tableData[$cfg['tables'][$key]])) {
            $b = rl_set_rows($b, $tableData[$cfg['tables'][$key]]['rows']);
        }

        if ($cfg['competitor']) {
            $map = [$cfg['competitor']['from'] => $cfg['competitor']['to']] + ($cfg['competitor']['short'] ?? []);
            $b = rl_rename($b, $map);
        }

        $kept[] = $b;
    }

    $content = '';
    foreach ($kept as $b) { $content .= serialize_block($b); }

    $header = "<?php\n\n/**\n * Title: {$cfg['title']}\n * Slug: remote-leverage/{$slug}\n * Categories: remote-leverage\n"
        ." * Description: {$cfg['desc']}\n *\n * GENERATED from patterns/comparison-full.php — do not edit by hand.\n"
        ." * Run: wp eval-file scripts/build-comparison-patterns.php\n */\n?>\n\n";

    file_put_contents(get_theme_file_path("patterns/{$slug}.php"), $header.$content."\n");
    printf("%-30s %2d sections (excluded %d)\n", $slug, count($kept), count($cfg['exclude']));
}
