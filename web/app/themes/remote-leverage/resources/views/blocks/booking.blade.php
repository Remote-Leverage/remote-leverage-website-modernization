<section id="booking-wizard" class="relative py-16 sm:py-24 bg-bg-light overflow-hidden">
  {{-- Background glow accents --}}
  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-brand-purple/10 rounded-full blur-3xl pointer-events-none -z-10"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Block Header --}}
    <div class="text-center max-w-2xl mx-auto mb-12">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Reserve Your Strategy Session
      </span>
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold font-display text-brand-hero tracking-tight mt-3">
        {{ $headline }}
      </h2>
      @if ($subheadline)
        <p class="text-text-muted text-sm sm:text-base mt-3 leading-relaxed">
          {{ $subheadline }}
        </p>
      @endif
    </div>

    {{-- Embed Reactive Livewire Booking Wizard --}}
    <div>
      <livewire:booking.multistep-booking-wizard :roleNeeded="$defaultRole" />
    </div>

    {{-- Trust Badging --}}
    @if ($showTrustBadges)
      <div class="mt-12 pt-8 border-t border-slate-200/60 flex flex-wrap items-center justify-center gap-6 sm:gap-10 text-xs text-text-slate font-semibold uppercase tracking-wider">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-brand-magenta" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          <span>Top 1% LatAm Talent</span>
        </div>
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-brand-magenta" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          <span>6-Month Free Replacement</span>
        </div>
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-brand-magenta" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          <span>No Long-Term Contracts</span>
        </div>
      </div>
    @endif
  </div>
</section>
