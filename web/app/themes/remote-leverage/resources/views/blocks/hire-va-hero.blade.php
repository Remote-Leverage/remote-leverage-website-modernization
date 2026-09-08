<section class="relative overflow-hidden bg-bg-light py-12 sm:py-16 lg:py-20">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
      
      {{-- Left Column: Value Proposition & CTA --}}
      <div class="lg:col-span-6 xl:col-span-7">
        {{-- Trust Eyebrow Pill --}}
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-brand-purple/10 text-brand-purple text-xs sm:text-sm font-semibold mb-6">
          <span class="w-2 h-2 rounded-full bg-status-success animate-pulse"></span>
          <span>{!! $badgeText !!}</span>
        </div>

        {{-- Main H1 --}}
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black font-display text-brand-hero tracking-tight leading-[1.08] mb-8">
          {!! $headline !!}
        </h1>

        {{-- 6-item Checklist Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mb-10">
          @php
            $checklist = [
              'Interview Before You Hire',
              'Hire Direct - No Middleman',
              'No contracts',
              'Hire Within 72 Hours',
              'Fluent English',
              '30% Discount on Future Hires',
            ];
          @endphp
          @foreach ($checklist as $item)
            <div class="flex items-center gap-2.5 text-text-primary text-sm sm:text-base font-medium">
              <span class="w-5 h-5 rounded-full bg-status-success/15 text-status-success flex items-center justify-center shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>
              </span>
              <span>{{ $item }}</span>
            </div>
          @endforeach
        </div>

        {{-- Live Call CTA --}}
        <div class="pt-2">
          <livewire:scheduling.instant-live-call-button buttonSize="hero" />
        </div>
      </div>

      {{-- Right Column: Interactive Booking Card --}}
      <div class="lg:col-span-6 xl:col-span-5">
        <div class="bg-white rounded-card-lg p-6 sm:p-8 border border-black/5 shadow-[0_12px_40px_rgba(0,0,0,0.06)]">
          <div class="mb-5 pb-4 border-b border-black/5">
            <h2 class="text-xl sm:text-2xl font-bold font-display text-brand-hero tracking-tight">
              {{ $bookingTitle }}
            </h2>
            <p class="text-xs sm:text-sm text-text-muted mt-1">
              {{ $bookingSubtitle }}
            </p>
          </div>
          <livewire:booking.multistep-booking-wizard />
        </div>
      </div>

    </div>
  </div>
</section>
