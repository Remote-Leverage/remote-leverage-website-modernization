@php
  use App\Support\BlockDefaults;

  $unionSvg = BlockDefaults::hireVaImg('Union.svg');
@endphp

@if (($layout ?? 'stacked') === 'split')

  {{-- Split layout: mirrors production's comparison pages — two photo cards on the
       left, section heading and CTA on the right. Measurements taken from prod's
       computed styles (46/-1.44px heading, 15/20/-0.45px card copy, 10px radius). --}}
  <section class="bg-bg-light pt-14 pb-10 lg:pt-20 lg:pb-10">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col lg:flex-row lg:items-start gap-10 lg:gap-[50px]">

        <div class="w-full lg:w-1/2 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          @foreach ([
            ['img' => $card1Image, 'title' => $card1Title, 'line1' => $card1Line1, 'line2' => $card1Line2, 'icon' => 'asterisk'],
            ['img' => $card2Image, 'title' => $card2Title, 'line1' => $card2Line1, 'line2' => $card2Line2, 'icon' => 'check'],
          ] as $card)
            <div class="flex flex-col overflow-hidden rounded-[10px] bg-white">
              @if (! empty($card['img']))
                <img src="{{ $card['img'] }}" alt="" loading="lazy" decoding="async"
                     class="w-full h-[206px] object-cover">
              @endif

              <div class="flex flex-col gap-5 px-5 pt-2.5 pb-[30px]">
                <div class="flex items-start justify-between gap-3">
                  <h3 class="font-display text-xl font-bold tracking-[-0.6px] text-black">
                    {{ $card['title'] }}
                  </h3>

                  @if ($card['icon'] === 'check')
                    <svg class="mt-1 h-5 w-5 shrink-0 text-status-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                  @else
                    <svg class="mt-1 h-5 w-5 shrink-0 text-black" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path d="M12 2.25a.75.75 0 01.75.75v7.19l6.22-3.59a.75.75 0 11.75 1.3L13.5 11.5l6.22 3.59a.75.75 0 11-.75 1.3l-6.22-3.59V21a.75.75 0 01-1.5 0v-8.2l-6.22 3.59a.75.75 0 01-.75-1.3l6.22-3.59-6.22-3.6a.75.75 0 01.75-1.3l6.22 3.6V3a.75.75 0 01.75-.75z" />
                    </svg>
                  @endif
                </div>

                <div class="flex flex-col gap-2">
                  <p class="text-[15px] leading-5 tracking-[-0.45px] text-black">{{ $card['line1'] }}</p>
                  @if (! empty($card['line2']))
                    <p class="text-[15px] leading-5 tracking-[-0.45px] text-black">{{ $card['line2'] }}</p>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>

        <div class="w-full lg:w-1/2 flex flex-col gap-5">
          @if (! empty($headline))
            <h2 class="font-display text-3xl sm:text-4xl lg:text-[46px] lg:leading-[1.15] font-bold tracking-[-0.03em] lg:tracking-[-1.44px] text-black">
              {!! $headline !!}
            </h2>
          @endif

          @if (! empty($subheadline))
            <p class="text-lg lg:text-[20px] tracking-[-0.03em] lg:tracking-[-0.6px] text-black">
              {!! $subheadline !!}
            </p>
          @endif

          @if (! empty($ctaText))
            <div>
              <a href="{{ $ctaUrl }}"
                 class="inline-flex items-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep text-white font-bold text-base lg:text-[20px] tracking-[-0.6px] pl-[30px] pr-10 py-5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple focus-visible:ring-offset-2">
                {{ $ctaText }}
              </a>
            </div>
          @endif
        </div>

      </div>
    </div>
  </section>

@else

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Header --}}
    @if (! empty($headline) || ! empty($subheadline))
      <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-14">
        @if (! empty($headline))
          <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight mb-3">
            {!! $headline !!}
          </h2>
        @endif
        @if (! empty($subheadline))
          <p class="text-base sm:text-lg text-text-muted font-medium">
            {{ $subheadline }}
          </p>
        @endif
      </div>
    @endif

    {{-- Single Unified Comparison Card Box --}}
    <div class="max-w-4xl mx-auto bg-white rounded-card-lg p-8 sm:p-12 border border-black/5 shadow-[0_8px_30px_rgba(0,0,0,0.04)] mb-12">
      <div class="grid grid-cols-1 md:grid-cols-11 gap-6 items-center">
        
        {{-- Left: Hiring on your own --}}
        <div class="md:col-span-5 text-center md:text-left">
          @if (! empty($card1Image))
            <img src="{{ $card1Image }}" alt="" loading="lazy" decoding="async" class="h-10 w-auto max-w-40 object-contain mb-4 mx-auto md:mx-0">
          @endif
          @if (! empty($card1Pill))
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold uppercase tracking-wider mb-4">
              <span>{{ $card1Pill }}</span>
            </div>
          @endif
          <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight mb-3">
            {{ $card1Title }}
          </h3>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed mb-2">
            {{ $card1Line1 }}
          </p>
          @if (! empty($card1Line2))
            <p class="text-text-muted text-sm sm:text-base leading-relaxed">
              {{ $card1Line2 }}
            </p>
          @endif
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
          @if (! empty($card2Image))
            <img src="{{ $card2Image }}" alt="" loading="lazy" decoding="async" class="h-10 w-auto max-w-40 object-contain mb-4 mx-auto md:mx-0">
          @endif
          @if (! empty($card2Pill))
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-status-success text-xs font-bold uppercase tracking-wider mb-4">
              <span>{{ $card2Pill }}</span>
            </div>
          @endif
          <h3 class="text-xl sm:text-2xl font-bold font-display text-brand-purple tracking-tight mb-3">
            {{ $card2Title }}
          </h3>
          <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed mb-2">
            {{ $card2Line1 }}
          </p>
          @if (! empty($card2Line2))
            <p class="text-text-primary font-medium text-sm sm:text-base leading-relaxed">
              {{ $card2Line2 }}
            </p>
          @endif
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

@endif
