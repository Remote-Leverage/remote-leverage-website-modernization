@php
  use App\Support\BlockDefaults;

  $globeUrl = BlockDefaults::hireVaImg('globe-1.png');
  $cards = [
    [
      'title' => 'No Recurring Fees - Hire Direct',
      'desc' => 'Pay once when you hire. No monthly markups, no hidden costs, no contracts keeping you tied down.',
      'icon' => '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    ],
    [
      'title' => 'Fluent English',
      'desc' => 'Every candidate is vetted for professional English proficiency, clear communication, and seamless timezone overlap.',
      'icon' => '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    ],
    [
      'title' => '30% Discount on Future Hires',
      'desc' => 'Scaling your team? Enjoy an automatic 30% discount on placement fees for every subsequent hire.',
      'icon' => '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
    ],
    [
      'title' => 'No Contracts',
      'desc' => 'You hold all the leverage. You hire directly onto your own payroll or contractor setup with zero lock-ins.',
      'icon' => '<svg class="w-6 h-6 text-brand-purple" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    ],
  ];
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Section Heading --}}
    <div class="max-w-3xl mb-12 sm:mb-16">
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight leading-[1.08]">
        Why hire through<br class="hidden sm:inline"> Remote Leverage?
      </h2>
    </div>

    {{-- 2-Column Layout: Left Dark Card with Bleeding Globe + Right Stack of 4 Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
      
      {{-- Left Column: Massive Dark Purple Card with Bleeding Globe --}}
      <div class="lg:col-span-5 bg-[#250D4A] rounded-card-lg p-8 sm:p-10 text-white relative overflow-hidden shadow-lg flex flex-col justify-between min-h-[480px]">
        <div class="relative z-10">
          {{-- 5-Star Rating --}}
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 backdrop-blur-xs mb-6">
            <div class="flex items-center gap-1 text-[#FFD700]">
              @for ($i = 0; $i < 5; $i++)
                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              @endfor
            </div>
            <span class="text-xs font-bold text-white tracking-wide">5.0 Star Rating</span>
          </div>

          {{-- Value Statement --}}
          <h3 class="text-2xl sm:text-3xl font-bold font-display text-white leading-snug tracking-tight mb-4">
            We've helped more than 2,000 businesses hire top talent across LatAm, the Caribbean and the EU.
          </h3>
          <p class="text-white/80 text-sm leading-relaxed max-w-md">
            Tap into vetted international professionals who integrate directly into your operations, saving up to 70% compared to local hires.
          </p>
        </div>

        {{-- Globe Graphic Flush to Bottom Edge (Bleed) --}}
        <div class="mt-8 -mb-10 sm:-mb-12 -mx-4 flex justify-center pointer-events-none relative z-0">
          <img src="{{ $globeUrl }}"
               alt="Global Talent Distribution"
               width="546"
               height="265"
               loading="lazy"
               decoding="async"
               class="w-full max-w-sm h-auto object-contain object-bottom drop-shadow-2xl">
        </div>
      </div>

      {{-- Right Column: Single Vertical Stack of 4 Horizontal Cards --}}
      <div class="lg:col-span-7 flex flex-col gap-4 sm:gap-5 justify-between">
        @foreach ($cards as $card)
          <div class="bg-white rounded-card-md p-6 sm:p-7 border border-black/5 shadow-xs hover:shadow-sm hover:border-brand-purple/20 transition-all duration-200 flex items-start gap-5">
            <div class="w-12 h-12 rounded-xl bg-brand-purple/10 flex items-center justify-center shrink-0">
              {!! $card['icon'] !!}
            </div>
            <div>
              <h4 class="text-lg sm:text-xl font-bold font-display text-brand-hero tracking-tight">
                {{ $card['title'] }}
              </h4>
              <p class="text-sm text-text-muted mt-1.5 leading-relaxed">
                {{ $card['desc'] }}
              </p>
            </div>
          </div>
        @endforeach
      </div>

    </div>

  </div>
</section>
