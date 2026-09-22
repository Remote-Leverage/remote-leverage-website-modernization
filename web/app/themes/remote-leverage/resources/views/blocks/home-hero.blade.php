{{-- The 2026 homepage hero, and the role pages' hero.

     `media` chooses what sits beside the copy, and the two choices are different compositions
     rather than one layout reflowing.

     media = 'cards' (default — the homepage):
       · Mobile — everything centred in one column: headline, subtitle, checklist, CTA. No
         talent cards, no rating row.
       · Desktop (lg and up, per direction 2026-09-16) — a two-column split: the copy left
         aligned in the left column, a fan of three talent cards in the right one. Before this
         the copy was centred with a card pair flanking it either side.

     media = 'image' (the role pages, /admin-virtual-assistants/ and its siblings):
       · Mobile — one column, but left aligned rather than centred, and the square composite
         follows the CTA instead of being dropped.
       · Desktop — even halves: copy left, the composite right. The checklist flows down each
         column in turn here, matching the order its own mobile column reads in.

     Tokens measured off Homepage V3.png at 1366px and Page_v1.2.png at 376px. Container stays
     the canonical max-w-[1380px] — per docs/design-system.md rule 1 the container is the one
     thing not taken from the comp. The accent line is brand purple and the checklist ticks
     brand magenta at every width; the headline is always three lines.

     One content list drives both checklist layouts — see HomeHeroBlock::checklist() for why the
     order of the six items makes the desktop two-column grid and the mobile single column
     agree. --}}
@php
  // `media` picks which of the two right-hand columns runs. 'cards' is the homepage and stays
  // the default, so every page that set nothing is untouched. 'image' is the role pages
  // (/admin-virtual-assistants/ and its thirteen siblings), whose comps replace the fan with one
  // square composite and left-align the copy at every width instead of centring it on mobile.
  $isImage = ($media ?? 'cards') === 'image';

  // A three-card fan: two cards set back and tilted away to either side, the third centred,
  // lower and nearer. Depth is carried by three things at once — vertical offset, stacking
  // order and surface tint — so the arrangement reads as considered rather than as three cards
  // that happen to overlap.
  //
  // Offsets are percentages of the fan box, and the card sizes itself as a percentage too, so
  // one set of numbers holds at both box sizes. The box shrinks to 75% at lg, where a 1024px
  // viewport cannot spare 568px beside the headline. A transform scale would have been simpler
  // to write and wrong: transforms do not affect layout, so the column would still have
  // reserved the full width and squeezed the copy to ~300px.
  //
  // Rotation travels as a custom property rather than a Tailwind rotate-* utility: the hover
  // state in .rl-hero-card rebuilds the whole transform to straighten the card, and would
  // overwrite a utility's rotation.
  $placement = [
    ['pos' => 'left-0 top-[6.2%]', 'rot' => '-9deg', 'z' => 'z-10', 'surface' => 'bg-lavender-tint'],
    ['pos' => 'left-[28.5%] top-[34.2%]', 'rot' => '-2deg', 'z' => 'z-20', 'surface' => 'bg-[#EBEBFF]'],
    ['pos' => 'left-[57.7%] top-0', 'rot' => '9deg', 'z' => 'z-10', 'surface' => 'bg-lavender-tint'],
  ];
@endphp

{{-- flex-1 + justify-center: the pattern wraps this in a min-h-dvh column with the logo strip
     pinned under it, so the hero takes the slack and centres in whatever is left. --}}
