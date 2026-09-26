{{-- About page long-form narrative: a prefixed section title, a lead statement, then two columns
     of rich prose (hidden below `md`/768px — mobile only; visible from tablet up), matching
     legacy's `elementor-hidden-mobile` on this same row. A mobile-only "Read More" toggle (Alpine)
     reveals it below `md`, mirroring legacy's `readmore_btn`. The badge stays visible at every
     breakpoint, in both modes. Text only, no imagery.

     The `block_type` ACF field (AboutNarrativeBlock::fields()) switches title_prefix, title,
     lead_statement AND the two detail columns between two authoring modes — same mechanism as
     AboutHeroBlock:

     "acf" (default): plain text/textarea ACF fields rendered as static markup, exactly as before
     this toggle existed. The badge sits beside the (short) title in a flex row.

     "inner_blocks": edited in place as native Gutenberg blocks — title, heading, lead paragraph
     AND a nested `core/columns` all live in the SAME InnerBlocks region, since a block only gets
     one (acf-hero-migration skill §2). Because that region can now grow much taller than the
     short title/prefix pair the `acf` branch has, the badge is NOT a flex sibling here (it would
     end up stranded at the top of a much taller row) — it renders full-width, right-aligned,
     above the InnerBlocks content instead. This is a deliberate, locally-unverified layout choice
     for this mode only; the `acf` branch's badge position is unchanged. `jsx` support on the
     block (AboutNarrativeBlock::$supports) is what makes ACF hydrate the `<InnerBlocks />` tag
     into a real block area; `template`/`allowedBlocks` are plain HTML attributes whose value is
     JSON built in AboutNarrativeBlock::with() (`wp_json_encode()`, escaped here like any other
     Blade variable) — NOT JSX/JS object-literal syntax typed inline, which ACF does not parse and
     simply prints as literal text.

     The badge (with its swirl icons) is a fixed composite the single InnerBlocks region can't
     reach, per this repo's acf-hero-migration skill — it stays a plain ACF field, always, in
     both modes (extracted to blocks.partials.about-narrative-badge so both branches share it). --}}
<div class="w-full px-4 sm:px-6 lg:px-8">
    @if ($blockType === 'acf')
        {{-- Top Header Row --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-6">
            <div>
                @if (! empty($titlePrefix))
                    <span class="block font-display text-2xl sm:text-3xl font-bold text-black tracking-tight mb-1">
                        {{ $titlePrefix }}
                    </span>
                @endif
                @if (! empty($title))
                    <h2 class="font-display text-3xl sm:text-4xl lg:text-[44px] font-bold text-black tracking-[-0.03em] leading-tight">
                        {{ $title }}
                    </h2>
                @endif
            </div>

            <div class="self-start sm:self-center shrink-0">
                @include('blocks.partials.about-narrative-badge')
            </div>
        </div>

        {{-- Large Statement Text --}}
        @if (! empty($leadStatement))
            <div class="font-display text-2xl sm:text-3xl lg:text-[32px] font-bold text-black tracking-[-0.02em] leading-snug my-8 sm:my-10">
                {!! $leadStatement !!}
            </div>
        @endif

        {{-- 2-Column Detail Paragraphs — hidden on mobile by default (matches legacy's
             `elementor-hidden-mobile` on this same row), visible from tablet (md) up. On mobile,
             a "Read More" toggle (mirroring legacy's `readmore_btn`, itself
             `elementor-hidden-desktop elementor-hidden-laptop elementor-hidden-tablet`) reveals
             the SAME content rather than duplicating it, so there's still a way to read it below
             md.

             `x-show` + a literal `style="display:none;"` fallback, same as the FAQ accordion
             (resources/views/blocks/accordion-faq.blade.php) — not a `:class` ternary. This
             theme boots Alpine lazily (IntersectionObserver-gated, several seconds after first
             paint by design, see resources/js/app.js's scheduleLivewire()), so a `:class` binding
             that only ever adds "hidden" OR "grid" leaves the element with NEITHER class present
             until Alpine hydrates — on mobile that default-renders as a plain block (visible),
             which is exactly the content this toggle exists to keep hidden until asked for. The
             static inline `display:none` is correct from first paint, no JS required. `md:grid!`
             (`!important`) still forces it visible at md+ regardless of `narrativeExpanded` or
             hydration timing: an `!important` stylesheet rule beats a plain inline style. --}}
        <div x-data="{ narrativeExpanded: false }">
            <div
                x-show="narrativeExpanded"
                style="display:none;"
                class="md:grid! md:grid-cols-2 md:gap-8 lg:gap-14 text-base sm:text-lg text-black/75 leading-relaxed"
            >
                @if (! empty($colLeft))
                    <div>
                        {!! nl2br($colLeft) !!}
                    </div>
                @endif

                @if (! empty($colRight))
                    <div>
                        {!! nl2br($colRight) !!}
                    </div>
                @endif
            </div>

            <button
                type="button"
                class="md:hidden mt-4 text-sm font-bold uppercase tracking-wide text-black underline underline-offset-4"
                @click="narrativeExpanded = ! narrativeExpanded"
                :aria-expanded="narrativeExpanded ? 'true' : 'false'"
            >
                <span x-text="narrativeExpanded ? 'Show Less' : 'Read More'"></span>
            </button>
        </div>
    @else
        <div class="pb-6">
            <div class="flex justify-end mb-6">
                @include('blocks.partials.about-narrative-badge')
            </div>

            {{-- Same mobile "Read More" as the `acf` branch, but the columns are inside
                 InnerBlocks-rendered markup we don't control node-by-node, so this targets the
                 nested `.wp-block-columns` by descendant selector instead of toggling a div we
                 own directly. `max-md:` scopes the expand override to strictly below `md`, so it
                 can never out-specificity the `md:[&_.wp-block-columns]:grid!` rule below even if
                 `narrativeExpanded` is still true after a resize to desktop (both being
                 `!important`, the more specific selector would otherwise win regardless of
                 breakpoint). More speculative than the `acf` branch: unverified against real
                 saved InnerBlocks markup, only against the seeded default template. --}}
            <div
                x-data="{ narrativeExpanded: false }"
                :class="{ 'is-expanded': narrativeExpanded }"
                class="[&_.wp-block-columns]:hidden max-md:[&.is-expanded_.wp-block-columns]:grid! md:[&_.wp-block-columns]:grid!"
            >
                <InnerBlocks
                    template="{{ $contentTemplate }}"
                    allowedBlocks="{{ $contentAllowedBlocks }}"
                    templateLock="all"
                />

                <button
                    type="button"
                    class="md:hidden mt-4 text-sm font-bold uppercase tracking-wide text-black underline underline-offset-4"
                    @click="narrativeExpanded = ! narrativeExpanded"
                    :aria-expanded="narrativeExpanded ? 'true' : 'false'"
                >
                    <span x-text="narrativeExpanded ? 'Show Less' : 'Read More'"></span>
                </button>
            </div>
        </div>
    @endif
</div>
