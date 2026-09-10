@php
  $heroBgUrl = get_template_directory_uri().'/public/images/affiliate/Gradient_04.png';
@endphp
<section class="relative overflow-hidden bg-cover bg-center min-h-screen py-16 sm:py-20 lg:py-24" style="background-image: url('{{ $heroBgUrl }}')">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">

      {{-- Left Column: Headline, Subheadline, Dual CTA --}}
      <div class="lg:col-span-5">
        <h1 class="font-display text-4xl sm:text-5xl lg:text-6xl font-bold text-black tracking-[-0.03em] leading-[1.1] mb-5">
          {!! $headline !!}
        </h1>

        @if (! empty($subheadline))
          <div class="text-base sm:text-lg text-black leading-relaxed max-w-xl mb-8">
            {!! $subheadline !!}
          </div>
        @endif

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
          <a
            href="{{ $primaryCtaUrl }}"
            class="px-6 py-3.5 rounded-full bg-black hover:bg-brand-purple text-white font-bold text-sm uppercase tracking-wide transition inline-flex items-center justify-center gap-3 whitespace-nowrap"
          >
            {{ $primaryCtaText }}
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </a>
          <a
            href="{{ $secondaryCtaUrl }}"
            class="px-6 py-3.5 rounded-full bg-transparent border border-black hover:bg-black hover:text-white text-black font-bold text-sm uppercase tracking-wide transition inline-flex items-center justify-center gap-3 whitespace-nowrap"
          >
            {{ $secondaryCtaText }}
            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M13 6l6 6-6 6" />
            </svg>
          </a>
        </div>
      </div>

      {{-- Right Column: Hero Image + Glass Earnings Progress Card --}}
      <div class="lg:col-span-7 flex justify-center lg:justify-end">
        <div class="relative w-full max-w-[525px] pb-12 sm:pb-14 pr-7 sm:pr-10">
          <div class="relative rounded-card-lg overflow-hidden aspect-[4/5] bg-black/5">
            @if (! empty($heroImage))
              <img
                src="{{ $heroImage }}"
                alt="{!! strip_tags($headline) !!}"
                loading="eager"
                decoding="async"
                class="w-full h-full object-cover"
              >
            @endif
          </div>

          {{-- Glassmorphism Earnings Progress Card — offset to the bottom-right corner, bleeding past the photo's edges for depth --}}
          <div class="absolute left-[38%] right-0 bottom-0 sm:left-[40%] rounded-card bg-white/10 backdrop-blur-md border border-white/25 p-4 sm:p-5 shadow-2xl">
            <div class="flex items-center justify-between text-2xs font-bold uppercase tracking-wider text-white/80 mb-2">
              <span>{{ $statLabelLeft }}</span>
              <span class="text-white">{{ $statLabelRight }}</span>
            </div>
            <div class="w-full h-1.5 rounded-full bg-white/25 overflow-hidden mb-2.5">
              <div class="h-full rounded-full bg-white" style="width: {{ max(0, min(100, $statPercent)) }}%"></div>
            </div>
            <div class="flex items-end justify-between">
              <span class="font-display text-base sm:text-lg font-bold text-white whitespace-nowrap">{{ $statAmountLeft }}</span>
              <span class="text-2xs sm:text-xs text-white/80 text-right">{{ $statAmountRight }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
