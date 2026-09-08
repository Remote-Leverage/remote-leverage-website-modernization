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

  $checklist = [
    'Interview Before You Hire',
    'Hire Direct - No Middleman',
    'No contracts',
    'Hire Within 72 Hours',
    'Fluent English',
    '30% Discount on Future Hires',
  ];
@endphp

<section class="relative overflow-hidden bg-[#1E0B38] text-white min-h-dvh flex flex-col justify-between pt-6 sm:pt-8 pb-6 sm:pb-8 lg:pb-10">
  {{-- Hero Background Graphic with Candidate Grid --}}
  <div class="absolute inset-0 z-0 pointer-events-none select-none">
    <img src="{{ BlockDefaults::themeImg('hire-va-4/hire-va-bg.webp') }}"
         alt=""
         class="w-full h-full object-cover object-[70%_top] lg:object-top" />
    {{-- Dark gradient overlay for mobile/tablet text readability --}}
    <div class="absolute inset-0 bg-gradient-to-r from-[#1E0B38]/95 via-[#1E0B38]/75 to-transparent lg:hidden"></div>
  </div>

  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10 flex-1 flex flex-col justify-between">

    {{-- Landing Header (Logo & Get Started CTA) --}}
    <div class="flex items-center justify-between pb-6 sm:pb-8 relative z-20">
      <a href="{{ home_url('/') }}" class="inline-flex items-center gap-2 group focus:outline-none" aria-label="Remote Leverage">
        <img src="{{ Vite::asset('resources/images/logo.svg') }}" 
             alt="{{ get_bloginfo('name', 'display') ?: 'Remote Leverage' }}"
             class="h-6 sm:h-7 w-auto brightness-0 invert transition-opacity group-hover:opacity-90" />
      </a>
      <a href="#consultation-card" 
         class="inline-flex items-center justify-center px-5 sm:px-6 py-2 sm:py-2.5 rounded-full bg-[#F8248A] hover:bg-[#D81575] text-white text-xs sm:text-sm font-bold tracking-wider uppercase shadow-md transition-all duration-150">
        GET STARTED
      </a>
    </div>

    {{-- Center Main Grid (Value Prop & Consultation Card) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center my-auto py-6 sm:py-8 lg:py-10">
      
      {{-- Left Column: Value Proposition --}}
      <div class="lg:col-span-6 xl:col-span-7">
        {{-- Trust Eyebrow Pill --}}
        <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-white/10 border border-white/15 text-white/90 text-xs sm:text-sm font-medium mb-6 backdrop-blur-xs shadow-xs">
          <span>{!! $badgeText !!}</span>
        </div>

        {{-- Main H1 --}}
        <h1 class="text-4xl sm:text-5xl lg:text-[52px] xl:text-[56px] font-bold font-display text-white tracking-tight leading-[1.08] mb-8">
          {!! $headline !!}
        </h1>

        {{-- 6-item Checklist Grid (2 Columns, simple white checkmark) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3.5 gap-x-6">
          @foreach ($checklist as $item)
            <div class="flex items-center gap-3 text-white text-sm sm:text-base font-medium">
              <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
              <span>{{ $item }}</span>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Right Column: Interactive Booking Card --}}
      <div class="lg:col-span-6 xl:col-span-5">
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
            :enable-isolated-fields="$enableIsolatedFields"
            :enableIsolatedFields="$enableIsolatedFields"
            :isolated-steps="$isolatedSteps"
            :isolatedSteps="$isolatedSteps"
            :hide-profile-header="$hideProfileHeader"
            :hideProfileHeader="$hideProfileHeader"
            :hide-progress-bar="$hideProgressBar"
            :hideProgressBar="$hideProgressBar"
            :button-text="$formButtonText"
            :buttonText="$formButtonText"
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
