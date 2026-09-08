# Landing Page Migration & Modernization Workflow

A comprehensive guide for extracting any production landing page, dissecting its sections, translating it into the Remote Leverage modern design system (Sage 10 + Tailwind CSS v4 + Alpine.js), creating or reusing modular ACF Gutenberg blocks with editable demo content, and publishing a preview testing page in WordPress.

---

## 1. High-Level Architecture & Standards

All pages and blocks in Remote Leverage v2 follow these foundational pillars:

| Layer | Technology | Standard / Role |
| :--- | :--- | :--- |
| **Framework** | **Roots Sage 10** | Laravel-style application structure, Blade templating, Composer autoloading. |
| **Styling** | **Tailwind CSS v4** | Design tokens defined in `@theme` block in CSS (`brand-dark-violet`, `brand-purple`, `bg-light`, `rounded-card`, `p-card`). |
| **Block Composer** | **Log1x AcfComposer** | Code-first ACF Block definitions (`app/Blocks/*Block.php`), automated field registration via Builder. |
| **Interactivity** | **Alpine.js** | Lightweight, self-contained client-side state for accordions, tickers, video modals, and wizards without jQuery or bulky libraries. |
| **Block Defaults** | **`App\Support\BlockDefaults`** | Centralized demo content, attachment mapping, text sanitization, and Gutenberg pattern pre-population. |
| **Block Patterns** | **WordPress Patterns (`patterns/`)** | Clean, modular page sections assembled with pre-hydrated ACF block attributes so all default demo content is immediately visible and editable in Gutenberg. |

---

## Non-Negotiable Core Directives

