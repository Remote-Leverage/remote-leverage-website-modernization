{{-- Production's "Skip the Hiring Headache" band: a light #F4F6FC section, a centred
     heading pair, then ONE full-width white card holding the two sides with the long
     pink Union.svg arrow between them, and a pink pill CTA beneath.

     Measured off production (1440px): card 1320px wide, radius 10px, padding 30px, no
     border or shadow; side titles h3 42px/500/48px black; body lines 15px/400/20px black
     with no gap between them; arrow 104x30; CTA #F90066, radius 100px.

     The eyebrow pills are optional fields and are empty by default — production shows
     none, and rendering them was what made this section read as a generic comparison. --}}
@php
  use App\Support\BlockDefaults;

  $unionSvg = BlockDefaults::hireVaImg('Union.svg');
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    @if (! empty($headline) || ! empty($subheadline))
      <div class="text-center max-w-3xl mx-auto mb-10 sm:mb-12">
        @if (! empty($headline))
          <h2 class="text-3xl sm:text-4xl lg:text-[42px] font-bold font-display text-black tracking-tight leading-[1.1] mb-3">
            {!! $headline !!}
          </h2>
        @endif
        @if (! empty($subheadline))
          <p class="text-base sm:text-lg lg:text-xl text-black font-normal leading-snug">
            {{ $subheadline }}
          </p>
        @endif
      </div>
    @endif

    {{-- Single unified comparison card --}}
    <div class="bg-white rounded-[10px] p-6 sm:p-8 lg:p-[30px] mb-10 sm:mb-12">
      <div class="grid grid-cols-1 md:grid-cols-11 gap-8 md:gap-6 items-center">

        {{-- Left: hiring on your own --}}
        <div class="md:col-span-5 text-center md:text-left">
          @if (! empty($card1Image))
            <img src="{{ $card1Image }}" alt="" loading="lazy" decoding="async" class="h-10 w-auto max-w-40 object-contain mb-4 mx-auto md:mx-0">
          @endif
          @if (! empty($card1Pill))
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider mb-4">
              <span>{{ $card1Pill }}</span>
            </div>
          @endif
          <h3 class="font-display text-3xl sm:text-4xl lg:text-[42px] font-medium text-black tracking-tight leading-[1.14] mb-4">
            {{ $card1Title }}
          </h3>
          <p class="text-black text-[15px] leading-[20px]">{{ $card1Line1 }}</p>
          @if (! empty($card1Line2))
            <p class="text-black text-[15px] leading-[20px]">{{ $card1Line2 }}</p>
          @endif
        </div>

        {{-- Centre: the long pink arrow --}}
        <div class="md:col-span-1 flex items-center justify-center">
          <img src="{{ $unionSvg }}"
               alt=""
               width="104"
               height="30"
               loading="lazy"
               decoding="async"
               class="w-[104px] max-w-full h-auto object-contain rotate-90 md:rotate-0">
        </div>

        {{-- Right: hiring with Remote Leverage --}}
        <div class="md:col-span-5 text-center md:text-left md:pl-4">
          @if (! empty($card2Image))
            <img src="{{ $card2Image }}" alt="" loading="lazy" decoding="async" class="h-10 w-auto max-w-40 object-contain mb-4 mx-auto md:mx-0">
          @endif
          @if (! empty($card2Pill))
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-status-success text-xs font-bold uppercase tracking-wider mb-4">
              <span>{{ $card2Pill }}</span>
            </div>
          @endif
          <h3 class="font-display text-3xl sm:text-4xl lg:text-[42px] font-medium text-black tracking-tight leading-[1.14] mb-4">
            {{ $card2Title }}
          </h3>
          <p class="text-black text-[15px] leading-[20px]">{{ $card2Line1 }}</p>
          @if (! empty($card2Line2))
            <p class="text-black text-[15px] leading-[20px]">{{ $card2Line2 }}</p>
          @endif
        </div>

      </div>
    </div>

    {{-- CTA --}}
    <div class="flex justify-center">
      <a href="{{ $ctaUrl }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-[#F90066] hover:bg-[#d60057] text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(249,0,102,0.35)] transition-all duration-200 hover:scale-[1.02]">
        <span>{{ $ctaText }}</span>
        <svg class="w-[22px] h-[22px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10"/>
          <path d="M10 8l4 4-4 4"/>
        </svg>
      </a>
    </div>

  </div>
</section>
