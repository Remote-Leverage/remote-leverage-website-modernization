@php
  $union5Url = asset('images/hire-va-4/Union-5.png');
@endphp

<section id="booking-footer" class="py-16 sm:py-20 lg:py-24 text-white relative overflow-hidden min-h-[90vh] flex items-center" style="background: radial-gradient(84.9% 75.5% at 65.45% 17.36%, #8A2BE2 0%, #250D4A 100%);">
  {{-- Subtle Background Glow / Texture --}}
  <div class="absolute inset-0 pointer-events-none opacity-20 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px]"></div>

  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
      
      {{-- Left Column: Value Proposition & Steps --}}
      <div class="lg:col-span-6 xl:col-span-6">
        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-white/10 border border-white/20 text-white text-xs sm:text-sm font-semibold mb-6 backdrop-blur-xs shadow-xs">
          <span class="w-2 h-2 rounded-full bg-[#00D67D] animate-pulse"></span>
          <span>Fast 72-Hour Matching</span>
        </div>

        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-white tracking-tight leading-[1.08] mb-8">
          {{ $headline }}
        </h2>

        <div class="space-y-6 max-w-lg">
          {{-- Step 1 --}}
          <div class="flex items-start gap-4">
            <span class="w-8 h-8 rounded-full bg-[#F8248A] text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs">1</span>
            <div>
              <h3 class="text-base sm:text-lg font-bold text-white">Pick a time on the next screen</h3>
              <p class="text-sm text-white/80 mt-0.5">Select a 15-minute slot that fits your schedule.</p>
            </div>
          </div>

          {{-- Step 2 --}}
          <div class="flex items-start gap-4">
            <span class="w-8 h-8 rounded-full bg-[#F8248A] text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs">2</span>
            <div>
              <h3 class="text-base sm:text-lg font-bold text-white">15-min Zoom call to map out the role and budget</h3>
              <p class="text-sm text-white/80 mt-0.5">We discuss requirements, workflows, and hours.</p>
            </div>
          </div>

          {{-- Step 3 --}}
          <div class="flex items-start gap-4">
            <span class="w-8 h-8 rounded-full bg-[#F8248A] text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-xs">3</span>
            <div>
              <h3 class="text-base sm:text-lg font-bold text-white">Interview pre-vetted candidates within 72 hrs</h3>
              <p class="text-sm text-white/80 mt-0.5">Hire direct with a 12-month replacement guarantee.</p>
            </div>
          </div>
        </div>
      </div>

      {{-- Right Column: Authentic Glassmorphism Booking Wizard --}}
      <div class="lg:col-span-6 xl:col-span-6">
        <livewire:booking.multistep-booking-wizard skin="glass" />
      </div>

    </div>
  </div>
</section>
