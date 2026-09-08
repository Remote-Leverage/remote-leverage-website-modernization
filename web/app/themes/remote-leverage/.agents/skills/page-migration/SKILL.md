---
name: page-migration
description: >-
  Migrate and modernize a production landing page URL into the Remote Leverage design system.
  Use when given a production URL (e.g. remoteleverage.com/...) to dissect it into sections,
  create or reuse modular ACF Gutenberg blocks and patterns with fully editable demo content,
  and publish a new preview testing page in WordPress.
---

# Landing Page Migration & Modernization Skill

This skill teaches the agent how to receive a production landing page URL, dissect its contents into sections, translate those sections into the modernized Remote Leverage design system (Roots Sage 10 + Tailwind CSS v4 + Alpine.js + Log1x AcfComposer), pre-hydrate all Gutenberg patterns with editable demo content, and publish a new WordPress testing page.

---

## Non-Negotiable Core Directives

### 1. Canonical Container Width is ALWAYS 1380px
- **Strict Rule**: Every main section container on every migrated page MUST be constrained to **1380px**.
- **In Gutenberg Block Patterns**: Every root `wp:group` MUST declare:
  ```json
  "layout":{"type":"constrained","contentSize":"1380px"}
  ```
- **In Blade Views & Tailwind Classes**: Section content wrappers MUST use:
  ```html
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
  ```
- **Prohibited**: NEVER use `max-w-4xl`, `max-w-5xl`, `max-w-6xl`, `max-w-7xl`, `1140px`, or `1200px` for main content wrappers.

### 2. Strict 1:1 Copy Fidelity (Zero Creative Deviation)
- **Strict Rule**: When given a target page, produce an **exact 1:1 visual and structural copy** using our design system tokens.
- **Do NOT Redesign**: Do not re-imagine the layout, omit elements, alter section ordering, change background colors, or replace bespoke components with generic equivalents.
- **Inspect Production CSS**: Always fetch the production page's compiled stylesheet (`post-<id>.css`) to inspect the exact background colors, gradients, background images, borders, and paddings.
- **Replicate All Elements**: Every eyebrow pill, trust counter, 5-star rating, checklist item, comparison card, badge graphic, video modal, and form field must be present in the exact relative arrangement.
- **Extend Design System Tokens**: Map all styles to theme tokens (`brand-midnight`, `brand-hero`, `brand-purple`, `bg-light`, `rounded-card`, etc.). If a token does not exist for an exact production value (e.g. a specific radial gradient, overlay image, or card border), **extend the design system tokens** in `theme.json` or `resources/css/app.css` rather than altering the design.

### 3. Zero "Unexpected or Invalid Content" Errors (Strict Gutenberg Block Grammar)
- **Strict Rule**: Patterns and pages MUST NEVER trigger Gutenberg's `"Block contains unexpected or invalid content. [Attempt recovery]"` modal in the block editor.
- **Why This Error Occurs**:
  - Gutenberg validates blocks upon loading a post by comparing the saved HTML in `post_content` against the block's JavaScript `save({ attributes })` output.
  - Core container blocks (`core/columns`, `core/column`, `core/group`) use `<InnerBlocks.Content />`. They expect **only valid Gutenberg block comments** (`<!-- wp:... -->`) as their inner content.
  - When arbitrary raw HTML tags (e.g. `<div class="pill">`, `<div class="grid">`, `<div class="card">`, raw `<h1>`, or un-bracketed SVG markup) are injected directly inside `<!-- wp:column -->` or `<!-- wp:group -->`, Gutenberg parses them as unrecognized "freeform" content chunks.
  - Furthermore, wrapping a child block in raw HTML (e.g. `<div class="my-wrapper"><!-- wp:acf/booking /--></div>`) corrupts the block tree because Gutenberg cannot associate the wrapping DOM nodes with any registered block.
  - The validation algorithm fails, and Gutenberg replaces the block with the dreaded:
    `"Block contains unexpected or invalid content. [Attempt recovery]"`
