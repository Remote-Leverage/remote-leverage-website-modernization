{{-- Replacement-guarantee panel: guarantee terms on the left, supporting imagery and reassurance
     points on the right. --}}
@php
  use App\Support\BlockDefaults;

  $badgeImg = BlockDefaults::hireVaImg('Group-59-1-e1780958571501.png');
  $icon1 = BlockDefaults::hireVaImg('stash_arrows-switch.svg');
  $icon2 = BlockDefaults::hireVaImg('majesticons_file-line-3.svg');
  $icon3 = BlockDefaults::hireVaImg('material-symbols_person-check-rounded.svg');
  $slotIcons = [$icon1, $icon2, $icon3];
@endphp

@php
  // Production runs this band two ways: the radial purple wash on the hire-va pages, and a
  // flat #250D4A on /ecommerce-virtual-assistant/. `background` picks between them.
  $bg = ($background ?? 'radial-purple') === 'flat-midnight'
      ? 'background: #250D4A;'
      : 'background: radial-gradient(84.9% 75.5% at 65.45% 17.36%, #8A2BE2 0%, #250D4A 100%);';
  // Prose copy (the comparison pages) takes the place of the icon trio.
  $prose = trim($body ?? '');
  $showReassurance = $prose === '' && (! isset($showReassuranceItems) || $showReassuranceItems);

  // Production sets this heading 42px on every page that carries the band, and holds it
  // to a narrow measure so it wraps: 424px on the radial-purple pages, 326px on the flat
  // #250D4A one. Measured 2026-09-15 across hire-va-4, hire-for-less, the three comparison
  // pages and /ecommerce-virtual-assistant/.
  $headingMeasure = ($background ?? 'radial-purple') === 'flat-midnight'
      ? 'lg:max-w-[326px]'
      : 'lg:max-w-[424px]';
@endphp

<section class="pt-12 sm:pt-16 lg:pt-20 pb-16 sm:pb-20 lg:pb-24 text-white relative overflow-hidden" style="{{ $bg }}">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">
      
      {{-- Left Column: Guarantee Details. Ordered after the badge below lg — see the badge's
           own note for why. --}}
      <div class="order-2 lg:order-none lg:col-span-7 pt-4 sm:pt-6">
        <h2 class="text-3xl sm:text-4xl lg:text-[42px] lg:leading-[48px] font-bold font-display text-white tracking-tight leading-[1.1] mb-10 {{ $headingMeasure }}">
          {{ $headline }}
        </h2>

        @if ($prose !== '')
        {{-- space-y gives the 20px inter-paragraph gap production sets; don't add a
             [&_p]:mb-0 reset here, it outranks space-y's :where() rule and closes it. --}}
        <div class="max-w-2xl mb-10 space-y-5 text-white/80 text-sm sm:text-base leading-relaxed [&_strong]:text-white [&_a]:underline">
          {!! $prose !!}
        </div>
        @endif

        {{-- Reassurance items. /ecommerce-virtual-assistant/ shows the badge image alone,
             so this trio is switchable rather than always-on.

             The copy is data rather than three hard-coded blocks: the 2026 homepage states the
             guarantee as a flat 12 months where production qualifies it as "6 months, extended
             to 12", and a claim about the guarantee's own terms should not need a template edit
             to differ between pages. Icons stay positional — they illustrate the slot, not the
             sentence, and an editor should not have to supply one to reword a line. --}}
        @if ($showReassurance)
        <div class="space-y-7 mb-10">
          @foreach ($reassuranceItems as $i => $item)
            <div class="flex items-start gap-4">
              <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                <img src="{{ $slotIcons[$i % count($slotIcons)] }}" alt="" class="w-6 h-6 object-contain">
              </div>
              <div>
                <h3 @class(['text-lg sm:text-xl font-bold text-white', 'mb-1' => ! empty($item['text'])])>
                  {{ $item['title'] }}
                </h3>
                @if (! empty($item['text']))
                  <p class="text-white/80 text-sm sm:text-base leading-relaxed">
                    {{ $item['text'] }}
                  </p>
                @endif
              </div>
            </div>
          @endforeach
        </div>
        @endif

        {{-- CTA Button --}}
        <div>
          @if (($ctaStyle ?? 'production') === 'pill')
            {{-- The homepage comp puts the shared magenta pill here; production's is purple. --}}
            @include('blocks.partials.cta-pill', ['text' => $ctaText, 'url' => $ctaUrl])
          @else
            <a href="{{ $ctaUrl }}"
               class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#8A2BE2] hover:bg-[#7b20d4] text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.5)] transition-all duration-300 hover:scale-[1.02]">
              <span>{{ $ctaText }}</span>
              <svg class="w-4 h-4 fill-none stroke-current" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
              </svg>
            </a>
          @endif
        </div>
      </div>

      {{-- Right Column: 3D Medals Badge FLUSH to the bleeding top edge.
           `order-1` below lg puts the badge above the copy when the grid collapses to one
           column. The negative top margin exists to bleed the medals off the band's top edge,
           which only works while the badge is the first thing in the column — stacked last it
           dragged the ribbons up through the CTA instead, clipping the button on every page
           shipping this block (verified on /hire-va-4/ at 376px, not just the homepage). This
           is also what the 2026 mobile comp draws: medals bleeding in at the top, heading
           beneath them. --}}
      <div class="order-1 lg:order-none lg:col-span-5 flex justify-center lg:justify-end -mt-12 sm:-mt-16 lg:-mt-20 pointer-events-none">
        <img src="{{ $badgeImg }}"
             alt="12-Month Replacement Guarantee Medals"
             width="578"
             height="545"
             loading="lazy"
             decoding="async"
             class="w-full max-w-sm sm:max-w-md lg:max-w-lg h-auto object-contain object-top drop-shadow-[0_24px_50px_rgba(0,0,0,0.4)]">
      </div>

    </div>
  </div>
</section>
