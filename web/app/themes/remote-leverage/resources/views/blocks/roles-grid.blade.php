@php
  use App\Support\BlockDefaults;

  $eyebrowImg = BlockDefaults::hireVaImg('Group-207.png');
  $womanImg = BlockDefaults::hireVaImg('Woman_looking_camera_smiling_2K_202607171433-1.png');
  $manImg = BlockDefaults::hireVaImg('man-dressed-casual-wearing-glasses-studio-shot-copy-space-2.png');
  $leadGenImg = BlockDefaults::hireVaImg('Frame-1092.png');
  $salesImg = BlockDefaults::hireVaImg('Screenshot-2026-07-17-at-2.03.18-p.m.-1.png');
  $marketingImg = BlockDefaults::hireVaImg('Frame-1092-1.png');
  $designImg = BlockDefaults::hireVaImg('Screenshot-2026-07-17-at-2.01.07-p.m.-1.png');
  $socialImg = BlockDefaults::hireVaImg('Screenshot-2026-07-17-at-2.02.42-p.m.-1.png');
  $customImg = BlockDefaults::hireVaImg('Frame-216.png');
@endphp

<section class="py-16 sm:py-20 lg:py-24 bg-[#F4F6FC]">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    
    {{-- Eyebrow & Headline --}}
    <div class="max-w-3xl mb-12 sm:mb-16">
      <div class="inline-flex items-center gap-3 px-4 py-2 rounded-full bg-white border border-black/5 shadow-xs mb-5">
        <img src="{{ $eyebrowImg }}" alt="Candidate Avatars" class="h-6 w-auto" loading="lazy" decoding="async">
        <span class="text-xs sm:text-sm font-bold text-brand-hero tracking-wide">2.5K+ pre-vetted candidates</span>
      </div>

      <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold font-display text-brand-hero tracking-tight leading-[1.08]">
        {!! nl2br(e($headline ?? 'The Roles That Buy Back Your Time')) !!}
      </h2>
    </div>

    {{-- True 3-Column Asymmetric Masonry Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
      
      {{-- COLUMN 1: Tall Admin Card Top + 2 Medium Cards Below --}}
      <div class="flex flex-col gap-6">
        {{-- Card 1: Administrative (Tall, Dark Purple, Woman Cutout) --}}
        <div class="relative overflow-hidden bg-[#250D4A] rounded-card p-6 sm:p-8 text-white border border-white/10 shadow-sm flex flex-col justify-between min-h-[440px] group">
          <div>
            <h3 class="text-2xl sm:text-3xl font-bold font-display text-white tracking-tight">Administrative</h3>
            <p class="text-sm sm:text-base text-white/80 mt-2 max-w-[280px] leading-relaxed">
              Inbox, calendar, invoices, data entry. The daily upkeep taken off your plate.
            </p>
          </div>
          <div class="mt-6 flex justify-center -mb-8 pointer-events-none">
            <img src="{{ $womanImg }}" alt="Administrative Specialist" loading="lazy" decoding="async" class="h-64 sm:h-72 w-auto object-contain object-bottom transition-transform duration-500 group-hover:scale-105">
          </div>
        </div>

        {{-- Card 2: Marketing (Lavender, Horizontal Layout) --}}
        <div class="bg-[#F2EDF9] rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">Marketing</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              Runs and optimizes your paid campaigns across Meta, Google, and LinkedIn.
            </p>
          </div>
          <div class="shrink-0">
            <img src="{{ $marketingImg }}" alt="Marketing" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 3: Graphic Design (Soft Blue, Horizontal Layout) --}}
        <div class="bg-[#E1ECF7] rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">Graphic Design</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              Social creative, decks, and brand assets that look like an in-house hire made them.
            </p>
          </div>
          <div class="shrink-0">
            <img src="{{ $designImg }}" alt="Graphic Design" loading="lazy" decoding="async" class="w-24 sm:w-28 h-auto object-contain">
          </div>
        </div>
      </div>

      {{-- COLUMN 2: 2 Medium Cards Top + Tall Support Card Bottom --}}
      <div class="flex flex-col gap-6">
        {{-- Card 4: Lead Generation (Lavender, Horizontal Layout) --}}
        <div class="bg-[#F2EDF9] rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">Lead Generation</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              Outreach calls, emails, texting, and follow-up that keeps your pipeline full.
            </p>
          </div>
          <div class="shrink-0">
            <img src="{{ $leadGenImg }}" alt="Lead Gen" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 5: Sales (SDR) (Soft Blue, Horizontal Layout) --}}
        <div class="bg-[#E1ECF7] rounded-card p-6 sm:p-7 border border-black/5 shadow-xs flex items-center justify-between gap-4 min-h-[165px] hover:shadow-sm transition-all duration-200">
          <div class="flex-1">
            <h3 class="text-xl font-bold font-display text-brand-hero">Sales (SDR)</h3>
            <p class="text-xs sm:text-sm text-text-muted mt-1.5 leading-relaxed max-w-[220px]">
              Qualifies leads, runs demos, and follows through until it's a closed deal.
            </p>
          </div>
          <div class="shrink-0">
            <img src="{{ $salesImg }}" alt="Sales SDR" loading="lazy" decoding="async" class="w-28 sm:w-32 h-auto object-contain">
          </div>
        </div>

        {{-- Card 6: Customer Support (Tall, Dark Purple, Man Cutout) --}}
        <div class="relative overflow-hidden bg-[#250D4A] rounded-card p-6 sm:p-8 text-white border border-white/10 shadow-sm flex flex-col justify-between min-h-[440px] flex-1 group">
          <div>
            <h3 class="text-2xl sm:text-3xl font-bold font-display text-white tracking-tight">Customer Support</h3>
            <p class="text-sm sm:text-base text-white/80 mt-2 max-w-[280px] leading-relaxed">
              Tickets, questions, and vendor calls handled so your customers stay happy.
            </p>
          </div>
          <div class="mt-6 flex justify-center -mb-8 pointer-events-none">
            <img src="{{ $manImg }}" alt="Customer Support" loading="lazy" decoding="async" class="h-64 sm:h-72 w-auto object-contain object-bottom transition-transform duration-500 group-hover:scale-105">
          </div>
        </div>
      </div>

      {{-- COLUMN 3: Social Media Top + Custom Role Bottom --}}
      <div class="flex flex-col gap-6">
        {{-- Card 7: Social Media (Soft Grey/Blue Surface, Post Mockup) --}}
        <div class="bg-[#EBF1F6] rounded-card p-6 sm:p-8 border border-black/5 shadow-xs flex flex-col justify-between min-h-[360px]">
          <div>
            <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">Social Media</h3>
            <p class="text-sm text-text-muted mt-2 leading-relaxed max-w-[300px]">
              Posts, replies, and community management that keeps your brand active.
            </p>
          </div>
          <div class="mt-6 flex justify-center">
            <img src="{{ $socialImg }}" alt="Social Media Mockup" loading="lazy" decoding="async" class="max-h-52 w-auto object-contain">
          </div>
        </div>

        {{-- Card 8: Custom Role (Clean White Card, Orbital Graphic) --}}
        <div class="bg-white rounded-card p-6 sm:p-8 border border-black/5 shadow-xs flex flex-col justify-between min-h-[400px] flex-1">
          <div>
            <h3 class="text-2xl font-bold font-display text-brand-hero tracking-tight">Custom Role</h3>
            <p class="text-sm text-text-muted mt-2 leading-relaxed max-w-[300px]">
              Something specific in mind? Tell us the role — we’ve likely filled it before.
            </p>
          </div>
          
          <div class="mt-6 flex justify-center items-center">
            <img src="{{ $customImg }}" alt="Custom Role Network" loading="lazy" decoding="async" class="w-full max-w-[280px] h-auto object-contain">
          </div>
        </div>
      </div>

    </div>

    {{-- Bottom Consultation CTA Button --}}
    <div class="mt-14 sm:mt-18 flex justify-center">
      <a href="#booking" class="inline-flex items-center gap-3 px-8 py-4 rounded-full bg-brand-purple hover:bg-[#7b20d4] text-white font-bold text-sm tracking-wider uppercase shadow-[0_4px_20px_rgba(138,43,226,0.35)] transition-all duration-200 hover:scale-[1.02]">
        <span>Book a Free 15-Minute Consultation</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
        </svg>
      </a>
    </div>

  </div>
</section>