- **The Core Commandments**:
  1. **ACF Block First for Bespoke/Complex Sections (MANDATORY)**:
     - Whenever a section contains split columns with custom styling, trust pills, checklist grids, bespoke cards, or embedded Livewire/Alpine components (e.g. Split Hero, Comparison Matrix, 3-Step Process, Guarantee with Overlapping Badges), **DO NOT stitch it together using raw HTML inside `core/columns` or `core/column`**.
     - **Build a dedicated code-first ACF Block** (`Log1x\AcfComposer\Block`) with a Blade template (`resources/views/blocks/*.blade.php`).
     - Blade gives 100% control over Tailwind CSS v4 classes, semantic HTML, Alpine.js, and Livewire without any Gutenberg block validation restrictions.
     - Gutenberg stores a single server-rendered comment:
       ```html
       <!-- wp:acf/section-slug {"name":"acf/section-slug","data":{...},"mode":"preview"} /-->
       ```
     - **ACF Blocks NEVER fail Gutenberg client-side block validation.**
  2. **If Core Blocks Are Used, Follow Strict Grammar**:
     - EVERY visual element MUST be a native block comment:
       - Headings: `<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">...</h2><!-- /wp:heading -->`
       - Paragraphs: `<!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->`
       - Containers: `<!-- wp:group {"layout":{"type":"constrained","contentSize":"1380px"}} --><div class="wp-block-group">...</div><!-- /wp:group -->`
       - Custom raw HTML: MUST be wrapped in `<!-- wp:html --><div>...</div><!-- /wp:html -->`. NEVER leave raw HTML outside a block comment inside a container block.
     - NEVER wrap a block comment inside an arbitrary unclosed or closed HTML `<div>` tag.
  3. **Automated Validation in QA**:
     - Run the `parse_blocks()` validation audit before reporting completion. Any block with `blockName === null` containing non-whitespace `innerHTML` inside a container block is an error that MUST be eliminated before delivery.

### 4. Typography & Font Weights: Bold is the Maximum (NEVER Use Extra Bold or Black)
- **Strict Rule**: NEVER use text extra bold (`font-extrabold`, `font-black`, or `font-weight: 800/900`). **Bold (`font-bold` / 700) is the absolute maximum weight you will ever use**.
- **Headings & Hero Titles**: Always use `font-bold` (or `font-semibold`), never `font-extrabold` or `font-black`.
- **Badges, Pills, Buttons, Counters**: Use `font-bold` or `font-semibold` or `font-medium`.
- **Prohibited Classes**: `font-extrabold`, `font-black`, `font-[800]`, `font-[900]`.

### 5. The 6 Structural Invariants (Never Flatten, Homogenize, or Invert)
Never approximate a bespoke page with generic components. Every section must be verified against these 6 invariants before and during implementation:
1. **Background Theme Invariant (Dark vs Light)**:
   - Always check the production computed background. If the original is dark (`#250D4A`, deep gradient, midnight purple), the migrated section MUST be dark with white typography and appropriately themed inputs/pills.
   - **NEVER** default a dark hero or dark section to `bg-bg-light` or white!
2. **Layout Topology Invariant (Masonry & Bento Stacking vs Nested Splitting)**:
   - Identify whether the layout is a true vertical column-stack masonry versus a CSS subgrid or uniform table.
   - In a 3-column masonry grid (`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`), each column is an independent vertical flex container (`flex flex-col gap-6`). Cards inside each column span the **full column width** — do **NOT** subdivide a column into mini horizontal `grid-cols-2` subgrids unless explicitly shown in the original screenshot.
   - **Counter-Balanced Bento Heights**:
     - Column 1: **Tall card on top** (e.g. Administrative dark card with cutout woman), followed by **2 medium cards** vertically stacked below (e.g. Marketing lavender, Graphic Design soft blue).
     - Column 2: **2 medium cards on top** vertically stacked (e.g. Lead Gen lavender, Sales SDR soft blue), followed by **Tall card at bottom** (e.g. Customer Support dark card with cutout man).
     - Column 3: **Medium card on top** (e.g. Social Media), followed by **Tall card at bottom** (e.g. Custom Role with orbital avatar network).
   - **Card Background & Inversion Fidelity**:
     - Inspect each individual card's background. For example, in the Roles section, Custom Role is an all-**white** card (`bg-white`), NOT dark purple.
     - Replicate the exact pastel tints (`#F2EDF9` lavender, `#E1ECF7` soft blue, `#250D4A` dark purple, `#FFFFFF` white).
