<section class="py-16 sm:py-24 bg-bg-light">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    {{-- Header --}}
    <div class="text-center max-w-3xl mx-auto mb-16">
      <span class="px-3.5 py-1 rounded-pill bg-brand-purple/10 text-brand-purple text-xs font-bold uppercase tracking-wider">
        Verified Global Roles
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

    {{-- Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
      @foreach ($cards as $card)
        <div class="group relative rounded-card-lg bg-surface-white border border-slate-200/80 hover:border-brand-purple/50 shadow-card hover:shadow-2xl transition-all duration-300 flex flex-col justify-between overflow-hidden">
          
          {{-- Card Visual / Top Dome Area --}}
          <div class="relative h-48 bg-gradient-to-br from-brand-midnight via-brand-navy to-brand-dark-violet overflow-hidden flex items-center justify-center p-6">
            {{-- Background image if set --}}
            @if (! empty($card['image']))
              <img 
                src="{{ $card['image'] }}" 
                alt="{{ $card['subtitle'] }}" 
                class="absolute inset-0 w-full h-full object-cover opacity-35 group-hover:scale-105 transition-transform duration-500" 
              />
            @endif

            {{-- Floating Badges --}}
            <div class="absolute top-4 left-4 right-4 flex items-center justify-between z-10">
              <span class="px-2.5 py-1 rounded-pill bg-white/15 backdrop-blur-md text-purple-200 text-2xs font-bold uppercase tracking-wider border border-white/20">
                {{ $card['title'] }}
              </span>

              @if (! empty($card['enable_verified_badge']))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-pill bg-emerald-500/20 backdrop-blur-md text-emerald-300 text-2xs font-bold border border-emerald-400/30">
                  <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                  <span>Top 1% Vetted</span>
                </span>
              @endif
            </div>

            {{-- Role Big Display --}}
            <div class="relative z-10 text-center">
              <h3 class="text-2xl font-bold font-display text-white group-hover:text-purple-200 transition">
                {{ $card['subtitle'] }}
              </h3>
              @if (! empty($card['hourly_rate']))
                <div class="inline-block mt-2 px-3 py-1 rounded-pill bg-brand-magenta text-white text-xs font-extrabold shadow-md">
                  {{ $card['hourly_rate'] }}
                </div>
              @endif
            </div>
          </div>

          {{-- Card Body --}}
          <div class="p-6 space-y-4 flex-1 flex flex-col justify-between">
            <p class="text-text-muted text-xs sm:text-sm leading-relaxed">
              {{ $card['description'] }}
            </p>

            {{-- Skills Tags --}}
            @if (! empty($card['skills_list']))
              <div class="pt-2">
                <div class="text-2xs uppercase tracking-wider font-semibold text-text-slate mb-2">Core Competencies:</div>
                <div class="flex flex-wrap gap-1.5">
                  @foreach (explode(',', $card['skills_list']) as $skill)
                    <span class="px-2 py-0.5 rounded-badge bg-slate-100 text-text-body text-2xs font-medium">
                      {{ trim($skill) }}
                    </span>
                  @endforeach
                </div>
              </div>
            @endif

            {{-- Worked At / Alumni Section --}}
            @if (! empty($card['enable_worked_at']))
              <div class="pt-4 border-t border-slate-100 flex items-center justify-between text-2xs text-text-slate">
                <span>{{ $card['worked_at_text'] ?: 'Alumni experience:' }}</span>
                @if (! empty($card['worked_at_logo']))
                  <img src="{{ $card['worked_at_logo'] }}" alt="Company" class="h-4 object-contain" />
                @else
                  <span class="font-bold text-text-body">Fortune 500 & Unicorns</span>
                @endif
              </div>
            @endif
          </div>

          {{-- Card Footer CTA --}}
          <div class="p-4 bg-slate-50/70 border-t border-slate-100">
            <a
              href="#booking-wizard"
              class="w-full py-2.5 rounded-cta bg-brand-purple/10 hover:bg-brand-purple text-brand-purple hover:text-white text-xs font-bold transition flex items-center justify-center gap-2 group-hover:bg-brand-purple group-hover:text-white"
            >
              <span>Request {{ $card['subtitle'] }} Candidate</span>
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
          </div>

        </div>
      @endforeach
    </div>
  </div>
</section>