> [!IMPORTANT]
> **1. Canonical Container Width is ALWAYS 1380px**:
> - Every main section container on every migrated page MUST be constrained to **1380px**.
> - In Gutenberg Block Patterns: Every root `wp:group` MUST declare `"layout":{"type":"constrained","contentSize":"1380px"}`.
> - In Blade Views & Tailwind Classes: Section content wrappers MUST use `w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8`.
> - **Prohibited**: NEVER use `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, `max-w-7xl`, `1140px`, or `1200px` for main content wrappers.

> [!IMPORTANT]
> **2. Strict 1:1 Copy Fidelity (Zero Creative Deviation)**:
> - When given a target page, produce an **exact 1:1 visual and structural copy** using our design system tokens.
> - **Do NOT Redesign**: Do not re-imagine the layout, omit elements, alter section ordering, change background colors, or replace bespoke components with generic equivalents.
> - **Inspect Production CSS**: Always fetch the production page's compiled stylesheet (`post-<id>.css`) to inspect the exact background colors, gradients, background images, borders, and paddings.
> - **Replicate All Elements**: Every eyebrow pill, trust counter, 5-star rating, checklist item, comparison card, badge graphic, video modal, and form field must be present in the exact relative arrangement.
> - **Extend Design System Tokens**: Map all styles to theme tokens (`brand-midnight`, `brand-hero`, `brand-purple`, `bg-light`, `rounded-card`, etc.). If a token does not exist for an exact production value (e.g. a specific radial gradient, overlay image, or card border), **extend the design system tokens** in `theme.json` or `resources/css/app.css` rather than altering the design.

> [!IMPORTANT]
> **3. Zero "Unexpected or Invalid Content" Errors (Strict Gutenberg Block Grammar)**:
> - Patterns and pages MUST NEVER trigger Gutenberg's `"Block contains unexpected or invalid content. [Attempt recovery]"` modal in the block editor.
> - **Why This Error Occurs**:
>   - Gutenberg validates blocks by comparing the saved HTML in `post_content` against the block's JavaScript `save({ attributes })` output.
>   - Core container blocks (`core/columns`, `core/column`, `core/group`) use `<InnerBlocks.Content />` and expect **only valid Gutenberg block comments** (`<!-- wp:... -->`) as their children.
>   - When arbitrary raw HTML tags (e.g. `<div class="pill">`, `<div class="grid">`, raw `<h1>`, or un-bracketed SVG markup) are injected directly inside `<!-- wp:column -->` or `<!-- wp:group -->`, Gutenberg flags them as unexpected/invalid content chunks.
>   - Wrapping a child block inside raw HTML (e.g. `<div class="my-wrapper"><!-- wp:acf/booking /--></div>`) corrupts the block tree because Gutenberg cannot associate the wrapping DOM nodes with any registered block.
> - **The Core Commandments**:
>   1. **ACF Block First for Bespoke/Complex Sections (MANDATORY)**:
>      - Whenever a section contains split columns with custom styling, trust pills, checklist grids, bespoke cards, or embedded Livewire/Alpine components (e.g. Split Hero, Comparison Matrix, 3-Step Process, Guarantee with Overlapping Badges), **DO NOT stitch it together using raw HTML inside `core/columns` or `core/column`**.
>      - **Build a dedicated code-first ACF Block** (`Log1x\AcfComposer\Block`) with a Blade template (`resources/views/blocks/*.blade.php`).
>      - Blade gives 100% control over Tailwind CSS v4 classes, semantic HTML, Alpine.js, and Livewire without any Gutenberg block validation restrictions.
>      - Gutenberg stores a single server-rendered comment (`<!-- wp:acf/section-slug {...} /-->`), which **can NEVER fail Gutenberg client-side block validation**.
>   2. **If Core Blocks Are Used, Follow Strict Grammar**:
>      - EVERY visual element MUST be a native block comment (`wp:heading`, `wp:paragraph`, `wp:group`, `wp:list`).
>      - Custom raw HTML MUST be wrapped in `<!-- wp:html --><div>...</div><!-- /wp:html -->`. NEVER leave raw HTML outside a block comment inside a container block.
>      - NEVER wrap a block comment inside an arbitrary unclosed or closed HTML `<div>` tag.
>   3. **Automated Validation in QA**:
>      - Run the `parse_blocks()` validation audit before reporting completion. Any block with `blockName === null` containing non-whitespace `innerHTML` inside a container block is an error that MUST be eliminated before delivery.

---

## 2. End-to-End Migration Procedure

When given a URL from a production site (e.g. `https://remoteleverage.com/sample-landing-page`), execute the following workflow:

```
┌────────────────────────────────────────────────────────┐
│ 1. INTAKE & RECONNAISSANCE                             │
│    Fetch page HTML, copy, structure, and media assets   │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 2. SECTION DISSECTION & BLOCK MAPPING                  │
│    Categorize sections: Hero, Ticker, Features, etc.   │
│    Determine existing blocks to reuse vs new blocks    │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 3. MEDIA SIDELOADING & ATTACHMENT MAPPING              │
│    Download images to public/images/<slug>/            │
│    Import to WP Media Library (get real Attachment IDs)│
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 4. BLOCK DEFINITIONS & VIEW TEMPLATES                  │
│    Build app/Blocks/<Name>Block.php                    │
│    Build resources/views/blocks/<slug>.blade.php       │
│    Apply cleanText() & resolveImageUrl()               │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 5. BLOCK DEFAULTS & PATTERN CREATION                   │
│    Add defaults to App\Support\BlockDefaults           │
│    Generate pre-populated patterns in patterns/<slug>  │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 6. TESTING PAGE GENERATION & WP_SLASH SYNCHRONIZATION  │
│    wp post create --post_type=page                     │
│    Update post_content using wp_slash($content)        │
└──────────────────────────┬─────────────────────────────┘
                           ▼
┌────────────────────────────────────────────────────────┐
│ 7. VERIFICATION & QA                                   │
│    parse_blocks() check, the_content check, npm build  │
└────────────────────────────────────────────────────────┘
```

---

## 3. Section Dissection Matrix

Dissect the production URL into standard Remote Leverage modular sections:

| Section Type | Production Purpose | Remote Leverage Pattern / Block | Key Design System Tokens |
| :--- | :--- | :--- | :--- |
| **Hero** | Primary headline, subheadline, trust hook, primary CTA, visual showcase | `remote-leverage/hero`<br>[`TalentMarqueeBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/TalentMarqueeBlock.php) | `bg-light`, `has-huge-font-size`, `letter-spacing: -0.03em`, pill purple button (`is-style-pill-purple`) |
| **Social Proof / Logos** | Client & partner brand validation | `remote-leverage/client-logos`<br>[`ClientLogosMarqueeBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/ClientLogosMarqueeBlock.php) | `bg-light`, infinite CSS ticker animation (`animate-marquee-logos`), shrink-0 item wrapping |
| **Global Advantage / Features** | 3-column benefit cards highlighting nearshore talent quality | `remote-leverage/worlds-best-talent`<br>[`FeatureCardsBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/FeatureCardsBlock.php) (`columns: 3`) | `rounded-card`, `p-card`, `shadow-[0_4px_24px_rgba(0,0,0,0.03)]`, hover translate `-translate-y-1` |
| **Role Specialties** | Cards showcasing specific functions (Executive, Healthcare, Sales, Ops) | `remote-leverage/beyond-virtual-assistant`<br>[`DepartmentCardsBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/DepartmentCardsBlock.php) | Background photography with dark gradient overlay, frosted glass blur layer (`backdrop-blur`), white typography |
| **Trust & Impact Metrics** | VAs placed, countries, economic impact | `remote-leverage/trust-and-impact`<br>[`TrustStatsBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/TrustStatsBlock.php) | Glassmorphism stats grid, dark card accent, bold metric counters |
| **Comparison Matrix** | DIY / Traditional Agency vs Remote Leverage | `remote-leverage/why-companies-choose`<br>[`DataTableBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/DataTableBlock.php) | Floating comparison rows, checkmark indicators, purple accent pills for Remote Leverage column |
| **Onboarding Process** | 3-step sequence: vacancy to onboarded in days | `remote-leverage/process-steps`<br>[`ProcessStepsBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/ProcessStepsBlock.php) | 3-column cards with numeric counters (`01`, `02`, `03`), clean descriptions |
| **Replacement Guarantee** | Zero-risk 12-month replacement guarantee | `remote-leverage/replacement-guarantee`<br>(Core blocks: `wp:group`, `wp:columns`) | `has-brand-dark-violet-background-color`, `has-white-color`, guarantee badge illustration |
| **Video Testimonials** | Client video case studies and quotes | `remote-leverage/results-testimonials-faq`<br>[`TestimonialsBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/TestimonialsBlock.php) | Vimeo video modal trigger, thumbnail poster, glass play icon, duration badge, client quote |
| **Accordion FAQ** | SEO & objection handling | `remote-leverage/results-testimonials-faq`<br>[`AccordionFaqBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/AccordionFaqBlock.php) | 2-column balanced Alpine.js accordion, automated Schema.org `FAQPage` JSON-LD |
| **Booking Footer / Funnel** | Final conversion scheduler | `remote-leverage/booking-footer`<br>[`BookingBlock`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Blocks/BookingBlock.php) | Dark violet skin, embedded reactive booking funnel wizard |

---

## 4. Block Development Standards (Sage 10 + AcfComposer)

Every ACF block in the theme must adhere to the following architecture:

### 1. Block Class (`app/Blocks/<Name>Block.php`)

```php
<?php

declare(strict_types=1);

namespace App\Blocks;

use Log1x\AcfComposer\Block;
use Log1x\AcfComposer\Builder;

class SampleFeatureBlock extends Block
{
    public $name = 'Sample Feature';
    public $slug = 'sample-feature';
    public $description = 'Feature highlight section with modern cards.';
    public $category = 'remote-leverage';
    public $icon = 'star-filled';
    public $keywords = ['features', 'benefits', 'cards'];
    public $view = 'blocks.sample-feature';

    public function with(): array
    {
        return [
            'headline' => \App\Support\BlockDefaults::cleanText(get_field('headline') ?: 'Default Headline'),
            'cards' => $this->cards(),
        ];
    }

    public function fields(): array
    {
        $fields = Builder::make('sample_feature_block');

        $fields
            ->addText('headline', ['label' => 'Section Headline'])
            ->addRepeater('cards', [
                'label' => 'Feature Cards',
                'layout' => 'block',
                'button_label' => 'Add Feature',
            ])
                ->addText('title', ['label' => 'Title'])
                ->addTextarea('desc', ['label' => 'Description', 'rows' => 2])
                ->addImage('img', ['label' => 'Card Image', 'return_format' => 'url'])
            ->endRepeater();

        return $fields->build();
    }

    public function cards(): array
    {
        $custom = function_exists('get_field') ? get_field('cards') : null;
        $cards = (! empty($custom) && is_array($custom))
            ? $custom
            : \App\Support\BlockDefaults::sampleFeatureCards();

        // Always sanitize text and resolve image URLs
        return array_map(function ($card) {
            $card['title'] = \App\Support\BlockDefaults::cleanText($card['title'] ?? '');
            $card['desc'] = \App\Support\BlockDefaults::cleanText($card['desc'] ?? '');
            $card['img'] = \App\Support\BlockDefaults::resolveImageUrl($card['img'] ?? '');
            return $card;
        }, $cards);
    }
}
```

### 2. Blade View Template (`resources/views/blocks/sample-feature.blade.php`)

```blade
<div class="w-full">
    @if (! empty($headline))
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-tight mb-8 text-center">
            {!! $headline !!}
        </h2>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-card">
        @foreach ($cards as $card)
            <div class="bg-white rounded-card p-card flex flex-col justify-between border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300">
                <div>
                    @if (! empty($card['img']))
                        <div class="w-full aspect-video rounded-xl overflow-hidden mb-4 bg-slate-100">
                            <img src="{{ $card['img'] }}" alt="{!! strip_tags($card['title']) !!}"
                                loading="lazy" decoding="async" class="w-full h-full object-cover">
                        </div>
                    @endif
                    <h3 class="font-display text-xl font-bold text-black tracking-[-0.02em] leading-snug mb-2">
                        {!! $card['title'] !!}
                    </h3>
                    <p class="text-sm text-black/80 leading-relaxed">
                        {{ $card['desc'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

---

## 5. Centralized Block Defaults & Editable Content

### The Problem This Solves
When patterns contain empty block comments (`<!-- wp:acf/sample {"data":{}} /-->`), Gutenberg loads 0 rows in the sidebar form. When an editor clicks "Add Row", ACF saves that 1 new row, which stops `get_field(...)` from being empty—wiping out all hardcoded fallback data.

### The Solution in [`BlockDefaults.php`](file:///Users/adriansalvatori/Documents/projects-rl/remoteleverage-v2/web/app/themes/remote-leverage/app/Support/BlockDefaults.php)

1. **`cleanText(mixed $text): string`**: Decodes any HTML entities or corrupted unicode escapes (`u003c` -> `<`, `u0026` -> `&`, `u0022` -> `"`).
2. **`resolveImageUrl(mixed $image): string`**: Safely resolves Attachment IDs, URL strings, or ACF image arrays into a direct URL string.
3. **`getAttachmentId(mixed $value): mixed`**: Maps image filenames to their WordPress media attachment IDs so Gutenberg's ACF image picker can display image thumbnails, filenames, and replacement buttons.
4. **`encodeRepeater(string $fieldName, string $fieldKey, array $rows, array &$data)`**: Serializes repeater rows with child subfield keys into the exact JSON format ACF expects in block comment attributes:
   ```php
   $data['cards'] = 4;
   $data['_cards'] = 'field_sample_feature_block_cards';
   $data['cards_0_title'] = 'My Card Title';
   $data['_cards_0_title'] = 'field_sample_feature_block_cards_title';
   ```
5. **`patternBlock(string $slug, array $data, array $attrs)`**: Formats the final Gutenberg comment:
   ```php
   return '<!-- wp:acf/' . $slug . ' ' . json_encode($blockAttrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
   ```
6. **`filterLoadValue()` Hook**: Pre-fills repeater rows in Gutenberg's sidebar form when a block is inserted directly from the block inserter. If an editor intentionally deletes all rows and saves, ACF sets metadata to `'0'`—`filterLoadValue` detects this and respects the deletion.

---

## 6. WordPress Block Pattern Standard

When creating a pattern file in `patterns/<slug>.php`:

```php
<?php
/**
 * Title: Sample Feature Section
 * Slug: remote-leverage/sample-feature
 * Categories: remote-leverage
 * Description: 3-column modern feature cards.
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
<div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
    <?= \App\Support\BlockDefaults::renderSampleFeature() ?>
</div>
<!-- /wp:group -->
```

> [!IMPORTANT]
> **HTML Tag Balance in Patterns**:
> Every `<!-- wp:group -->` block MUST have its corresponding opening `<div class="wp-block-group...">` tag directly before its child content and `</div>` before `<!-- /wp:group -->`. Missing opening or closing tags will trigger Gutenberg's `"Block contains unexpected or invalid content"` recovery modal.

---

## 7. Media Sideloading Procedure

Always ensure demo images exist in the WordPress Media Library with real attachment IDs:

```php
// In a WP-CLI script or migration helper:
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

$file_array = [
    'name' => basename($filepath),
    'tmp_name' => $tmpFile,
];
$attachmentId = media_handle_sideload($file_array, 0);
```

---

## 8. Safe Page Generation & `wp_slash` Rule

When creating or updating a page via WP-CLI or PHP:

> [!CAUTION]
> **The `wp_slash` Requirement**:
> `wp_update_post()` and `wp_insert_post()` execute `wp_unslash()` on all passed attributes.
> If you pass JSON-encoded block comment markup containing unicode escapes (e.g. `\u003c` or `\u0022`) without wrapping it in `wp_slash()`, WordPress will strip all backslashes. This causes `<p>` tags to become raw `u003cpu003e` text in the editor and on the frontend!
>
> **Correct Usage**:
> ```php
> wp_update_post([
>     'ID' => $pageId,
>     'post_content' => wp_slash($content),
> ]);
> ```

---

## 9. Verification & QA Checklist

Run these automated verification commands before delivering the preview page to the user:

```bash
# 1. Verify all registered patterns compile cleanly
wp eval '
$patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
foreach ($patterns as $p) {
    if (str_starts_with($p["name"], "remote-leverage/")) {
        echo $p["name"] . " length: " . strlen($p["content"]) . "\n";
    }
}
'

# 2. Verify block hierarchy and ensure zero orphan HTML chunks
wp eval '
$p = get_post($TEST_PAGE_ID);
$blocks = parse_blocks($p->post_content);
function audit_blocks($blocks) {
    foreach ($blocks as $b) {
        if (($b["blockName"] ?? "") === null && trim($b["innerHTML"] ?? "") !== "") {
            echo "ERROR: Stray HTML chunk: " . trim($b["innerHTML"]) . "\n";
        }
        if (!empty($b["innerBlocks"])) audit_blocks($b["innerBlocks"]);
    }
}
audit_blocks($blocks);
echo "Block audit complete.\n";
'

# 3. Check for unicode corruption and broken img tags on frontend render
wp eval '
$post = get_post($TEST_PAGE_ID);
setup_postdata($post);
$html = apply_filters("the_content", $post->post_content);
if (preg_match("/u00[0-9a-f]{2}/i", $html, $matches)) {
    echo "ERROR: Corrupted unicode escape found: " . $matches[0] . "\n";
} else {
    echo "Unicode integrity: CLEAN\n";
}
preg_match_all("/<img[^>]+src=[\x27\"]([^\x27\"]*)[\x27\"]/", $html, $matches);
$empty = 0; foreach ($matches[1] as $s) if (empty($s)) $empty++;
echo "Empty img srcs: " . $empty . " / " . count($matches[1]) . "\n";
'

# 4. Compile production assets
npm run build

# 5. Lint PHP syntax
php -l app/Support/BlockDefaults.php
```