3. **Container Boundary Invariant (Single Unified Card vs Multi-Box)**:
   - Check whether comparison sides (e.g., "Hiring on your own" vs "Hiring with Remote Leverage") or grouped content live inside **one single enclosing container card** or separate detached cards.
   - If the original encloses them inside a single outer card with an arrow/divider, build it as **one unified container**.
4. **Bleed & Attachment Invariant (Zero-Padding Edges)**:
   - Check if graphics (medal ribbons, globe illustrations, candidate cutouts) bleed directly off the top, bottom, or side edges of their container.
   - **NEVER** float an edge-bleeding graphic inside internal padding. Ribbon graphics must be stuck flush to the top edge (`pt-0`, negative margin, or absolute top pinning).
5. **Surface Material Invariant (Frosted Glass vs Solid White)**:
   - Check if cards or form containers use glassmorphism (`backdrop-blur`, semi-transparent background e.g. `bg-white/10`, translucent border `border-white/20`, dark glass inputs).
   - **NEVER** replace a glassmorphic container or glass lead form with a flat opaque white card.
6. **Card Asymmetry & Tint Invariant**:
   - Replicate individual card tinting (e.g. lavender, soft blue, deep purple) and cutouts. Do not homogenize distinct cards into identical plain white boxes.

---

## Workflow Phases

When given a production URL, execute these 8 phases systematically:

```
0. Visual Ingestion ──> 1. Spec Table ──> 2. Media Sideload ──> 3. Block Architecture ──> 4. Defaults & Patterns ──> 5. Testing Page ──> 6. Code Validation ──> 7. Side-by-Side Visual Diff
```

---

### Phase 0: Visual Ingestion & Ground Truth Capture (MANDATORY FIRST STEP)

DO NOT start by scraping text or parsing DOM trees. DOM scrapes are visually blind and cause catastrophic layout and theme errors.

1. **Capture Desktop Screenshots of EVERY Section**:
   - Use `browser_subagent` to navigate to the production URL.
   - Scroll section-by-section and capture high-resolution screenshots of every distinct section (Hero, Logobar, Roles, Why Hire, Comparison, Guarantee, Process, FAQ, Footer/Lead Form).
   - Save these screenshot artifacts for direct visual reference throughout development.
2. **Inspect Computed Styles via CSS**:
   - Inspect the production stylesheet (`post-<id>.css`) for exact background colors, gradients (`radial-gradient(...)`), border-radii, card colors, and paddings.

---

### Phase 1: Section Dissection & Visual Spec Table

Before writing any Blade template or registering any block, compile and print the **Visual Spec Table**:

| Section Name | Theme (Dark/Light) | Background (Hex/Gradient) | Layout Topology | Container Boundary | Bleed Graphics | Surface Style |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| *e.g. Hero* | *Dark* | *#250D4A gradient* | *Split: Text L / Card R* | *Full width canvas* | *Talent marquee backdrop* | *Dark canvas, white text* |
| *e.g. Roles* | *Light* | *#F9F9FB* | *3-Col Asymmetric Bento* | *Separate tinted cards* | *Cutout persons* | *Tinted lavender/blue/purple* |
| *e.g. Why Hire*| *Split* | *#250D4A / #F9F9FB* | *Asymmetric Split* | *Large card L, Stack R*| *Globe bleeding off bottom* | *Dark card L, Soft cards R* |
| *e.g. Headache*| *Light* | *#F9F9FB* | *Side-by-side comparison* | ***Single unified white card***| *Center pink arrow* | *Solid white card, border* |
| *e.g. Guarantee*|*Light* | *#FFFFFF* | *2-Col Split* | *Single outer card* | ***Medals flush to top edge (pt-0)***| *Solid white, soft shadow* |
| *e.g. Footer* | *Dark* | *Deep purple gradient* | *Split: Text L / Form R* | *Glass container* | *N/A* | ***Frosted glass (backdrop-blur)*** |

---

### Phase 2: Bespoke Block Modeling (No Sloppy Slotting)

- **Do NOT shoehorn content into generic theme blocks** if the visual topology does not match 100%.
- If a section has a bespoke layout (e.g. Bento grid, Asymmetric split with bleeding globe, Unified single-box comparison, Frosted glass footer card), **create a dedicated code-first ACF Block** (`Log1x\AcfComposer\Block`) with a dedicated Blade template.
- Blade allows full expression of Tailwind CSS v4, custom grid spans, exact background tints, and image positioning without fighting Gutenberg's block validation.

