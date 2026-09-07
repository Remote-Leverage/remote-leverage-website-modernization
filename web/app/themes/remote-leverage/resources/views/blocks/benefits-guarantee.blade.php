<section class="py-16 sm:py-24 bg-bg-light">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Header --}}
    <div class="max-w-3xl mb-16">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Uncompromising Quality
      </span>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-display text-brand-hero tracking-tight mt-3">
        {{ $headline }}
      </h2>
      @if ($description)
        <p class="text-text-muted text-sm sm:text-base mt-3 leading-relaxed">
          {{ $description }}
        </p>
      @endif
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
      
      {{-- Guarantee Card (Left Column - 5 cols) --}}
      <div class="lg:col-span-5 rounded-card-lg bg-gradient-to-br from-brand-midnight via-brand-navy to-brand-dark-violet text-white p-8 sm:p-10 shadow-glow-purple border border-purple-900/40 flex flex-col justify-between relative overflow-hidden">
        <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-brand-magenta/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="space-y-6 relative z-10">
          {{-- Guarantee Shield Icon --}}
          <div class="w-14 h-14 rounded-card bg-white/10 border border-white/20 flex items-center justify-center text-brand-magenta">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </div>

          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-purple-300">Peace of Mind Guarantee</span>
            <h3 class="text-2xl sm:text-3xl font-bold font-display text-white mt-1 leading-tight">
              {{ $guaranteeTitle }}
            </h3>
          </div>

          <p class="text-slate-300 text-sm leading-relaxed">
            {{ $guaranteeDesc }}
          </p>
        </div>

        <div class="pt-8 relative z-10">
          <a
            href="{{ $ctaUrl }}"
            class="inline-flex items-center justify-center gap-2 w-full py-3.5 px-6 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white text-sm font-bold shadow-lg transition transform hover:-translate-y-0.5 cursor-pointer"
          >
            <span>{{ $ctaText }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>
      </div>

      {{-- Benefits Grid (Right Column - 7 cols) --}}
      <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-6">
        @foreach ($benefits as $benefit)
          <div class="bg-surface-white rounded-card-lg border border-slate-200/80 p-6 sm:p-7 shadow-card hover:shadow-xl hover:border-brand-purple/40 transition-all duration-300 flex flex-col justify-between">
            <div class="space-y-3">
              <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
              </div>

              <h4 class="text-base sm:text-lg font-bold font-display text-brand-hero">
                {{ $benefit['title'] }}
              </h4>

              <p class="text-text-muted text-xs sm:text-sm leading-relaxed">
                {{ $benefit['description'] }}
              </p>
            </div>
          </div>
        @endforeach
      </div>

    </div>

  </div>
</section>
