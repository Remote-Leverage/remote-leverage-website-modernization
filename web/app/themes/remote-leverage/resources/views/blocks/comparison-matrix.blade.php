@php
  use App\Support\BlockDefaults;

  $unionSvg = BlockDefaults::hireVaImg('Union.svg');
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-14">
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight mb-3">
        {{ $headline }}
      </h2>
      <p class="text-base sm:text-lg text-text-muted font-medium">
        {{ $subheadline }}
      </p>
    </div>

    {{-- Single Unified Comparison Card Box --}}
    <div class="max-w-4xl mx-auto bg-white rounded-card-lg p-8 sm:p-12 border border-black/5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] mb-12">
      <div class="grid grid-cols-1 md:grid-cols-11 gap-6 items-center">
        
        {{-- Left: Hiring on your own --}}
        <div class="md:col-span-5 text-center md:text-left">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider mb-4">
            <span>Traditional DIY</span>
          </div>
          <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight mb-3">
            Hiring on your own
          </h3>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed mb-2">
            4 to 8 weeks of posting, screening, and interviewing.
          </p>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed">
            Payroll, taxes, and compliance all on you.
          </p>
        </div>

        {{-- Center: Arrow Connector --}}
        <div class="md:col-span-1 flex items-center justify-center py-2 md:py-0">
          <div class="w-12 h-12 rounded-full bg-brand-purple/10 flex items-center justify-center">
            <img src="{{ $unionSvg }}"
                 alt="Transform to Remote Leverage"
                 width="104"
                 height="30"
                 loading="lazy"
                 decoding="async"
                 class="w-6 h-auto object-contain rotate-90 md:rotate-0">
          </div>
        </div>

        {{-- Right: Hiring with Remote Leverage --}}
        <div class="md:col-span-5 text-center md:text-left md:pl-4">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-[#00D67D] text-xs font-bold uppercase tracking-wider mb-4">
            <span>Remote Leverage Way</span>
          </div>
          <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-purple tracking-tight mb-3">
            Hiring with Remote Leverage
          </h3>
          <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed mb-2">
            Interview the top 1% in 72 hours.
          </p>
          <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed">
            We handle payroll, compliance, and onboarding.
          </p>
        </div>

      </div>
    </div>

    {{-- CTA Button --}}
    <div class="flex justify-center">
      <a href="{{ $ctaUrl }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#8A2BE2] hover:bg-[#7b20d4] text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.35)] transition-all duration-200 hover:scale-[1.02]">
        <span>{{ $ctaText }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
        </svg>
      </a>
    </div>

  </div>
</section>