---

### Phase 3: Media Sideloading & Attachment Mapping

All demo images in ACF blocks MUST exist in the WordPress Media Library as real attachments.

1. **Import Images**:
   Run a WP-CLI snippet to import and attach the images:
   ```bash
   wp eval '
   require_once(ABSPATH . "wp-admin/includes/image.php");
   require_once(ABSPATH . "wp-admin/includes/file.php");
   require_once(ABSPATH . "wp-admin/includes/media.php");
   $files = glob("public/images/<page-slug>/*.{webp,png,jpg}", GLOB_BRACE);
   foreach ($files as $file) {
       $tmp = wp_tempnam(basename($file));
       copy($file, $tmp);
       media_handle_sideload(["name" => basename($file), "tmp_name" => $tmp], 0);
   }
   '
   ```
2. **Attachment Lookup**:
   `BlockDefaults::getAttachmentId($value)` will automatically map the filename to its attachment ID in the database.

---

### Phase 4: Block Creation Standards (Log1x AcfComposer)

If a section requires a brand new block, follow this exact pattern:

1. **Class**: `app/Blocks/<Name>Block.php`
   - Inherits `Log1x\AcfComposer\Block`.
   - In `fields()`, always specify `'return_format' => 'url'` for image fields:
     ```php
     ->addImage('img', ['label' => 'Image', 'return_format' => 'url'])
     ```
   - In data retrieval methods (`with()` or helper methods):
     - Check `get_field(...)`.
     - Fall back to `BlockDefaults::<method>()`.
     - Run all strings through `BlockDefaults::cleanText()`.
     - Run all images through `BlockDefaults::resolveImageUrl()`.
2. **View**: `resources/views/blocks/<slug>.blade.php`
   - Use Tailwind CSS v4 design tokens (`rounded-card`, `p-card`, `border-black/4`, `shadow-[0_4px_24px_rgba(0,0,0,0.03)]`).
   - Use semantic tags (`h2`, `h3`, `p`, `img` with `loading="lazy" decoding="async" width height`).
   - Use Alpine.js for interactive toggles or modals.

---

### Phase 5: Block Defaults & Pre-Populated Patterns

Never leave block comment attributes empty (`"data": {}` or `"data": []`). Empty data forces editors to wipe out default content when clicking "Add Row".

1. **In `app/Support/BlockDefaults.php`**:
   - Add the default items array for the new block.
   - Add a render method:
     ```php
     public static function renderMySection(array $overrides = []): string
     {
         $data = [];
         self::encodeRepeater('cards', 'field_my_section_block_cards', self::mySectionCards(), $data);
         return self::patternBlock('my-section', array_merge($data, $overrides));
     }
     ```
   - Register the field in `filterLoadValue()` for `acf/load_value` so standalone insertions also load default rows.
2. **In `patterns/<section-slug>.php`**:
   - Create the pattern file with standard WordPress header.
   - Embed the pre-populated block:
     ```php
     <?php
     /**
      * Title: My Section
      * Slug: remote-leverage/my-section
      * Categories: remote-leverage
      */
     ?>
     <!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"5rem","bottom":"6rem"}}},"backgroundColor":"bg-light","layout":{"type":"constrained","contentSize":"1380px"}} -->
     <div class="wp-block-group alignfull has-bg-light-background-color has-background" style="padding-top:5rem;padding-bottom:6rem">
         <?= \App\Support\BlockDefaults::renderMySection() ?>
     </div>
     <!-- /wp:group -->
     ```
   - **CRITICAL**: Always ensure `<div class="wp-block-group...">` is present inside `wp:group` to prevent Gutenberg block invalidation errors.

---

### Phase 6: Testing Page Creation & Database Synchronization

1. **Create the Testing Page**:
   ```bash
   wp post create --post_type=page --post_title="<Page Title> [Modern Preview]" --post_name="<slug>-preview" --post_status=publish
   ```
2. **Assemble Pattern Content**:
   Concatenate all section patterns into a single string.
