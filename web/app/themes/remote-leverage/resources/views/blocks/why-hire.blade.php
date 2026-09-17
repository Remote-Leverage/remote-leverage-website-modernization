{{-- 'Why hire through us' band: section heading over four icon cards on a pale surface, with a
     globe graphic.

     `layout` picks the arrangement. 'split' is production and the 2026 homepage and stays the
     default. 'banner' is the role pages (/admin-virtual-assistants/ and its siblings): the proof
     card runs the full width with the globe in its own half, and the four cards fall into a 2x2
     grid beneath it.

     Colour on the banner arrangement was read off 01_Administrative_VAs.webp by sampling rather
     than estimated: the proof ground is a pure vertical ramp (rows are flat to within 2 units
     across all 945px of its width), the CTA is exactly --color-brand-magenta, and the icon tile
     is --color-lavender-surface. --}}
@php
  use App\Support\BlockDefaults;

  // Two knobs for the 2026 homepage, both defaulting to what /hire-va-4/, /hire-va-6/ and
  // /reviews/ already render. The comp centres the heading and strips the proof card back to
  // bare stars over the quote — no "5.0 Star Rating" pill, no closing paragraph.
  $isCentred = ($headingAlign ?? 'left') === 'center';
  $bareProof = ($proofChrome ?? 'full') === 'bare';
  $isBanner = ($layout ?? 'split') === 'banner';

  // The badge replaces the gold stars when a figure is supplied. Both halves are optional, so a
  // page can show the count alone.
  $badgeCount = $proofBadgeCount ?? '';
  $badgeLabel = $proofBadgeLabel ?? '';
  $hasBadge = $badgeCount !== '' || $badgeLabel !== '';

  // Proof card ground. 'midnight' is the flat #250D4A every page shipping this block was
  // measured against; 'violet' is the 2026 homepage's #6410A6 with a #7616B6 radial blooming
  // behind the globe (per direction 2026-09-16).
  // 'violet-deep' is the role comps' banner. Regressing the clean interior rows (y 1620-1950 of
  // the comp) and extrapolating to the card's true edges gives #5A1DAF at the top and #250D4D at
  // the bottom; the latter is --color-brand-dark-violet to within 3 units of blue, which is webp
  // rounding on a dark saturated purple rather than a second colour, so the token is used.
  $proofSurface = match ($proofBackground ?? 'midnight') {
    'violet' => 'background-color:#6410A6;background-image:radial-gradient(70% 55% at 50% 78%, #7616B6 0%, rgba(118,22,182,0) 76%);',
    'violet-deep' => 'background-color:#25104A;background-image:linear-gradient(180deg, #5A1DAF 0%, #25104A 100%);',
    default => 'background-color:#250D4A;',
  };

  // The role pages ship their own globe: same dotted sphere, but with the orbit arcs and the
  // candidate portraits composited in, so nothing has to be positioned over it here.
  $globeUrl = ($proofImage ?? '') ?: BlockDefaults::hireVaImg('globe-1.png');
  $globeW = ($proofImage ?? '') ? 498 : 546;
  $globeH = ($proofImage ?? '') ? 453 : 265;

  // Three overlapping portraits, the same treatment acf/trust-stats uses for its onboarded
  // count. NOTE: the comp's three faces are not in the theme's asset set and are not
  // recoverable from a 40px crop, so the theme's own portraits stand in.
  $badgeFaces = ['person_01.webp', 'person_02.webp', 'Person_03.webp'];

  // Two sets, positional in both. 'classic' is what every page shipping this block already
  // renders and stays the default; 'descriptive' is the role comps' set, where each glyph
  // depicts its card instead of decorating it. The stroke colour is text-brand-purple in both:
  // the comp's glyph is a 1.5px antialiased stroke whose most saturated pixel (#8644C5) is
  // already blended toward the tile, so it cannot distinguish brand-purple from star-purple.
  $iconSets = [
    'classic' => [
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    ],
    'descriptive' => [
      // No recurring fees: a barred dollar.
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><path d="M14.2 9.3h-3.3a1.7 1.7 0 0 0 0 3.4h2.2a1.7 1.7 0 0 1 0 3.4H9.8M12 7.6v1.7M12 16.1v1.7"/><line x1="5.6" y1="18.4" x2="18.4" y2="5.6"/></svg>',
      // Fluent English: a speech bubble carrying the language code.
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5a8.5 8.5 0 1 1-6.6 13.85L4 20.5l3.3-1.2A8.5 8.5 0 0 1 12 3.5Z"/><text x="12" y="14.6" text-anchor="middle" font-family="inherit" font-size="6.4" font-weight="700" fill="currentColor" stroke="none">EN</text></svg>',
      // 30% discount: a percent sign.
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><line x1="15" y1="9" x2="9" y2="15"/><circle cx="9.6" cy="9.6" r="1.15"/><circle cx="14.4" cy="14.4" r="1.15"/></svg>',
      // No contracts: a barred document.
      '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3.2H7.4a1.8 1.8 0 0 0-1.8 1.8v14a1.8 1.8 0 0 0 1.8 1.8h9.2a1.8 1.8 0 0 0 1.8-1.8V7.6z"/><polyline points="14 3.2 14 7.6 18.4 7.6"/><line x1="5.2" y1="19.6" x2="18.8" y2="4.4"/></svg>',
    ],
  ];

  $cardIcons = $iconSets[$iconSet ?? 'classic'] ?? $iconSets['classic'];
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Section Heading --}}
    <div @class(['mb-12 sm:mb-16', 'max-w-3xl' => ! $isCentred, 'max-w-3xl mx-auto text-center' => $isCentred])>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight leading-[1.08]">
        @if (($headline ?? '') === 'Why hire through Remote Leverage?')
          Why hire through<br class="hidden sm:inline"> Remote Leverage?
        @else
          {{ $headline }}
        @endif
      </h2>
    </div>

    @if ($isBanner)

    {{-- Banner arrangement: the proof card runs the full width with the globe in its own half,
         and the four reasons fall into a 2x2 grid beneath it. The globe is inside the padding
         here rather than bleeding off the bottom edge, because the role comps' export is a
         complete sphere rather than the split arrangement's cropped hemisphere. --}}
    <div class="rounded-card-lg overflow-hidden px-6 py-8 text-white shadow-lg sm:px-10 sm:py-10 lg:px-12 lg:py-12" style="{{ $proofSurface }}">
      <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-2 lg:gap-12">

        <div class="pointer-events-none">
          <img src="{{ $globeUrl }}"
               alt="Global Talent Distribution"
               width="{{ $globeW }}"
               height="{{ $globeH }}"
               loading="lazy"
               decoding="async"
               class="mx-auto h-auto w-full max-w-[420px] object-contain lg:max-w-[360px]">
        </div>

        <div class="flex flex-col items-start">
          @if ($hasBadge)
            <div class="flex items-center gap-4">
              <div class="flex items-center -space-x-3">
                @foreach ($badgeFaces as $face)
                  <img src="{{ BlockDefaults::homeImg($face) }}"
                       alt=""
                       width="88"
                       height="88"
                       loading="lazy"
                       decoding="async"
                       class="h-9 w-9 rounded-full border-2 border-white/60 object-cover">
                @endforeach
              </div>
              <div class="flex items-center gap-2.5">
                <svg class="h-[22px] w-[22px] shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 1.4l2.3 1.8 2.9-.3 1 2.7 2.6 1.3-.7 2.8L21.8 12l-1.7 2.3.7 2.8-2.6 1.3-1 2.7-2.9-.3-2.3 1.8-2.3-1.8-2.9.3-1-2.7-2.6-1.3.7-2.8L2.2 12l1.7-2.3-.7-2.8 2.6-1.3 1-2.7 2.9.3z" fill="#1D9BF0"/>
                  <path d="m8.3 12.2 2.5 2.5 4.9-4.9" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="font-display text-[13px] leading-[1.28] text-white">
                  @if ($badgeCount !== ''){{ $badgeCount }}@endif
                  @if ($badgeCount !== '' && $badgeLabel !== '')<br>@endif
                  @if ($badgeLabel !== ''){{ $badgeLabel }}@endif
                </span>
              </div>
            </div>
          @endif

          {{-- Centred on mobile and left aligned from lg, which is what the two comps show. The
               width cap keeps the four-line set the desktop comp sets; without it the wider
               canonical container reflows it to three. --}}
          <h3 @class([
            'w-full font-display font-bold tracking-[-0.02em] text-white',
            'text-center text-[26px] leading-[1.18] sm:text-[32px] lg:max-w-[430px] lg:text-left lg:text-[37px] lg:leading-[1.3]',
            'mt-7' => $hasBadge,
          ])>
            {{ $proofTitle }}
          </h3>

          @if (($proofCtaText ?? '') !== '')
            {{-- Compact: full width inside a card that is only ~310px wide at 390px, where the
                 default pill's chrome breaks the label over two lines. --}}
            @include('blocks.partials.cta-pill', [
              'text' => $proofCtaText,
              'url' => $proofCtaUrl,
              'size' => 'compact',
              'class' => 'mt-8 w-full sm:w-auto',
            ])
          @endif
        </div>

      </div>
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-2">
      @include('blocks.partials.why-hire-cards')
    </div>

    @else

    {{-- 2-Column Layout: Left Dark Card with Bleeding Globe + Right Stack of 4 Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
      
      {{-- Left Column: Massive Dark Purple Card with Bleeding Globe --}}
      <div class="lg:col-span-5 rounded-card-lg p-8 sm:p-10 text-white relative overflow-hidden shadow-lg flex flex-col justify-between min-h-[480px]" style="{{ $proofSurface }}">
        <div class="relative z-10">
          {{-- 5-Star Rating --}}
          <div @class([
            'mb-6',
            'inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 backdrop-blur-xs' => ! $bareProof,
            'flex' => $bareProof,
          ])>
            <div class="flex items-center gap-1 text-[#FFD700]">
              @for ($i = 0; $i < 5; $i++)
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              @endfor
            </div>
            @unless ($bareProof)
              <span class="text-xs font-bold text-white tracking-wide">5.0 Star Rating</span>
            @endunless
          </div>

          {{-- Value Statement --}}
          <h3 class="text-2xl sm:text-3xl font-bold font-display text-white leading-snug tracking-tight mb-4">
            {{ $proofTitle }}
          </h3>
          @unless ($bareProof)
            <p class="text-white/80 text-sm leading-relaxed max-w-md">
              Tap into vetted international professionals who integrate directly into your operations, saving up to 70% compared to local hires.
            </p>
          @endunless
        </div>

        {{-- Globe Graphic Flush to Bottom Edge (Bleed) --}}
        <div class="mt-8 -mb-10 sm:-mb-12 -mx-4 flex justify-center pointer-events-none relative z-0">
          <img src="{{ $globeUrl }}"
               alt="Global Talent Distribution"
               width="{{ $globeW }}"
               height="{{ $globeH }}"
               loading="lazy"
               decoding="async"
               class="w-full max-w-sm h-auto object-contain object-bottom drop-shadow-2xl">
        </div>
      </div>

      {{-- Right Column: Single Vertical Stack of 4 Horizontal Cards --}}
      <div class="lg:col-span-7 flex flex-col gap-4 sm:gap-5 justify-between">
        @include('blocks.partials.why-hire-cards')
      </div>

    </div>

    @endif

  </div>
</section>
