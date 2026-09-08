# Gutenberg Block Grammar & Block Validation Rules

## Zero "Unexpected or Invalid Content" Errors

In WordPress Gutenberg, posts and patterns must **never** trigger the `"Block contains unexpected or invalid content. [Attempt recovery]"` modal.

### Root Cause
WordPress validates blocks on edit by comparing the saved HTML in `post_content` against the block's JavaScript `save({ attributes })` output.
Core container blocks (`core/columns`, `core/column`, `core/group`) use `<InnerBlocks.Content />` and only accept **valid Gutenberg block comments** (`<!-- wp:... -->`).
If arbitrary raw HTML tags (e.g. `<div class="badge">`, `<div class="grid">`, or wrapper `<div>`s around child blocks) are placed inside `<!-- wp:column -->` or `<!-- wp:group -->`, Gutenberg parses them as unrecognized orphan content and fails validation.

### Mandates:
1. **ACF Block First for Bespoke/Complex Layouts (MANDATORY)**:
   - When creating hero sections, split columns with pills/checklists, comparison tables, or sections embedding Livewire/Alpine components, **DO NOT stitch raw HTML into core `wp:columns`**.
   - Create a dedicated code-first ACF Block (`app/Blocks/*Block.php`) with a Blade template (`resources/views/blocks/*.blade.php`).
   - Gutenberg stores only `<!-- wp:acf/<slug> {...} /-->`. ACF blocks are dynamically rendered by the server and **NEVER** fail client-side block validation.

2. **Strict Block Grammar for Core Blocks**:
   - If using core blocks (`wp:group`, `wp:columns`, `wp:column`), every child must be a valid block comment (`wp:heading`, `wp:paragraph`, `wp:list`, `wp:group`, etc.).
   - If raw HTML snippets are strictly necessary, wrap them inside `<!-- wp:html --><div>...</div><!-- /wp:html -->`.
   - Never inject loose `<div>` tags directly inside `<!-- wp:column -->`.
   - Never wrap a Gutenberg block comment in an unclosed or ad-hoc raw HTML tag.

3. **Always Synchronize with `wp_slash()`**:
   - When updating `post_content` via WP-CLI or PHP, always pass content through `wp_slash($content)` to prevent backslash stripping (`\u003c` -> `u003c`).

4. **Container Width**:
   - The canonical container width is always **1380px** (`"contentSize":"1380px"` / `max-w-[1380px] mx-auto`).