3. **Update with `wp_slash`**:
   **MANDATORY**: Always wrap the post content in `wp_slash()`:
   ```bash
   wp eval '
   $slugs = ["remote-leverage/hero", "remote-leverage/client-logos", ...];
   $content = "";
   foreach ($slugs as $s) {
       $p = \WP_Block_Patterns_Registry::get_instance()->get_registered($s);
       if ($p) $content .= trim($p["content"]) . "\n\n";
   }
   wp_update_post([
       "ID" => $TEST_PAGE_ID,
       "post_content" => wp_slash($content),
   ]);
   '
   ```
   *Why*: Without `wp_slash()`, WordPress's internal `wp_unslash()` strips all backslashes from JSON block comments, converting `\u003c` to `u003c` and corrupting HTML into raw text!

---

### Phase 7: Verification & QA Checklist

Execute these 4 automated tests before reporting the URL to the user:

1. **Pattern Registry Check**:
   ```bash
   wp eval '
   $patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();
   foreach ($patterns as $p) {
       if (str_starts_with($p["name"], "remote-leverage/")) echo $p["name"] . " OK\n";
   }
   '
   ```
2. **Block Hierarchy & Validation Audit**:
   ```bash
   wp eval '
   $blocks = parse_blocks(get_post($PAGE_ID)->post_content);
   function audit($b) {
       foreach ($b as $x) {
           if (($x["blockName"] ?? "") === null && trim($x["innerHTML"] ?? "") !== "") {
               echo "ERROR: Orphan HTML chunk: " . trim($x["innerHTML"]) . "\n";
           }
           if (!empty($x["innerBlocks"])) audit($x["innerBlocks"]);
       }
   }
   audit($blocks);
   '
   ```
3. **Unicode & Image Integrity Check**:
   ```bash
   wp eval '
   $html = apply_filters("the_content", get_post($PAGE_ID)->post_content);
   if (preg_match("/u00[0-9a-f]{2}/i", $html, $m)) echo "ERROR: Corrupted unicode: " . $m[0] . "\n";
   else echo "Unicode: CLEAN\n";
   preg_match_all("/<img[^>]+src=[\x27\"]([^\x27\"]*)[\x27\"]/", $html, $m);
   $empty = 0; foreach ($m[1] as $s) if (empty($s)) $empty++;
   echo "Empty img srcs: $empty / " . count($m[1]) . "\n";
   '
   ```
4. **Compile Assets**:
   ```bash
   npm run build
   ```
5. **Mandatory Side-by-Side Visual Diff QA (NO SIGNOFF WITHOUT THIS)**:
   - Use `browser_subagent` to capture full-resolution screenshots of each section on the local preview URL (`http://remoteleverage-v2.test/<slug>-preview/`).
   - Visually compare every local screenshot against the corresponding production screenshot captured in Phase 0.
   - Explicitly verify the **6 Invariants** for each section:
     1. **Theme Match**: Is the background dark or light matching the original?
     2. **Topology Match**: Is the Bento grid intact with correct row/col spans and card heights?
     3. **Container Match**: Are comparison lists or side-by-side modules in a single unified box?
     4. **Bleed Match**: Are medals ribbons, globes, or cutouts flush to container edges without unwanted padding?
     5. **Surface Match**: Is glassmorphism (`backdrop-blur`, translucent border, dark inputs) properly applied?
     6. **Weight Match**: Are all headings and text constrained to `font-bold` (700 max)?
   - If any section fails any of these 6 checks, it is an automatic rejection that MUST be corrected before reporting to the user.

---

## Troubleshooting Common Gotchas

- **Problem**: In Gutenberg, the block says "Block contains unexpected or invalid content. Attempt recovery".
  - **Solution**: The outer `wp:group` is missing its opening `<div class="wp-block-group ...">` or closing `</div>`. Ensure every opening Gutenberg block comment has a matching HTML tag.
- **Problem**: Text shows `u0026amp;`, `u003cbru003e`, or `u003cpu003e`.
  - **Solution**: The post was saved without `wp_slash()`. Call `wp_update_post(['ID' => $id, 'post_content' => wp_slash($content)])` and ensure `BlockDefaults::cleanText()` is applied in block classes.
- **Problem**: Image is missing in the block on the frontend, or ACF inspector shows no image thumbnail.
  - **Solution**: The image is a raw URL string instead of an Attachment ID. Sideload the image into the Media Library with `media_handle_sideload()` and use `BlockDefaults::getAttachmentId()` to provide the integer ID.
