{{-- The 2026 homepage hero: a centred column (Google rating, headline, subtitle, checklist card,
     CTA) with two pairs of tilted talent cards flanking it, over the theme's #F4F6FC ground.

     Tokens and geometry measured off Homepage V3.png at 1366px and Page_v1.2.png at 376px on
     2026-09-15. Container stays the canonical max-w-[1380px] — per docs/design-system.md rule 1
     the container is the one thing not taken from the comp.

     Desktop and mobile differ only in what is present, not in how anything is styled:
       · mobile drops the rating row and both card pairs entirely
       · desktop sets the checklist in two columns, mobile in one
     Everything else is shared. Per direction on 2026-09-16 the comp's two desktop-only
     treatments were dropped in favour of the mobile ones — the accent line is brand purple at
     every width, and the checklist ticks are brand magenta on the bare ground with no white
     card behind them. The headline is always three lines.

     One content list drives both columns — see HomeHeroBlock::checklist() for why the order of
     the six items makes the desktop two-column grid and the mobile single column agree. --}}
@php
  $leftCards = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'left'));
  $rightCards = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'right'));

  // Back card sits deeper and paler; the front card overlaps it down and inward.
  //
  // Placement is measured, not eyeballed. Card boxes on Homepage V3.png at 1366px, read off
  // horizontal and vertical colour-run scans, with y relative to the section top (header
  // bottom, 68px):
  //     André   #DDE2F6  centre (176, 232)  ~-3deg
  //     Luana   #EBEBFF  centre (166, 340)  ~-9deg
  //     Mariana #DDE2F6  centre (1220, 320) ~+7deg
  //     Bruno   #EBEBFF  centre (1161, 384) ~+5deg
  // Cards are 180x255. Every offset below is that centre minus half the card, expressed from
  // the group anchor. Spelled out as literals per slot rather than computed, because Tailwind
  // scans source text and never sees a class built by concatenation.
  $cardSkin = [
    ['surface' => 'bg-lavender-tint', 'z' => 'z-10'],
    ['surface' => 'bg-[#EBEBFF]', 'z' => 'z-20'],
  ];
@endphp

{{-- flex-1 + justify-center: the pattern wraps this in a min-h-dvh column with the logo strip
     pinned under it, so the hero takes the slack and centres in whatever is left. --}}
<section class="relative flex flex-1 flex-col justify-center overflow-hidden bg-bg-light pt-10 pb-10 lg:pt-[72px] lg:pb-8">
  <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Floating talent cards. Shown from xl up only: they need ~215px per side beside the
         720px text column, which a 1024px viewport does not have, and the mobile comp has no
         cards at all — so there is nothing to serve between those two. --}}
    @if ($leftCards)
      <div class="pointer-events-none absolute left-[60px] top-0 hidden h-[470px] w-[216px] xl:block" aria-hidden="true">
        @foreach ($leftCards as $i => $card)
          <div class="absolute {{ $cardSkin[$i % 2]['z'] }} {{ $i === 0 ? 'left-[26px] top-[105px] -rotate-3' : 'left-4 top-[213px] -rotate-[9deg]' }}">
            @include('blocks.partials.home-hero-card', ['card' => $card, 'surface' => $cardSkin[$i % 2]['surface']])
          </div>
        @endforeach
      </div>
    @endif

    @if ($rightCards)
      <div class="pointer-events-none absolute right-[56px] top-0 hidden h-[520px] w-[240px] xl:block" aria-hidden="true">
        @foreach ($rightCards as $i => $card)
          <div class="absolute {{ $cardSkin[$i % 2]['z'] }} {{ $i === 0 ? 'right-0 top-[192px] rotate-[7deg]' : 'right-[59px] top-[256px] rotate-[5deg]' }}">
            @include('blocks.partials.home-hero-card', ['card' => $card, 'surface' => $cardSkin[$i % 2]['surface']])
          </div>
        @endforeach
      </div>
    @endif

    <div class="relative z-30 mx-auto flex max-w-[720px] flex-col items-center text-center">

      {{-- Google rating. Desktop only — the mobile comp omits it. --}}
      @if ($showRating)
        <div class="mb-7 hidden items-center gap-2.5 lg:flex">
          <img src="{{ $ratingLogo }}" alt="Google" width="66" height="22" class="h-[22px] w-auto" decoding="async">
          <span class="font-display text-[17px] font-medium text-brand-hero">{{ $ratingScore }}</span>
          <span class="flex items-center gap-0.5 text-[#FFB400]" role="img" aria-label="{{ $ratingScore }} out of 5 stars">
            @for ($s = 0; $s < 5; $s++)
              <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
              </svg>
            @endfor
          </span>
        </div>
      @endif

      {{-- Always three lines: the two headline lines come from the field's own line breaks, the
           accent line is its own field. Left to wrap on its own the desktop headline sets on
           two lines, which is not what the page is meant to say. --}}
      <h1 class="font-display font-bold tracking-[-0.02em] text-brand-hero text-[34px] leading-[1.14] sm:text-[42px] lg:text-[48px] lg:leading-[1.1]">
        {!! nl2br(e($headline)) !!}<br>
        <span class="text-brand-purple">{{ $headlineAccent }}</span>
      </h1>

      <p class="mt-5 max-w-[640px] font-display text-[17px] leading-[1.5] text-brand-hero sm:text-lg lg:mt-[18px] lg:text-[20px] lg:leading-[30px]">
        {!! $subtitle !!}
      </p>

      {{-- Checklist: magenta ticks on the bare ground at every width, one column on mobile and
           two on desktop. The comp put the desktop pair inside a 548x120 white card with green
           ticks; dropped on 2026-09-16 in favour of the mobile treatment everywhere.

           The desktop columns are `max-content` and pushed apart rather than a 1fr/1fr split,
           which keeps the comp's column positions (left 427..663, right 713..937 within a
           548px block). They cannot be `auto`: an auto track absorbs free space before
           justify-content gets a look in, so the columns butt together and the longer items
           wrap. --}}
      @if ($checklist)
        <ul class="mt-8 grid w-full max-w-[548px] grid-cols-1 gap-y-[18px] text-left lg:mt-7 lg:grid-cols-[max-content_max-content] lg:justify-between lg:gap-y-[14px]">
          @foreach ($checklist as $item)
            <li class="flex items-center gap-3">
              <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-brand-magenta">
                <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </span>
              <span class="font-display text-[15px] leading-tight text-brand-hero">{{ $item }}</span>
            </li>
          @endforeach
        </ul>
      @endif

      @include('blocks.partials.cta-pill', [
        'text' => $ctaText,
        'url' => $ctaUrl,
        'class' => 'mt-10 w-full max-w-[440px] lg:mt-9 lg:w-auto lg:max-w-none',
      ])
    </div>
  </div>
</section>
