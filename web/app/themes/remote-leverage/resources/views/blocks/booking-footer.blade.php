@php
  $union5Url = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/Union-5.png', 'https'));
@endphp

<section id="booking-footer" class="py-16 sm:py-20 lg:py-24 text-white relative overflow-hidden bg-[#250D4A]" style="background-image: url('{{ $union5Url }}'); background-position: center; background-repeat: no-repeat; background-size: cover;">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
      
      {{-- Left Column: Process Steps --}}
      <div class="lg:col-span-6 xl:col-span-7">
        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black font-display text-white tracking-tight leading-tight mb-10">
          {{ $headline }}
        </h2>

        <div class="space-y-6">
          {{-- Step 1 --}}
          <div class="flex items-center gap-4">
            <span class="w-8 h-8 rounded-full bg-brand-magenta text-white font-bold text-sm flex items-center justify-center shrink-0">1</span>
            <span class="text-base sm:text-lg font-medium text-white">Pick a time on the next screen</span>
          </div>

          {{-- Step 2 --}}
          <div class="flex items-center gap-4">
            <span class="w-8 h-8 rounded-full bg-brand-magenta text-white font-bold text-sm flex items-center justify-center shrink-0">2</span>
            <span class="text-base sm:text-lg font-medium text-white">15-min Zoom call to map out the role and budget</span>
          </div>

          {{-- Step 3 --}}
          <div class="flex items-center gap-4">
            <span class="w-8 h-8 rounded-full bg-brand-magenta text-white font-bold text-sm flex items-center justify-center shrink-0">3</span>
            <span class="text-base sm:text-lg font-medium text-white">Interview pre-vetted candidates within 72 hrs</span>
          </div>
        </div>
      </div>

      {{-- Right Column: Interactive Scheduling Wizard --}}
      <div class="lg:col-span-6 xl:col-span-5">
        <div class="bg-white/5 backdrop-blur-md rounded-card-lg p-6 sm:p-8 border border-white/10 shadow-[0_12px_40px_rgba(0,0,0,0.3)]">
          <livewire:booking.multistep-booking-wizard />
        </div>
      </div>

    </div>
  </div>
</section>
