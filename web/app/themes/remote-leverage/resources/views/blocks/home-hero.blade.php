{{-- The 2026 homepage hero: a centred column (Google rating, headline, subtitle, checklist card,
     CTA) with two pairs of tilted talent cards flanking it, over the theme's #F4F6FC ground.

     Tokens and geometry measured off Homepage V3.png at 1366px and Page_v1.2.png at 376px on
     2026-09-15. Container stays the canonical max-w-[1380px] — per docs/design-system.md rule 1
     the container is the one thing not taken from the comp.

     Desktop and mobile are genuinely different compositions here, not one layout reflowing:
       · mobile drops the rating row and both card pairs entirely
       · mobile prints the accent line in brand purple (#8A2BE2); desktop keeps it black
       · mobile checks are brand magenta on the bare ground; desktop checks are #0EBC67 inside
         a 548x120 white card
     Both are driven by one content list — see HomeHeroBlock::checklist() for why the order of
     the six items makes the desktop two-column grid and the mobile single column agree. --}}
@php
  use App\Support\BlockDefaults;

  $leftCards = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'left'));
  $rightCards = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'right'));

  // Back card sits deeper and paler; the front card overlaps it down and inward. Spelled out
  // per side rather than computed so Tailwind can see every class as a literal.
  $cardSkin = [
    ['surface' => 'bg-lavender-tint', 'z' => 'z-10'],
    ['surface' => 'bg-[#EBEBFF]', 'z' => 'z-20'],
  ];
@endphp

<section class="relative overflow-hidden bg-bg-light pt-10 pb-10 lg:pt-[72px] lg:pb-8">
  <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Floating talent cards. Absolutely positioned from lg up, where there is room beside the
         centred column; hidden below that because the mobile comp has no cards at all. --}}
    @if ($leftCards)
      <div class="pointer-events-none absolute left-0 top-4 hidden w-[240px] lg:block xl:w-[260px]" aria-hidden="true">
        @foreach ($leftCards as $i => $card)
          <div class="absolute {{ $cardSkin[$i % 2]['z'] }} {{ $i === 0 ? 'left-6 top-0 -rotate-[5deg]' : 'left-0 top-[92px] -rotate-[8deg]' }}">
            @include('blocks.partials.home-hero-card', ['card' => $card, 'surface' => $cardSkin[$i % 2]['surface']])
          </div>
        @endforeach
      </div>
    @endif

    @if ($rightCards)
      <div class="pointer-events-none absolute right-0 top-[84px] hidden w-[240px] lg:block xl:w-[260px]" aria-hidden="true">
        @foreach ($rightCards as $i => $card)
          <div class="absolute {{ $cardSkin[$i % 2]['z'] }} {{ $i === 0 ? 'right-0 top-0 rotate-[6deg]' : 'right-[70px] top-[66px] rotate-[8deg]' }}">
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

      <h1 class="font-display font-bold tracking-[-0.02em] text-brand-hero text-[34px] leading-[1.14] sm:text-[42px] lg:text-[48px] lg:leading-[1.1]">
        {{ $headline }}<br>
        <span class="text-brand-purple lg:text-brand-hero">{{ $headlineAccent }}</span>
      </h1>

      <p class="mt-5 max-w-[660px] font-display text-[17px] leading-[1.5] text-brand-hero sm:text-lg lg:mt-6 lg:text-[20px] lg:leading-[30px]">
        {!! $subtitle !!}
      </p>

      {{-- Checklist. One list, two treatments: a white card with #0EBC67 ticks in two columns
           on desktop, bare magenta ticks in one column on mobile. --}}
      @if ($checklist)
        <ul class="mt-8 grid w-full max-w-[548px] grid-cols-1 gap-x-12 gap-y-[18px] text-left lg:mt-7 lg:gap-y-[18px] lg:grid-cols-2 lg:rounded-2xl lg:bg-white lg:px-[18px] lg:py-5">
          @foreach ($checklist as $item)
            <li class="flex items-center gap-3">
              <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-brand-magenta lg:h-4 lg:w-4 lg:bg-[#0EBC67]">
                <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </span>
              <span class="font-display text-[15px] leading-tight text-brand-hero lg:text-base">{{ $item }}</span>
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