<section class="relative flex flex-1 flex-col justify-center overflow-hidden bg-bg-light pt-10 pb-10 lg:pt-14 lg:pb-8">
  <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div @class([
      'lg:grid lg:items-center lg:gap-10 xl:gap-16',
      'lg:grid-cols-[minmax(0,1fr)_auto]' => ! $isImage,
      // Even halves: the composite is square and sits flush to the container's right edge, so
      // an auto track (which sizes to the image's intrinsic 1000px) would crowd out the copy.
      'lg:grid-cols-2 xl:gap-20' => $isImage,
    ])>

      <div @class([
        'relative z-30 flex flex-col',
        'mx-auto max-w-[720px] items-center text-center lg:mx-0 lg:max-w-none lg:items-start lg:text-left' => ! $isImage,
        'max-w-[640px] items-start text-left lg:max-w-none' => $isImage,
      ])>

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
          <span @class(['text-brand-purple' => ($accentTone ?? 'purple') === 'purple'])>{{ $headlineAccent }}</span>
        </h1>

        <p class="mt-5 max-w-[640px] font-display text-[17px] leading-[1.5] text-brand-hero sm:text-lg lg:mt-[18px] lg:text-[20px] lg:leading-[30px]">
          {!! $subtitle !!}
        </p>

        {{-- Checklist: magenta ticks on the bare ground at every width, one column on mobile and
             two on desktop.

             On mobile the list is `w-fit mx-auto`, so the block shrinks to its longest item and
             centres as a unit while the items stay left-aligned inside it. It was `w-full`,
             which pinned every tick to the screen edge under centred copy.

             The desktop columns are `max-content` rather than `auto`: an auto track absorbs
             free space before justify-content gets a look in, so the columns butt together and
             the longer items wrap. --}}
        @if ($checklist)
          <ul @class([
            'mt-8 grid max-w-full grid-cols-1 gap-y-[18px] text-left lg:mt-7 lg:w-auto lg:max-w-none lg:justify-start lg:gap-x-10 lg:gap-y-[14px]',
            'mx-auto w-fit lg:mx-0 lg:grid-cols-[max-content_max-content]' => ! $isImage,
            // Column-major, not row-major. The role comps read straight down the left column and
            // then down the right, and their mobile column repeats that same order — so the DOM
            // order is already the mobile order and the desktop grid flows down each column in
            // turn. Row flow here would have transposed the two desktop columns against mobile.
            'w-full lg:w-auto lg:grid-flow-col lg:grid-rows-3 lg:auto-cols-max' => $isImage,
          ])>
            @foreach ($checklist as $item)
              <li class="flex items-center gap-3">
                <span @class([
                  'flex h-4 w-4 shrink-0 items-center justify-center rounded-full',
                  'bg-brand-magenta' => ($tickTone ?? 'magenta') !== 'emerald',
                  // Measured off the role comps: the tick ring reads #21AE78-#30B881 across
                  // three samples, which is #10B981 under webp chroma rounding.
                  'bg-[#10B981]' => ($tickTone ?? 'magenta') === 'emerald',
                ])>
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

      {{-- The role comps' composite: the portrait, the Google rating badge, the CRM and call
           pills and the contact-list card are all baked into one square export, so there is
           nothing to position here. Square at both breakpoints — 447x448 in the 1170px desktop
           comp, 260x260 in the 293px mobile one.

           fetchpriority high and no lazy attribute: this is the largest element in the first
           viewport on every role page, so it is the LCP candidate.
           No `decoding="async"`: same reason as acf/hire-va-hero — async decode
           delayed LCP promotion after the pixels were already on screen. --}}
      @if ($isImage && $heroImage)
        <div class="mt-10 w-full lg:mt-0">
          <img src="{{ $heroImage }}"
               alt="{{ trim(preg_replace('/\s+/', ' ', $headline.' '.$headlineAccent)) }}"
               width="1000" height="1000" fetchpriority="high"
               class="aspect-square h-auto w-full rounded-card object-cover">
        </div>
      @endif

      {{-- Talent cards. lg and up only: the fan needs its own column, which a phone does not
           have, and the mobile design has no cards at all.

           The column auto-sizes to the fan, which is why the box carries real widths rather
           than a transform. --}}
      @if ($cards && ! $isImage)
        {{-- pr clears the rotated corners. A 240x322 card turned 9deg reaches ~50px past its own
             box, and the section clips at the viewport, so without this the right-hand card lost
             its top corner. --}}
        <div class="hidden lg:block lg:pr-10 xl:pr-14">
          <div class="relative h-[388px] w-[424px] xl:h-[520px] xl:w-[568px]" aria-hidden="true">
            @foreach (array_slice($cards, 0, count($placement)) as $i => $card)
              @php($place = $placement[$i])
              {{-- The wrapper carries the size, not the card: the card's percentage width would
                   otherwise resolve against this absolutely-positioned box, which has no width
                   of its own and shrinks to its content. --}}
              <div class="group absolute aspect-[240/322] w-[42.3%] {{ $place['pos'] }} {{ $place['z'] }} rl-hero-card"
                   style="--rl-card-rot: {{ $place['rot'] }};">
                @include('blocks.partials.home-hero-card', ['card' => $card, 'surface' => $place['surface']])
              </div>
            @endforeach
          </div>
        </div>
      @endif

    </div>
  </div>
</section>
