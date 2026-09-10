<section class="relative overflow-hidden bg-linear-to-br from-[#F9EAFF] via-bg-light to-[#E6DEF4] py-16 sm:py-20 lg:py-24">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">

      {{-- Left Column: Headline, Subheadline, Dual CTA --}}
      <div class="lg:col-span-7">
        <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold text-black tracking-[-0.03em] leading-[1.1] mb-5">
          {!! $headline !!}
        </h1>

        @if (! empty($subheadline))
          <p class="text-base sm:text-lg text-black/70 leading-relaxed max-w-xl mb-8">
            {!! $subheadline !!}
          </p>
        @endif

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
          <a
            href="{{ $primaryCtaUrl }}"
            class="px-7 py-3.5 rounded-cta bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-sm uppercase tracking-wide shadow-lg transition transform hover:-translate-y-0.5 inline-flex items-center justify-center gap-2"
          >
            {{ $primaryCtaText }}
          </a>
          <a
            href="{{ $secondaryCtaUrl }}"
            class="px-7 py-3.5 rounded-cta bg-white border border-black/10 hover:border-brand-purple text-black font-bold text-sm uppercase tracking-wide transition inline-flex items-center justify-center gap-2"
          >
            {{ $secondaryCtaText }}
          </a>
        </div>
      </div>

      {{-- Right Column: Hero Image + Glass Earnings Progress Card --}}
      <div class="lg:col-span-5 relative">
        <div class="relative rounded-card-lg overflow-hidden aspect-square bg-black/5">
          @if (! empty($heroImage))
            <img
              src="{{ $heroImage }}"
              alt="{!! strip_tags($headline) !!}"
              loading="eager"
              decoding="async"
              class="w-full h-full object-cover"
            >
          @endif

          {{-- Glassmorphism Earnings Progress Card --}}
          <div class="absolute left-4 right-4 bottom-4 sm:left-6 sm:right-6 sm:bottom-6 rounded-card bg-white/10 backdrop-blur-md border border-white/25 p-4 sm:p-5 shadow-xl">
            <div class="flex items-center justify-between text-2xs font-bold uppercase tracking-wider text-white/80 mb-2">
              <span>{{ $statLabelLeft }}</span>
              <span class="text-white">{{ $statLabelRight }}</span>
            </div>
            <div class="w-full h-1.5 rounded-full bg-white/25 overflow-hidden mb-2.5">
              <div class="h-full rounded-full bg-white" style="width: {{ max(0, min(100, $statPercent)) }}%"></div>
            </div>
            <div class="flex items-end justify-between">
              <span class="font-display text-lg sm:text-xl font-bold text-white">{{ $statAmountLeft }}</span>
              <span class="text-2xs sm:text-xs text-white/80">{{ $statAmountRight }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
