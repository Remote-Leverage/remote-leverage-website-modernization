@php
  use App\Support\BlockDefaults;

  $badgeImg = BlockDefaults::hireVaImg('Group-59-1-e1780958571501.png');
  $icon1 = BlockDefaults::hireVaImg('stash_arrows-switch.svg');
  $icon2 = BlockDefaults::hireVaImg('majesticons_file-line-3.svg');
  $icon3 = BlockDefaults::hireVaImg('material-symbols_person-check-rounded.svg');
@endphp

<section class="pt-12 sm:pt-16 lg:pt-20 pb-16 sm:pb-20 lg:pb-24 text-white relative overflow-hidden" style="background: radial-gradient(84.9% 75.5% at 65.45% 17.36%, #8A2BE2 0%, #250D4A 100%);">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">
      
      {{-- Left Column: Guarantee Details --}}
      <div class="lg:col-span-7 pt-4 sm:pt-6">
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-white tracking-tight leading-[1.1] mb-10">
          {{ $headline }}
        </h2>

        <div class="space-y-7 mb-10">
          {{-- Item 1 --}}
          <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
              <img src="{{ $icon1 }}" alt="" class="w-6 h-6 object-contain">
            </div>
            <div>
              <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                Not the right fit?
              </h3>
              <p class="text-white/80 text-sm sm:text-base leading-relaxed">
                Free replacement any time in the first 6 months, extended to 12.
              </p>
            </div>
          </div>

          {{-- Item 2 --}}
          <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
              <img src="{{ $icon2 }}" alt="" class="w-6 h-6 object-contain">
            </div>
            <div>
              <h3 class="text-lg sm:text-xl font-bold text-white mb-1">
                No long-term contracts.
              </h3>
              <p class="text-white/80 text-sm sm:text-base leading-relaxed">
                One flat fee, only if you hire.
              </p>
            </div>
          </div>

          {{-- Item 3 --}}
          <div class="flex items-start gap-4">
            <div class="w-11 h-11 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
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
             class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#8A2BE2] hover:bg-[#7b20d4] text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.5)] transition-all duration-300 hover:scale-[1.02]">
            <span>{{ $ctaText }}</span>
            <svg class="w-4 h-4 fill-none stroke-current" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
          </a>
        </div>
      </div>

      {{-- Right Column: 3D Medals Badge FLUSH to the bleeding top edge --}}
      <div class="lg:col-span-5 flex justify-center lg:justify-end -mt-12 sm:-mt-16 lg:-mt-20 pointer-events-none">
        <img src="{{ $badgeImg }}"
             alt="12-Month Replacement Guarantee Medals"
             width="578"
             height="545"
             loading="lazy"
             decoding="async"
             class="w-full max-w-sm sm:max-w-md lg:max-w-lg h-auto object-contain object-top drop-shadow-[0_24px_50px_rgba(0,0,0,0.4)]">
      </div>

    </div>
  </div>
</section>
