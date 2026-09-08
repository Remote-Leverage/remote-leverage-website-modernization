@php
  $eyebrowImg = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/Group-207.png', 'https'));
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-bg-light">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Eyebrow & Headline --}}
    <div class="max-w-3xl mb-12 sm:mb-16">
      @if (!empty($eyebrow))
        <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-white border border-black/5 shadow-xs mb-5">
          <img src="{{ $eyebrowImg }}" alt="Candidate Avatars" class="h-6 w-auto" loading="lazy" decoding="async">
          <span class="text-xs sm:text-sm font-bold text-brand-hero tracking-wide">{{ $eyebrow }}</span>
        </div>
      @endif

      @if (!empty($headline))
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black font-display text-brand-hero tracking-tight leading-[1.08]">
          {!! $headline !!}
        </h2>
      @endif
    </div>

    {{-- Grid of 8 Role Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card">
      @foreach ($cards as $card)
        <div class="group bg-white rounded-card p-6 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)] hover:border-brand-purple/20 transition-all duration-300 flex flex-col justify-between">
          <div>
            <h3 class="font-display text-xl font-bold text-brand-hero tracking-tight leading-snug">
              {{ $card['title'] }}
            </h3>
            <p class="text-sm text-text-muted mt-2 leading-relaxed">
              {{ $card['desc'] }}
            </p>
          </div>

          @if (!empty($card['img']))
            <div class="mt-5 pt-2 flex items-center justify-center overflow-hidden rounded-xl bg-slate-50/80 min-h-37.5 relative">
              <img src="{{ $card['img'] }}"
                   alt="{{ strip_tags($card['title']) }}"
                   loading="lazy"
                   decoding="async"
                   class="max-h-35 w-auto object-contain transition-transform duration-500 group-hover:scale-105">
            </div>
          @endif
        </div>
      @endforeach
    </div>

    {{-- Bottom CTA Button --}}
    @if (!empty($ctaText))
      <div class="mt-12 sm:mt-16 flex justify-center">
        <a href="{{ $ctaUrl }}" class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-brand-purple hover:bg-brand-purple/90 text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.35)] transition-all duration-200 hover:scale-[1.02]">
          <span>{{ $ctaText }}</span>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
          </svg>
        </a>
      </div>
    @endif

  </div>
</section>
