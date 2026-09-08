@php
  $globeUrl = esc_url(set_url_scheme(get_template_directory_uri() . '/public/images/hire-va-4/globe-1.png', 'https'));
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-bg-light">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Section Heading --}}
    <div class="mb-12 sm:mb-16">
      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black font-display text-brand-hero tracking-tight">
        {{ $headline }}
      </h2>
    </div>

    {{-- Grid: Left Big Proof Card + Right 2x2 Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-stretch">
      
      {{-- Left Card: Social Proof & Global Talent --}}
      <div class="lg:col-span-5 bg-white rounded-card-lg p-8 sm:p-10 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col justify-between">
        <div>
          {{-- 5-Star Rating --}}
          <div class="flex items-center gap-1.5 mb-6">
            @for ($i = 0; $i < 5; $i++)
              <svg class="w-5 h-5 fill-status-warning" viewBox="0 0 15 14">
                <path d="M7.33898 0L9.07142 5.08955H14.6777L10.1421 8.23507L11.8746 13.3246L7.33898 10.1791L2.80338 13.3246L4.53582 8.23507L0.000226974 5.08955H5.60653L7.33898 0Z"/>
              </svg>
            @endfor
          </div>

          {{-- Proof Headline --}}
          <h3 class="text-2xl sm:text-3xl font-extrabold font-display text-brand-hero leading-snug tracking-tight mb-6">
            {{ $proofTitle }}
          </h3>
        </div>

        {{-- Globe Illustration --}}
        <div class="mt-8 flex justify-center">
          <img src="{{ $globeUrl }}"
               alt="Global Talent Distribution"
               width="546"
               height="265"
               loading="lazy"
               decoding="async"
               class="w-full max-w-105 h-auto object-contain">
        </div>
      </div>

      {{-- Right: 2x2 Grid of Feature Benefits --}}
      <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-6">
        
        {{-- Feature 1 --}}
        <div class="bg-white rounded-card-lg p-7 sm:p-8 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col justify-start">
          <div class="w-12 h-12 rounded-xl bg-brand-purple/10 flex items-center justify-center mb-5 shrink-0">
            <svg class="w-7 h-7" viewBox="0 0 40 40" fill="none">
              <path d="M20 32.5925C27.1799 32.5925 33 26.7724 33 19.5925C33 12.4126 27.1799 6.59253 20 6.59253C12.8201 6.59253 7 12.4126 7 19.5925C7 26.7724 12.8201 32.5925 20 32.5925Z" stroke="#8A2BE2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M16.1001 23.4926C16.9373 24.6106 18.496 25.2476 20.0001 25.3009C21.9969 25.3724 23.9001 24.4169 23.9001 22.1926C23.9001 18.2926 16.7501 20.2426 16.7501 16.3426C16.7501 14.2236 18.2841 13.4475 20.0001 13.4943C21.4418 13.5333 23.0096 14.1521 23.9001 15.0426M20.0001 13.4943V11.1426M20.0001 25.3009V28.0426" stroke="#8A2BE2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4.5 8.09253L33.5 33.0925" stroke="#8A2BE2" stroke-width="2"/>
            </svg>
          </div>
          <h4 class="text-xl font-bold font-display text-brand-hero tracking-tight mb-3">
            No Recurring Fees - Hire Direct
          </h4>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed">
            Save thousands of Dollars a year by hiring your Virtual Assistant directly. One flat fee, direct onboarding, no ongoing costs.
          </p>
        </div>

        {{-- Feature 2 --}}
        <div class="bg-white rounded-card-lg p-7 sm:p-8 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col justify-start">
          <div class="w-12 h-12 rounded-xl bg-brand-purple/10 flex items-center justify-center mb-5 shrink-0">
            <svg class="w-7 h-7" viewBox="0 0 40 40" fill="none">
              <path d="M29.7609 26.1659C31.221 24.08 32.0026 21.597 32 19.0535C32 12.1712 26.4038 6.59253 19.5 6.59253C12.5962 6.59253 7 12.1712 7 19.0535C7 25.9357 12.5962 31.5144 19.5 31.5144C21.5424 31.5144 23.4685 31.023 25.1712 30.1572L27.7875 31.269L30.9005 32.5925L30.3114 29.2703L29.7609 26.1659Z" stroke="#8A2BE2" stroke-width="2" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M12.7451 23.9543V15H18.9651V16.3762H14.3317V18.732H18.6165V20.0841H14.3317V22.5781H18.9651V23.9543H12.7451Z" fill="#8A2BE2"/>
              <path d="M19.7437 23.9543V15H21.4564L24.5994 19.994C24.7076 20.1623 24.8498 20.4006 25.0261 20.7091C25.2024 21.0176 25.3807 21.3321 25.561 21.6526C25.549 21.3161 25.5389 20.9816 25.5309 20.649C25.5269 20.3125 25.5249 20.0561 25.5249 19.8798V15H27.1175V23.9543H25.3987L22.5622 19.4591C22.434 19.2588 22.2577 18.9704 22.0333 18.5938C21.809 18.2131 21.5606 17.7804 21.2881 17.2957C21.3082 17.7925 21.3202 18.2272 21.3242 18.5998C21.3282 18.9724 21.3302 19.2568 21.3302 19.4531V23.9543H19.7437Z" fill="#8A2BE2"/>
            </svg>
          </div>
          <h4 class="text-xl font-bold font-display text-brand-hero tracking-tight mb-3">
            Fluent English
          </h4>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed">
            We understand how important it is to speak fluent English with little to no accent. We go through hundreds of applicants a day and only bring you the top 1%.
          </p>
        </div>

        {{-- Feature 3 --}}
        <div class="bg-white rounded-card-lg p-7 sm:p-8 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col justify-start">
          <div class="w-12 h-12 rounded-xl bg-brand-purple/10 flex items-center justify-center mb-5 shrink-0">
            <svg class="w-7 h-7" viewBox="0 0 26 26" fill="none">
              <path d="M8.35728 17.6428L17.643 8.35704M13.0001 25.0713C16.2017 25.0713 19.2721 23.7995 21.5359 21.5357C23.7998 19.2719 25.0716 16.2014 25.0716 12.9999C25.0716 9.79835 23.7998 6.72794 21.5359 4.46411C19.2721 2.20027 16.2017 0.928467 13.0001 0.928467C9.7986 0.928467 6.72818 2.20027 4.46435 4.46411C2.20052 6.72794 0.928711 9.79835 0.928711 12.9999C0.928711 16.2014 2.20052 19.2719 4.46435 21.5357C6.72818 23.7995 9.7986 25.0713 13.0001 25.0713Z" stroke="#8A2BE2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="9.2855" cy="9.28575" r="0.928571" fill="#8A2BE2"/>
              <circle cx="16.7141" cy="16.7143" r="0.928571" fill="#8A2BE2"/>
            </svg>
          </div>
          <h4 class="text-xl font-bold font-display text-brand-hero tracking-tight mb-3">
            30% Discount on Future Hires
          </h4>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed">
            Get 30% off placement fees for every additional VA you hire within 12 months of your first placement.
          </p>
        </div>

        {{-- Feature 4 --}}
        <div class="bg-white rounded-card-lg p-7 sm:p-8 border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col justify-start">
          <div class="w-12 h-12 rounded-xl bg-brand-purple/10 flex items-center justify-center mb-5 shrink-0">
            <svg class="w-7 h-7" viewBox="0 0 40 40" fill="none">
              <path d="M30.0609 16.1638V29.0906C30.0609 29.7763 29.7885 30.4339 29.3036 30.9188C28.8188 31.4036 28.1612 31.676 27.4755 31.676H11.9633C11.2776 31.676 10.62 31.4036 10.1352 30.9188C9.65032 30.4339 9.37793 29.7763 9.37793 29.0906V10.9931C9.37793 10.3074 9.65032 9.6498 10.1352 9.16495C10.62 8.6801 11.2776 8.40771 11.9633 8.40771H22.3048M30.0609 16.1638V15.9415C30.0607 15.2558 29.7882 14.5984 29.3033 14.1136L24.355 9.16523C23.8702 8.68034 23.2127 8.40786 22.5271 8.40771H22.3048M30.0609 16.1638H24.8901C24.2044 16.1638 23.5468 15.8914 23.062 15.4066C22.5771 14.9217 22.3048 14.2641 22.3048 13.5784V8.40771" stroke="#8A2BE2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4.5 8.09253L33.5 33.0925" stroke="#8A2BE2" stroke-width="2"/>
            </svg>
          </div>
          <h4 class="text-xl font-bold font-display text-brand-hero tracking-tight mb-3">
            No Contracts
          </h4>
          <p class="text-text-muted text-sm sm:text-base leading-relaxed">
            You’re not locked into any sort of long term commitment with us or any Virtual Assistant you hire through us. If you’re not happy with the applicants we bring you, we don’t get paid.
          </p>
        </div>

      </div>

    </div>
  </div>
</section>
