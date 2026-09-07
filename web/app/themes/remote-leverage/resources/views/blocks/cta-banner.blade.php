<section class="py-16 sm:py-24 bg-surface-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="relative rounded-card-lg bg-gradient-to-r from-brand-midnight via-brand-navy to-brand-hero text-white p-8 sm:p-14 lg:p-16 shadow-glow-purple border border-purple-900/40 overflow-hidden">
      {{-- Ambient lights --}}
      <div class="absolute -right-24 -top-24 w-96 h-96 bg-brand-purple/25 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-24 -bottom-24 w-96 h-96 bg-brand-magenta/20 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        
        {{-- Text & CTA Button (Left Column - 7 cols) --}}
        <div class="lg:col-span-7 space-y-6">
          <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-pill bg-white/10 text-purple-200 text-xs font-bold uppercase tracking-wider border border-white/10">
            Scale Smarter
          </span>

          <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-display tracking-tight text-white leading-tight">
            {{ $headline }}
          </h2>

          @if ($subheadline)
            <p class="text-slate-300 text-sm sm:text-base leading-relaxed max-w-xl">
              {{ $subheadline }}
            </p>
          @endif

          <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
            <a
              href="{{ $ctaUrl }}"
              class="px-8 py-4 rounded-cta bg-brand-magenta hover:bg-brand-magenta-hover text-white font-bold text-sm sm:text-base shadow-xl transition transform hover:-translate-y-0.5 inline-flex items-center justify-center gap-2"
            >
              <span>{{ $ctaText }}</span>
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>

            <div class="text-xs text-slate-400 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-status-success animate-ping"></span>
              <span>15-min call &bull; No commitment</span>
            </div>
          </div>
        </div>

        {{-- Globe Graphic (Right Column - 5 cols) --}}
        <div class="lg:col-span-5 flex items-center justify-center lg:justify-end">
          <div class="relative w-64 h-64 sm:w-80 sm:h-80 flex items-center justify-center">
            @if ($globeImage)
              <img 
                src="{{ $globeImage }}" 
                alt="Global Talent Map" 
                class="w-full h-full object-contain filter drop-shadow-2xl animate-pulse" 
              />
            @else
              {{-- Fallback SVG Globe Network --}}
              <div class="w-full h-full rounded-full border border-purple-500/20 bg-brand-purple/10 flex items-center justify-center relative p-6">
                <svg class="w-44 h-44 text-purple-300/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                  <div class="px-3.5 py-1.5 rounded-pill bg-brand-midnight/90 backdrop-blur-md border border-purple-500/40 text-2xs font-bold text-white shadow-xl">
                    Americas Network
                  </div>
                </div>
              </div>
            @endif
          </div>
        </div>

      </div>
    </div>
  </div>
</section>
