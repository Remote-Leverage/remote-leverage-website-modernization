{{-- Roles grid: eyebrow, headline and a grid of role cards with photography, used to show what
     kinds of hire are available. --}}
@php
  use App\Support\BlockDefaults;

  // The eight cards are eight bespoke slots, not a loop: each has its own surface colour, its
  // own height and its own image treatment, and the mosaic only works because they differ.
  // What the slots are NOT is fixed content — the block has always declared a `cards` repeater,
  // and until 2026-09-16 the view ignored it and hard-coded all eight titles, so filling the
  // repeater did nothing. Slot order matches BlockDefaults::rolesGridCards(), which carries the
  // same copy the view used to hold, so every page already shipping this block is unchanged.
  //
  //   0 tall purple photo card   3 social media        6 customer support (tall, man cutout)
  //   1 lead generation          4 marketing           7 custom role
  //   2 blue horizontal card     5 graphic design
  //
  // The 2026 homepage swaps slots 0 and 2 to put Sales (SDR) on the photo card; see
  // patterns/homepage-roles.php.
  $cards = array_values(is_array($cards ?? null) && $cards !== [] ? $cards : BlockDefaults::rolesGridCards());
  $slot = fn (int $i, string $key, string $fallback = '') => $cards[$i][$key] ?? $fallback;

  $eyebrowImg = BlockDefaults::hireVaImg('Group-207.png');

  // The eyebrow ships as one string in one field, but /hire-va-4/'s mobile comp stacks the
  // count above the label beside a verified badge. Both the badge and a pre-split copy of the
  // text are emitted `hidden`, so every page's rendered box is byte-identical to before and
  // the stacked treatment is opt-in from a block instance's own `rl_design_css` (see
  // patterns/hire-va-4-roles.php) rather than a second field every page would have to know
  // about. Splitting the live text node in place is not equivalent: it re-shapes the run and
  // moved glyphs by a sub-pixel on desktop.
  $eyebrowText = $eyebrow ?? '2.5K+ pre-vetted candidates';
  [$eyebrowCount, $eyebrowLabel] = array_pad(explode(' ', $eyebrowText, 2), 2, '');

  // Administrative card tint. 'dark' is production's treatment on every page that ships this
  // block today (/hire-va-4/, /hire-va-6/, /hire-va-1st-month-free/ all compute
  // rgb(99,65,162) = #6341A2 with white text, measured 2026-09-15), so it stays the default.
  // 'lavender' exists for a page that wants the card to read as one of the light ones.
  // Both class sets are spelled out as full literals — Tailwind scans source text and never
  // emits a class assembled by concatenation.
  $adminTint = ($adminTint ?? 'dark') === 'lavender' ? 'lavender' : 'dark';
  $adminSurface = $adminTint === 'lavender'
    ? 'bg-roles-lavender border-black/5 shadow-xs'
    : 'bg-roles-feature text-white border-white/10 shadow-sm';
  $adminTitle = $adminTint === 'lavender' ? 'text-brand-hero' : 'text-white';
  $adminBody = $adminTint === 'lavender' ? 'text-text-muted' : 'text-white/80';
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Headline & eyebrow. Production centres the header and puts the pill BELOW the heading
         (measured on /hire-va-4/, /hire-va-6/ and /hire-va-1st-month-free/ 2026-09-15: h2
         text-align:center at x=390 w=660, pill 35px beneath it). --}}
    <div class="mx-auto max-w-4xl mb-12 sm:mb-16 text-center">
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight leading-[1.08]">
        {!! nl2br(e($headline ?? 'The Roles That Buy Back Your Time')) !!}
      </h2>

      <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-white border border-black/5 shadow-xs mt-8">
        <img {!! \App\Support\BlockDefaults::imageSizeAttrs($eyebrowImg) !!} src="{{ $eyebrowImg }}" alt="Candidate Avatars" class="h-6 w-auto" loading="lazy" decoding="async">
        {{-- Verified badge. `hidden` everywhere by default so no page's render moves; an
             instance that wants the comp's avatar cluster switches it on in `rl_design_css`. --}}
        <svg class="hidden shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
          <path fill="#0067FF" d="M22.25 12c0-1.43-.88-2.67-2.19-3.34.46-1.39.2-2.9-.81-3.91s-2.52-1.27-3.91-.81C14.67 2.63 13.43 1.75 12 1.75s-2.67.88-3.34 2.19c-1.39-.46-2.9-.2-3.91.81s-1.27 2.52-.81 3.91C2.63 9.33 1.75 10.57 1.75 12s.88 2.67 2.19 3.34c-.46 1.39-.2 2.9.81 3.91s2.52 1.27 3.91.81c.67 1.31 1.91 2.19 3.34 2.19s2.67-.88 3.34-2.19c1.39.46 2.9.2 3.91-.81s1.27-2.52.81-3.91c1.31-.67 2.19-1.91 2.19-3.34Z"/>
          <path fill="#fff" d="m10.75 16.6-3.6-3.6 1.45-1.45 2.15 2.15 4.65-4.65 1.45 1.45-6.1 6.1Z"/>
        </svg>
        <span class="text-xs sm:text-sm font-bold text-brand-hero tracking-wide">{{ $eyebrowText }}</span>
        {{-- Stacked alternative, `hidden` by default so the span above stays the one text node
             every page has always rendered — splitting that node in place moved sub-pixel glyph
             positions on desktop. An instance swaps the two over in `rl_design_css`. --}}
        <span class="hidden text-xs sm:text-sm font-bold text-brand-hero tracking-wide"><span>{{ $eyebrowCount }}</span><span>{{ $eyebrowLabel }}</span></span>
      </div>
    </div>

    {{-- True 3-Column Asymmetric Masonry Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
      
      {{-- COLUMN 1: Tall Admin Card Top + 2 Medium Cards Below --}}
      <div class="contents lg:flex lg:flex-col lg:gap-6">
        {{-- Card 1: Administrative (tall, woman cutout). Dark by default — that is what every
             production page shipping this block renders; `admin_tint` can flip it to lavender. --}}
        <div class="order-1 lg:order-none relative overflow-hidden {{ $adminSurface }} rounded-card p-6 sm:p-8 border flex flex-col justify-between min-h-[440px] group">
          <div>
            <h3 class="text-2xl sm:text-3xl font-bold font-display {{ $adminTitle }} tracking-tight">{{ $slot(0, 'title') }}</h3>
            <p class="text-sm sm:text-base {{ $adminBody }} mt-2 max-w-[280px] leading-relaxed">
              {{ $slot(0, 'desc') }}
            </p>
          </div>
          <div class="mt-6 flex justify-center -mb-8 pointer-events-none">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(0, 'img')) !!} src="{{ $slot(0, 'img') }}" alt="{{ $slot(0, 'title') }}" loading="lazy" decoding="async" class="h-64 sm:h-72 w-auto object-contain object-bottom transition-transform duration-500 group-hover:scale-105">
          </div>
        </div>

        {{-- Card 2: Marketing (Lavender, Horizontal Layout) --}}
        <div class="order-5 lg:order-none bg-roles-violet rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">{{ $slot(4, 'title') }}</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              {{ $slot(4, 'desc') }}
            </p>
          </div>
          <div class="shrink-0">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(4, 'img')) !!} src="{{ $slot(4, 'img') }}" alt="{{ $slot(4, 'title') }}" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 3: Graphic Design (Soft Blue, Horizontal Layout) --}}
        <div class="order-6 lg:order-none bg-roles-blue rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">{{ $slot(5, 'title') }}</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              {{ $slot(5, 'desc') }}
            </p>
          </div>
          <div class="shrink-0">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(5, 'img')) !!} src="{{ $slot(5, 'img') }}" alt="{{ $slot(5, 'title') }}" loading="lazy" decoding="async" class="w-24 sm:w-28 h-auto object-contain">
          </div>
        </div>
      </div>

      {{-- COLUMN 2: 2 Medium Cards Top + Tall Support Card Bottom --}}
      <div class="contents lg:flex lg:flex-col lg:gap-6">
        {{-- Card 4: Lead Generation (Lavender, Horizontal Layout) --}}
        <div class="order-2 lg:order-none bg-roles-lavender rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">{{ $slot(1, 'title') }}</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              {{ $slot(1, 'desc') }}
            </p>
          </div>
          <div class="shrink-0">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(1, 'img')) !!} src="{{ $slot(1, 'img') }}" alt="{{ $slot(1, 'title') }}" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 5: Sales (SDR) (Soft Blue, Horizontal Layout) --}}
        <div class="order-3 lg:order-none bg-roles-blue rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">{{ $slot(2, 'title') }}</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              {{ $slot(2, 'desc') }}
            </p>
          </div>
          <div class="shrink-0">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(2, 'img')) !!} src="{{ $slot(2, 'img') }}" alt="{{ $slot(2, 'title') }}" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 6: Customer Support (Tall, light lavender, man cutout) — light on production --}}
        <div class="order-7 lg:order-none relative overflow-hidden bg-roles-lavender rounded-card p-6 sm:p-8 border border-black/5 shadow-xs flex flex-col justify-between min-h-[440px] flex-1 group">
          <div>
            <h3 class="text-2xl sm:text-3xl font-bold font-display text-brand-hero tracking-tight">{{ $slot(6, 'title') }}</h3>
            <p class="text-sm sm:text-base text-text-muted mt-2 max-w-[280px] leading-relaxed">
              {{ $slot(6, 'desc') }}
            </p>
          </div>
          <div class="mt-6 flex justify-center -mb-8 pointer-events-none">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(6, 'img')) !!} src="{{ $slot(6, 'img') }}" alt="{{ $slot(6, 'title') }}" loading="lazy" decoding="async" class="h-64 sm:h-72 w-auto object-contain object-bottom transition-transform duration-500 group-hover:scale-105">
          </div>
        </div>
      </div>

      {{-- COLUMN 3: Social Media Top + Custom Role Bottom --}}
      <div class="contents lg:flex lg:flex-col lg:gap-6">
        {{-- Card 7: Social Media (Soft Grey/Blue Surface, Post Mockup) --}}
        <div class="order-4 lg:order-none bg-roles-sky rounded-card p-6 sm:p-8 border border-black/5 shadow-xs flex flex-col justify-between min-h-[360px]">
          <div>
            <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">{{ $slot(3, 'title') }}</h3>
            <p class="text-sm text-text-muted mt-2 leading-relaxed max-w-[300px]">
              {{ $slot(3, 'desc') }}
            </p>
          </div>
          <div class="mt-6 flex justify-center">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(3, 'img')) !!} src="{{ $slot(3, 'img') }}" alt="{{ $slot(3, 'title') }}" loading="lazy" decoding="async" class="max-h-52 w-auto object-contain">
          </div>
        </div>

        {{-- Card 8: Custom Role (Clean White Card, Orbital Graphic) --}}
        <div class="order-8 lg:order-none bg-white rounded-card p-6 sm:p-8 border border-black/5 shadow-xs flex flex-col justify-between min-h-[400px] flex-1">
          <div>
            <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">{{ $slot(7, 'title') }}</h3>
            <p class="text-sm text-text-muted mt-2 leading-relaxed max-w-[300px]">
              {{ $slot(7, 'desc') }}
            </p>
          </div>
          
          <div class="mt-6 flex justify-center items-center">
            <img {!! \App\Support\BlockDefaults::imageSizeAttrs($slot(7, 'img')) !!} src="{{ $slot(7, 'img') }}" alt="{{ $slot(7, 'title') }}" loading="lazy" decoding="async" class="w-full max-w-[280px] h-auto object-contain">
          </div>
        </div>
      </div>

    </div>

    {{-- Bottom consultation CTA. Production: #F90066 (--color-brand-magenta), 14px/700 uppercase,
         letter-spacing -0.45px, radius 100px, no shadow, href #booking-footer — measured on all
         three pages 2026-09-15. The label comes from the block's cta_text field, whose default
         is already production's "BOOK A FREE CONSULTATION"; the hard-coded string that used to
         live here is why the pages rendered the longer 15-minute wording. --}}
    <div class="mt-14 sm:mt-18 flex justify-center">
      @if (($ctaStyle ?? 'production') === 'pill')
        {{-- The 2026 homepage's pill: larger label, circled chevron, outline ring. Opt-in, so
             the production pages above keep the arrow they were measured against. --}}
        @include('blocks.partials.cta-pill', ['text' => $ctaText ?? 'BOOK A CONSULTATION', 'url' => $ctaUrl ?? '#booking-footer'])
      @else
        <a href="{{ $ctaUrl ?? '#booking-footer' }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-brand-magenta hover:bg-[#d40057] text-white font-bold text-sm tracking-[-0.45px] uppercase transition-all duration-200 hover:scale-[1.02]">
          <span>{{ $ctaText ?? 'BOOK A FREE CONSULTATION' }}</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </a>
      @endif
    </div>

  </div>
</section>
