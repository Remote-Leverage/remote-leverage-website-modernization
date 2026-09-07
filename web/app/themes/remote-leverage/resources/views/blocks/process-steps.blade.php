<section class="py-16 sm:py-24 bg-surface-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-16">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Proven 4-Step Framework
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

    {{-- Steps Grid with Connector --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 relative">
      @foreach ($steps as $index => $step)
        <div class="relative bg-bg-light rounded-card-lg border border-slate-200/80 p-6 sm:p-8 flex flex-col justify-between hover:shadow-lg transition-all duration-300">
          
          <div>
            {{-- Big Step Number & Badge --}}
            <div class="flex items-center justify-between mb-6">
              <span class="text-3xl sm:text-4xl font-extrabold font-display text-step-num">
                {{ $step['step_number'] ?? sprintf('%02d', $index + 1) }}
              </span>
              <div class="w-8 h-8 rounded-full bg-brand-purple/10 text-brand-purple flex items-center justify-center font-bold text-xs">
                {{ $index + 1 }}
              </div>
            </div>

            {{-- Step Title --}}
            <h3 class="text-lg font-bold font-display text-brand-hero mb-2 leading-snug">
              {{ $step['title'] }}
            </h3>

            {{-- Step Description --}}
            <p class="text-text-muted text-xs sm:text-sm leading-relaxed">
              {!! nl2br(e($step['description'])) !!}
            </p>
          </div>

          {{-- Optional Step CTA --}}
          @if (! empty($step['cta_label']) && ! empty($step['cta_url']))
            <div class="pt-6 mt-6 border-t border-slate-200/60">
              <a
                href="{{ $step['cta_url'] }}"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-magenta hover:text-brand-magenta-hover transition"
              >
                <span>{{ $step['cta_label'] }}</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
              </a>
            </div>
          @endif

        </div>
      @endforeach
    </div>
  </div>
</section>
