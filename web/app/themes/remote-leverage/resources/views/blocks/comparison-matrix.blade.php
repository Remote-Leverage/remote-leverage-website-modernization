@php
  $unionSvg = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/Union.svg', 'https'));
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-bg-light">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-14 sm:mb-16">
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black font-display text-brand-hero tracking-tight mb-3">
        {{ $headline }}
      </h2>
      <p class="text-base sm:text-lg text-text-muted font-medium">
        {{ $subheadline }}
      </p>
    </div>

    {{-- Side-by-Side Comparison Container --}}
    <div class="flex flex-col md:flex-row items-center justify-center gap-6 lg:gap-8 max-w-5xl mx-auto mb-14">
      
      {{-- Card 1: Hiring on your own --}}
      <div class="w-full md:w-1/2 bg-white rounded-card-lg p-8 sm:p-10 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] text-center md:text-left flex flex-col justify-center min-h-55">
        <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight mb-4">
          Hiring on your own
        </h3>
        <p class="text-text-muted text-sm sm:text-base leading-relaxed mb-3">
          4 to 8 weeks of posting, screening, and interviewing.
        </p>
        <p class="text-text-muted text-sm sm:text-base leading-relaxed">
          Payroll, taxes, and compliance all on you.
        </p>
      </div>

      {{-- Center Graphic: Arrow / Union --}}
      <div class="shrink-0 flex items-center justify-center py-2 md:py-0">
        <img src="{{ $unionSvg }}"
             alt=""
             width="104"
             height="30"
             loading="lazy"
             decoding="async"
             class="w-20 md:w-24 h-auto object-contain rotate-90 md:rotate-0">
      </div>

      {{-- Card 2: Hiring with Remote Leverage --}}
      <div class="w-full md:w-1/2 bg-white rounded-card-lg p-8 sm:p-10 border-2 border-brand-purple/30 shadow-[0_8px_30px_rgba(138,43,226,0.08)] text-center md:text-left flex flex-col justify-center min-h-55">
        <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-purple tracking-tight mb-4">
          Hiring with Remote Leverage
        </h3>
        <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed mb-3">
          Interview the top 1% in 72 hours.
        </p>
        <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed">
          We handle payroll, compliance, and onboarding.
        </p>
      </div>

    </div>

    {{-- CTA Button --}}
    <div class="flex justify-center">
      <a href="{{ $ctaUrl }}"
         class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-brand-purple hover:bg-brand-purple/90 text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.35)] transition-all duration-300 hover:scale-[1.02]">
        <svg class="w-5 h-5 fill-white" viewBox="0 0 24 24">
          <path d="M11.7871 0C18.287 0.000243557 23.573 5.28724 23.5732 11.7871C23.573 18.287 18.287 23.573 11.7871 23.5732C5.28725 23.573 0.000243552 18.287 0 11.7871C0.000233875 5.28724 5.28724 0.00023695 11.7871 0ZM11.7871 1.98535C6.38376 1.98559 1.98559 6.38376 1.98535 11.7871C1.9856 17.1905 6.38377 21.5877 11.7871 21.5879C17.1904 21.5876 21.5876 17.1904 21.5879 11.7871C21.5877 6.38376 17.1905 1.9856 11.7871 1.98535ZM9.71387 5.17578L15.7314 11.4043L16.2773 11.9687L15.7314 12.5342L10.0654 18.3994L9.48242 19.0029L8.89746 18.3994L8.64355 18.1377L8.09668 17.5732L8.64258 17.0078L13.5098 11.9687L8.29102 6.56738L7.74609 6.00195L8.29102 5.4375L8.54492 5.17578L9.12891 4.57031L9.71387 5.17578ZM1.22754 12.8711C1.25633 13.1536 1.29901 13.4324 1.34961 13.708C1.29803 13.427 1.25547 13.1426 1.22656 12.8545L1.22754 12.8711ZM12.8545 1.22656C13.1426 1.25547 13.427 1.29803 13.708 1.34961C13.4324 1.29901 13.1536 1.25633 12.8711 1.22754L12.8545 1.22656ZM10.7461 1.22363L10.7031 1.22754C10.6463 1.23333 10.5897 1.24035 10.5332 1.24707C10.604 1.23864 10.6749 1.23065 10.7461 1.22363Z"/>
        </svg>
        <span>{{ $ctaText }}</span>
      </a>
    </div>

  </div>
</section>
