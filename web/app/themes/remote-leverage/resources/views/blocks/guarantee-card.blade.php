@php
  $badgeImg = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/Group-59-1-e1780958571501.png', 'https'));
  $icon1 = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/stash_arrows-switch.svg', 'https'));
  $icon2 = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/majesticons_file-line-3.svg', 'https'));
  $icon3 = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/material-symbols_person-check-rounded.svg', 'https'));
@endphp

<section class="py-16 sm:py-20 lg:py-24 text-white relative overflow-hidden" style="background: radial-gradient(84.9% 75.5% at 65.45% 17.36%, #8A2BE2 0%, #250D4A 100%);">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
      
      {{-- Left Column: Guarantee Details --}}
      <div class="lg:col-span-7">
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black font-display text-white tracking-tight leading-tight mb-10">
          {{ $headline }}
        </h2>

        <div class="space-y-7 mb-10">
          {{-- Item 1 --}}
          <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0 mt-0.5">
              <img src="{{ $icon1 }}" alt="" class="w-6 h-6 object-contain">
            </div>
            <div>
              <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                Not the right fit?
              </h3>
              <p class="text-white/80 text-sm sm:text-base leading-relaxed">
                Free replacement any time in the first 6 months, extended to 12
              </p>
            </div>
          </div>

          {{-- Item 2 --}}
          <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0 mt-0.5">
              <img src="{{ $icon2 }}" alt="" class="w-6 h-6 object-contain">
            </div>
            <div>
              <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                No long-term contracts.
              </h3>
              <p class="text-white/80 text-sm sm:text-base leading-relaxed">
                One flat fee, only if you hire
              </p>
            </div>
          </div>

          {{-- Item 3 --}}
          <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center shrink-0 mt-0.5">
              <img src="{{ $icon3 }}" alt="" class="w-6 h-6 object-contain">
            </div>
            <div>
              <h3 class="text-lg sm:text-xl font-bold text-white">
                A dedicated manager helps with onboarding, training, and tracking
              </h3>
            </div>
          </div>
        </div>

        {{-- CTA Button --}}
        <div>
          <a href="{{ $ctaUrl }}"
             class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-brand-purple hover:bg-brand-purple/90 text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.5)] transition-all duration-300 hover:scale-[1.02]">
            <svg class="w-5 h-5 fill-white" viewBox="0 0 24 24">
              <path d="M11.7871 0C18.287 0.000243557 23.573 5.28724 23.5732 11.7871C23.573 18.287 18.287 23.573 11.7871 23.5732C5.28725 23.573 0.000243552 18.287 0 11.7871C0.000233875 5.28724 5.28724 0.00023695 11.7871 0ZM11.7871 1.98535C6.38376 1.98559 1.98559 6.38376 1.98535 11.7871C1.9856 17.1905 6.38377 21.5877 11.7871 21.5879C17.1904 21.5876 21.5876 17.1904 21.5879 11.7871C21.5877 6.38376 17.1905 1.9856 11.7871 1.98535ZM9.71387 5.17578L15.7314 11.4043L16.2773 11.9687L15.7314 12.5342L10.0654 18.3994L9.48242 19.0029L8.89746 18.3994L8.64355 18.1377L8.09668 17.5732L8.64258 17.0078L13.5098 11.9687L8.29102 6.56738L7.74609 6.00195L8.29102 5.4375L8.54492 5.17578L9.12891 4.57031L9.71387 5.17578ZM1.22754 12.8711C1.25633 13.1536 1.29901 13.4324 1.34961 13.708C1.29803 13.427 1.25547 13.1426 1.22656 12.8545L1.22754 12.8711ZM12.8545 1.22656C13.1426 1.25547 13.427 1.29803 13.708 1.34961C13.4324 1.29901 13.1536 1.25633 12.8711 1.22754L12.8545 1.22656ZM10.7461 1.22363L10.7031 1.22754C10.6463 1.23333 10.5897 1.24035 10.5332 1.24707C10.604 1.23864 10.6749 1.23065 10.7461 1.22363Z"/>
            </svg>
            <span>{{ $ctaText }}</span>
          </a>
        </div>
      </div>

      {{-- Right Column: 3D Medals Badge --}}
      <div class="lg:col-span-5 flex justify-center lg:justify-end">
        <img src="{{ $badgeImg }}"
             alt="12-Month Replacement Guarantee"
             width="578"
             height="545"
             loading="lazy"
             decoding="async"
             class="w-full max-w-120 h-auto object-contain drop-shadow-[0_20px_50px_rgba(0,0,0,0.3)]">
      </div>

    </div>
  </div>
</section>
