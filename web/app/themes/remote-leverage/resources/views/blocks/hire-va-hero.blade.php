{{-- Hire-a-VA hero: headline and CTA over a background graphic with a grid of candidate portraits. --}}
@php
  use App\Support\BlockDefaults;

  $logos = [
    ['name' => 'Garuz Legal Group', 'src' => BlockDefaults::themeImg('home/garuz-1-1.webp')],
    ['name' => 'Borror / BRRRR', 'src' => BlockDefaults::themeImg('home/brrrr-1.webp')],
    ['name' => 'AdCenter360', 'src' => BlockDefaults::themeImg('home/adcenter-2.webp')],
    ['name' => 'Chick-fil-A', 'src' => BlockDefaults::themeImg('home/chick-fil-a-logo-1.webp')],
    ['name' => 'ADP', 'src' => BlockDefaults::themeImg('home/rl-adp.webp')],
    ['name' => 'Mainstreet', 'src' => BlockDefaults::themeImg('home/rl-mainstreet.webp')],
    ['name' => 'College Hunks', 'src' => BlockDefaults::themeImg('home/rl-college-hunks.webp')],
    ['name' => 'Farmers Insurance', 'src' => BlockDefaults::themeImg('home/rl-farmers.webp')],
    ['name' => 'RE/MAX', 'src' => BlockDefaults::themeImg('home/remax-1.webp')],
  ];

  // $checklist is supplied by HireVaHeroBlock::with(); it falls back to the
  // standard six English items when the block's repeater is empty.

  $heroBg = BlockDefaults::hireVaHeroBackground();
@endphp

{{-- pt-24 below lg clears the fixed 90px CTA-only header (sections/header-cta.blade.php).
     Desktop keeps pt-8: there the grid's `my-auto` leaves ~96px of centring slack, so the
     header is cleared already. On mobile the content fills the box, that slack collapses to
     0, and the eyebrow pill rendered underneath the header. --}}
