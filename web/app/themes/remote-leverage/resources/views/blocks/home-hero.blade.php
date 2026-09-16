{{-- The 2026 homepage hero.

     Two compositions, not one layout reflowing:

       · Mobile — everything centred in one column: headline, subtitle, checklist, CTA. No
         talent cards, no rating row.
       · Desktop (lg and up, per direction 2026-09-16) — a two-column split: the copy left
         aligned in the left column, all four talent cards clustered in the right one. Before
         this the copy was centred with a card pair flanking it either side.

     Tokens measured off Homepage V3.png at 1366px and Page_v1.2.png at 376px. Container stays
     the canonical max-w-[1380px] — per docs/design-system.md rule 1 the container is the one
     thing not taken from the comp. The accent line is brand purple and the checklist ticks
     brand magenta at every width; the headline is always three lines.

     One content list drives both checklist layouts — see HomeHeroBlock::checklist() for why the
     order of the six items makes the desktop two-column grid and the mobile single column
     agree. --}}
@php
  // The cards were split left/right of the centred copy. Now they share one cluster, so the
  // field only decides which of the two stacks a card joins — `side` keeps its name because it
  // still means "which pile", and the stored content does not need rewriting.
  $nearStack = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'left'));
  $farStack = array_values(array_filter($cards, fn ($c) => ($c['side'] ?? 'left') === 'right'));

  // Within a stack the first card sits behind and paler, the second overlaps it lower and
  // nearer. Spelled out per slot rather than computed: Tailwind scans source text and never
  // sees a class assembled by concatenation.
  $cardSkin = [
    ['surface' => 'bg-lavender-tint', 'z' => 'z-10'],
    ['surface' => 'bg-[#EBEBFF]', 'z' => 'z-20'],
  ];

  // Cluster geometry, in a 452x452 box: two overlapping pairs side by side, the right pair
  // dropped lower so the four read as a fan rather than a grid. Rotation travels as a custom
  // property because .rl-hero-card's idle float has to rebuild the whole transform each
  // keyframe — a Tailwind rotate-* utility would be overwritten by the animation.
  $placement = [
    ['pos' => 'left-[18px] top-0', 'rot' => '-7deg', 'delay' => '0s'],
    ['pos' => 'left-0 top-[136px]', 'rot' => '-3deg', 'delay' => '.9s'],
    ['pos' => 'left-[268px] top-[44px]', 'rot' => '7deg', 'delay' => '.45s'],
    ['pos' => 'left-[252px] top-[180px]', 'rot' => '3deg', 'delay' => '1.35s'],
  ];
@endphp

{{-- flex-1 + justify-center: the pattern wraps this in a min-h-dvh column with the logo strip
     pinned under it, so the hero takes the slack and centres in whatever is left. --}}
<section class="relative flex flex-1 flex-col justify-center overflow-hidden bg-bg-light pt-10 pb-10 lg:pt-14 lg:pb-8">
  <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_452px] lg:items-center lg:gap-10 xl:gap-16">

      <div class="relative z-30 mx-auto flex max-w-[720px] flex-col items-center text-center lg:mx-0 lg:max-w-none lg:items-start lg:text-left">

        {{-- Google rating. Hidden for now (direction 2026-09-16) — the field is still there, so
             turning it back on is one flag rather than restoring markup. --}}
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

        {{-- Always three lines: the two headline lines come from the field's own line breaks,
             the accent line is its own field. Left to wrap on its own the desktop headline sets
             on two lines, which is not what the page is meant to say. --}}
        <h1 class="font-display font-bold tracking-[-0.02em] text-brand-hero text-[34px] leading-[1.14] sm:text-[42px] lg:text-[48px] lg:leading-[1.1] xl:text-[54px]">
          {!! nl2br(e($headline)) !!}<br>
          <span class="text-brand-purple">{{ $headlineAccent }}</span>
        </h1>

        <p class="mt-5 max-w-[640px] font-display text-[17px] leading-[1.5] text-brand-hero sm:text-lg lg:mt-[18px] lg:text-[20px] lg:leading-[30px]">
          {!! $subtitle !!}
        </p>

        {{-- Checklist: magenta ticks on the bare ground at every width, one column on mobile and
             two on desktop. The columns are `max-content` rather than `auto`: an auto track
             absorbs free space before justify-content gets a look in, so the columns butt
             together and the longer items wrap. --}}
        @if ($checklist)
          <ul class="mt-8 grid w-full max-w-[548px] grid-cols-1 gap-y-[18px] text-left lg:mt-7 lg:w-auto lg:max-w-none lg:grid-cols-[max-content_max-content] lg:justify-start lg:gap-x-10 lg:gap-y-[14px]">
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

      {{-- Talent cards. lg and up only: the cluster needs its own 452px column, which a phone
           does not have, and the mobile comp has no cards at all. --}}
      @if ($cards)
        <div class="relative hidden h-[452px] w-[452px] lg:block" aria-hidden="true">
          @foreach (array_merge($nearStack, $farStack) as $i => $card)
            @php($place = $placement[$i % count($placement)])
            @php($skin = $cardSkin[($i % 2)])
            <div class="absolute {{ $place['pos'] }} {{ $skin['z'] }} rl-hero-card"
                 style="--rl-card-rot: {{ $place['rot'] }}; animation-delay: {{ $place['delay'] }};">
              @include('blocks.partials.home-hero-card', ['card' => $card, 'surface' => $skin['surface']])
            </div>
          @endforeach
        </div>
      @endif

    </div>
  </div>
</section>
