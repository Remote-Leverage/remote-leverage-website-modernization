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

## Workflow Phases

When given a production URL, execute these 7 phases systematically:

```
1. Reconnaissance ──> 2. Dissection ──> 3. Media Sideload ──> 4. Block Composer ──> 5. Patterns & Defaults ──> 6. Testing Page ──> 7. QA Check
```

---

### Phase 1: Intake & Reconnaissance

1. **Extract Production Content**:
   - Use `read_url_content` or `browser_subagent` to fetch the complete text, headings, and images of the target URL.
   - If the page has interactive elements (calendars, sliders, accordions), inspect their HTML structure and underlying data.
2. **Download Page Images**:
   - Save production images to `public/images/<page-slug>/`.
   - Maintain modern web formats (`.webp`, `.png`, `.svg`).

---

### Phase 2: Section Dissection & Block Mapping

Dissect the page into modular sections and map each to the design system:

| Section Type | Common Elements | Existing Block / Pattern to Reuse | Action if Missing |
| :--- | :--- | :--- | :--- |
| **Hero** | H1, Subhead, CTA button, visual marquee | `remote-leverage/hero`<br>`acf/talent-marquee` | Create new hero block if layout differs |
| **Social Proof Ticker** | Client / partner logos in infinite marquee | `remote-leverage/client-logos`<br>`acf/client-logos-marquee` | Reuse with updated logo array |
| **Feature Grid (3-4 Col)** | 3 or 4 benefit cards with title, text, image | `remote-leverage/worlds-best-talent`<br>`acf/feature-cards` (`columns: '3'|'4'`) | Reuse with custom card presets |
| **Department / Role Cards** | Specialty cards with photo background & blur overlay | `remote-leverage/beyond-virtual-assistant`<br>`acf/department-cards` | Reuse with new role data |
| **Trust & Impact Stats** | Placed counter, country count, economic impact | `remote-leverage/trust-and-impact`<br>`acf/trust-stats` | Reuse or adapt fields |
| **Comparison Matrix** | DIY vs Remote Leverage comparison rows | `remote-leverage/why-companies-choose`<br>`acf/data-table` | Reuse with new criterion rows |
| **Process Steps** | 3-step numbered sequence (`01`, `02`, `03`) | `remote-leverage/process-steps`<br>`acf/process-steps` | Reuse with step copy |
| **Guarantee Card** | 12-month replacement guarantee card | `remote-leverage/replacement-guarantee`<br>(Core blocks: `wp:group`, `wp:columns`) | Pure core Gutenberg block pattern |
| **Video Testimonials** | Client quotes, Vimeo modal trigger, duration badge | `remote-leverage/results-testimonials-faq`<br>`acf/testimonials` | Reuse with client video IDs & quotes |
| **Accordion FAQ** | Expandable Q&A items + Schema.org JSON-LD | `remote-leverage/results-testimonials-faq`<br>`acf/accordion-faq` | Reuse with page FAQ items |
| **Booking Footer / Funnel** | Multistep calendar qualification wizard | `remote-leverage/booking-footer`<br>`acf/booking` | Reuse embedded funnel |

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

---

## Troubleshooting Common Gotchas

- **Problem**: In Gutenberg, the block says "Block contains unexpected or invalid content. Attempt recovery".
  - **Solution**: The outer `wp:group` is missing its opening `<div class="wp-block-group ...">` or closing `</div>`. Ensure every opening Gutenberg block comment has a matching HTML tag.
- **Problem**: Text shows `u0026amp;`, `u003cbru003e`, or `u003cpu003e`.
  - **Solution**: The post was saved without `wp_slash()`. Call `wp_update_post(['ID' => $id, 'post_content' => wp_slash($content)])` and ensure `BlockDefaults::cleanText()` is applied in block classes.
- **Problem**: Image is missing in the block on the frontend, or ACF inspector shows no image thumbnail.
  - **Solution**: The image is a raw URL string instead of an Attachment ID. Sideload the image into the Media Library with `media_handle_sideload()` and use `BlockDefaults::getAttachmentId()` to provide the integer ID.