<section class="relative overflow-hidden bg-[#1E0B38] text-white min-h-dvh flex flex-col justify-between pt-24 lg:pt-8 pb-6 sm:pb-8 lg:pb-10">
  {{-- Hero Background Graphic with Candidate Grid --}}
  <div class="absolute inset-0 z-0 pointer-events-none select-none">
    {{-- LCP on every page that uses this block. Mobile-first: `img src` is the
         750px file PSI actually paints. A desktop `src` here is what the
         2026-09-18 staging re-run fetched on a 412px viewport (85 KB decode
         during TBT) despite the picture source. Desktop is a min-width source
         so the preload scanner does not discover it on a phone. --}}
    <picture>
      <source media="(min-width: 1024px)" srcset="{{ $heroBg['desktop'] }}" type="image/webp" width="1366" height="945" />
      <img src="{{ $heroBg['mobile'] }}"
           alt="" width="750" height="519"
           fetchpriority="high" decoding="async"
           class="w-full h-full object-cover object-[70%_top] lg:object-top" />
    </picture>
    {{-- Dark gradient overlay for mobile/tablet text readability --}}
    <div class="absolute inset-0 bg-gradient-to-r from-[#1E0B38]/95 via-[#1E0B38]/75 to-transparent lg:hidden"></div>
  </div>

  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10 flex-1 flex flex-col justify-between">

    {{-- No header bar inside the hero: the CTA-only header is a real page header,
         sections/header-cta.blade.php, swapped in by layouts/app.blade.php via
         App\Support\PageChrome. Production renders it above the hero on the page
         background, not overlaid on the artwork. --}}

    {{-- Center Main Grid (Value Prop & Consultation Card).
         `form-first` splits the checklist into its own grid child so it can be ordered
         below the booking card once the hero stacks, which is how production serves
         /hire-va-4/, /hire-for-less/, /hire-va-6/ and /hire-va-1st-month-free/ at 390px.
         Desktop is pinned with explicit col-start/row-start so the split is unchanged,
         and lg:gap-y-0 keeps the headline's own mb-8 as the only gap between the two
         left-hand cells, exactly as when they shared one.

         The default keeps the original two-child markup untouched: emitting the split
         structure unconditionally moved /spanish/'s vertically-centred headline by 6px. --}}
    @php($formFirst = ($mobileOrder ?? 'checklist-first') === 'form-first')
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 {{ $formFirst ? 'lg:gap-x-12 lg:gap-y-0' : 'lg:gap-12' }} items-center my-auto py-6 sm:py-8 lg:py-10">

      {{-- Left Column: Value Proposition --}}
      <div class="{{ $formFirst ? 'order-1 lg:order-none lg:col-start-1 lg:row-start-1' : '' }} lg:col-span-6 xl:col-span-7">
        {{-- Trust eyebrow pill — omitted entirely when the block passes a blank badge --}}
        @if (! empty($badgeText))
          <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-white/10 border border-white/15 text-white/90 text-xs sm:text-sm font-medium mb-6 backdrop-blur-xs shadow-xs">
            <span>{!! $badgeText !!}</span>
          </div>
        @endif

        {{-- Main H1 --}}
        <h1 class="text-4xl sm:text-5xl lg:text-[52px] xl:text-[56px] font-bold font-display text-white tracking-tight leading-[1.08] mb-8">
          {!! $headline !!}
        </h1>

        @unless ($formFirst)
          @include('blocks.partials.hire-va-checklist', ['checklist' => $checklist])
        @endunless
      </div>

      @if ($formFirst)
        {{-- Ordered below the booking card on phones, back under the headline at lg. --}}
        <div class="order-3 lg:order-none lg:col-span-6 xl:col-span-7 lg:col-start-1 lg:row-start-2">
          @include('blocks.partials.hire-va-checklist', ['checklist' => $checklist])
        </div>
      @endif

      {{-- Right Column: Interactive Booking Card --}}
      <div class="{{ $formFirst ? 'order-2 lg:order-none lg:col-start-7 xl:col-start-8 lg:row-start-1 lg:row-span-2' : '' }} lg:col-span-6 xl:col-span-5">
        <div id="consultation-card" class="bg-white rounded-[24px] p-6 sm:p-8 text-neutral-900 border border-white/20 shadow-[0_24px_60px_rgba(0,0,0,0.35)] relative z-10 max-w-[500px] lg:ml-auto w-full">
          <div class="mb-6">
            <h2 class="text-2xl sm:text-[28px] font-bold text-slate-900 tracking-tight leading-tight">
              {{ $bookingTitle ?: 'Book a Free 15-Minute Consultation' }}
            </h2>
            @if (!empty($bookingSubtitle))
              <p class="text-xs sm:text-sm text-text-muted mt-1.5">
                {{ $bookingSubtitle }}
              </p>
            @endif
          </div>
          <livewire:booking.multistep-booking-wizard
            skin="naked"
            :lazy="false"
            :enable-isolated-fields="(bool) ($enableIsolatedFields ?? false)"
            :enableIsolatedFields="(bool) ($enableIsolatedFields ?? false)"
            :isolated-steps="is_array($isolatedSteps ?? null) ? $isolatedSteps : []"
            :isolatedSteps="is_array($isolatedSteps ?? null) ? $isolatedSteps : []"
            :hide-profile-header="(bool) ($hideProfileHeader ?? false)"
            :hideProfileHeader="(bool) ($hideProfileHeader ?? false)"
            :hide-progress-bar="(bool) ($hideProgressBar ?? false)"
            :hideProgressBar="(bool) ($hideProgressBar ?? false)"
            {{-- Deliberately NOT revenue-first: this is the hero's progressive form, which opens
                 on the email field alone and reveals the rest after. The revenue-first radio
                 treatment is scoped to the page-bottom booking blocks only. --}}
            :button-text="$formButtonText ?? 'Book a Consultation'"
            :buttonText="$formButtonText ?? 'Book a Consultation'"
          />
        </div>
      </div>

    </div>

    {{-- Bottom Logo Marquee / Social Proof --}}
    <div class="mt-auto pt-6 border-t border-white/10 relative z-10">
      <div class="rl-logo-marquee-wrapper overflow-hidden py-2">
        <div class="animate-marquee-logos flex items-center gap-10 sm:gap-14 lg:gap-16">
          @foreach (array_merge($logos, $logos) as $logo)
            <div class="rl-logo-marquee-item shrink-0">
              <img src="{{ $logo['src'] }}"
                   alt="{{ $logo['name'] }}"
                   loading="lazy"
                   decoding="async"
                   style="filter: brightness(0) invert(1);"
                   class="h-6 sm:h-7 lg:h-8 max-w-[140px] w-auto object-contain opacity-70 hover:opacity-100 transition-opacity duration-200">
            </div>
          @endforeach
        </div>
      </div>
    </div>

  </div>
</section>
