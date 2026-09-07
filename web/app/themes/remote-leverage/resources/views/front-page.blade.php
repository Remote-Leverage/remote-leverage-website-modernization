@extends('layouts.app')

@section('content')
@php
  $imgBase = get_template_directory_uri() . '/public/images/home';
@endphp

<div
  x-data="{
    activeVideo: null,
    openModal(url) { this.activeVideo = url; document.body.style.overflow = 'hidden'; },
    closeModal() { this.activeVideo = null; document.body.style.overflow = 'auto'; }
  }"
  class="w-full font-display text-text-body antialiased overflow-x-hidden"
>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 0 · HERO                                           --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-bg-light pt-16 overflow-hidden">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10 text-center">

      <h1 class="font-display text-5xl lg:text-[48px] font-bold leading-[54px] tracking-[-0.03em] text-black max-w-2xl mx-auto mb-6">
        Great Talent Changes<br>Everything
      </h1>

      <div class="max-w-xl mx-auto mb-8 text-base leading-relaxed">
        <p class="font-bold text-black mb-1">And we'll search the world to find your perfect match.</p>
        <p class="text-text-secondary">We help companies hire exceptional remote talent across sales, marketing, operations, support, and technology roles – without the overhead of traditional hiring.</p>
      </div>

      <div class="flex justify-center mb-12">
        <a
          href="#booking-footer"
          class="inline-flex items-center gap-5 px-7 py-4 bg-brand-purple hover:bg-black text-white font-bold text-sm uppercase tracking-[0.05em] rounded-full transition-colors duration-200 shadow-[0_10px_30px_rgba(138,43,226,0.25)]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M11.7871 0C18.287 0.000243557 23.573 5.28724 23.5732 11.7871C23.573 18.287 18.287 23.573 11.7871 23.5732C5.28725 23.573 0.000243552 18.287 0 11.7871C0.000233875 5.28724 5.28724 0.00023695 11.7871 0ZM11.7871 1.98535C6.38376 1.98559 1.98559 6.38376 1.98535 11.7871C1.9856 17.1905 6.38377 21.5877 11.7871 21.5879C17.1904 21.5876 21.5876 17.1904 21.5879 11.7871C21.5877 6.38376 17.1905 1.9856 11.7871 1.98535ZM9.71387 5.17578L15.7314 11.4043L16.2773 11.9687L15.7314 12.5342L10.0654 18.3994L9.48242 19.0029L8.89746 18.3994L8.64355 18.1377L8.09668 17.5732L8.64258 17.0078L13.5098 11.9687L8.29102 6.56738L7.74609 6.00195L8.29102 5.4375L8.54492 5.17578L9.12891 4.57031L9.71387 5.17578Z" fill="white"/></svg>
          <span>Find my next hire</span>
        </a>
      </div>
    </div>

    {{-- Talent Cards Infinite Marquee --}}
    @php
      $talentCards = [
        ['name'=>'André Vilalobos','title'=>'Graphic Designer','desc'=>'6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.','logo'=>$imgBase.'/State-Farm-01.webp','bg'=>$imgBase.'/con-07.webp'],
        ['name'=>'Juliana Silva','title'=>'Lead Generation (SDR)','desc'=>'6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.','logo'=>$imgBase.'/mercado.webp','bg'=>$imgBase.'/cont-02.webp'],
        ['name'=>'Valeria Andrea','title'=>'Medical Assistant','desc'=>'4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.','logo'=>$imgBase.'/Allstate-01.webp','bg'=>$imgBase.'/con-05.webp'],
        ['name'=>'Laura Valentina','title'=>'Customer Support','desc'=>'+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles.','logo'=>$imgBase.'/image-12-1.webp','bg'=>$imgBase.'/con-08.webp'],
        ['name'=>'Sofía Pérez','title'=>'Marketing Assistant','desc'=>'4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness.','logo'=>$imgBase.'/Frame-74-1.webp','bg'=>$imgBase.'/cont-03.webp'],
        ['name'=>'Luana Dias','title'=>'Executive Assistant','desc'=>'3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive\'s time like it\'s my own.','logo'=>$imgBase.'/NU-bank-01.webp','bg'=>$imgBase.'/con-06.webp'],
        ['name'=>'Sarah Martinez','title'=>'Sr Executive Assistant','desc'=>'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.','logo'=>$imgBase.'/1655873088shopify-logo-transparent.webp','bg'=>$imgBase.'/Frame-131-1.webp'],
        ['name'=>'Daniela Costa','title'=>'Executive Assistant','desc'=>'Experienced Executive Assistant specializing in executive support, meeting coordination, travel planning, and operational workflows. Known for exceptional organization.','logo'=>$imgBase.'/rappi_logo-Small.webp','bg'=>$imgBase.'/Frame-132-1.webp'],
        ['name'=>'Lucas Mendes','title'=>'Marketing Manager','desc'=>'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization with proven track record scaling pipeline.','logo'=>$imgBase.'/clickup.webp','bg'=>$imgBase.'/Frame-133-1.webp'],
        ['name'=>'Noah Martinez','title'=>'Sales Representative','desc'=>'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies.','logo'=>$imgBase.'/image-2.webp','bg'=>$imgBase.'/Frame-135-1.webp'],
        ['name'=>'Diego Navarro','title'=>'Sales Representative','desc'=>'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management to convert qualified leads.','logo'=>$imgBase.'/image-11.webp','bg'=>$imgBase.'/Frame-135-2.webp'],
      ];
    @endphp
    <h2 class="sr-only">Pre-Vetted Remote Professionals</h2>
    <div class="w-full overflow-hidden">
      <div class="animate-marquee-left gap-4 py-2 pb-6">
        @foreach (array_merge($talentCards, $talentCards) as $card)
          <div class="rl-talent-card-wrapper rl-department-card">
            <img class="rl-department-card__bg w-full h-full object-cover" src="{{ $card['bg'] }}" alt="{{ $card['name'] }}" loading="lazy" decoding="async" width="250" height="450">
            <div class="rl-department-card__overlay"></div>
            <div class="rl-department-card__blur"></div>
            <div class="rl-department-card__content">
              <h3 class="rl-department-card__title">
                {{ $card['name'] }}
                <svg class="rl-department-card__verified" viewBox="0 0 24 24" fill="#00D67D"><path d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z"/></svg>
              </h3>
              <div class="rl-department-card__subtitle">{{ $card['title'] }}</div>
              <p class="rl-department-card__description">{{ $card['desc'] }}</p>
              <div class="rl-department-card__worked-at">
                <span class="rl-department-card__worked-at-text">Worked at</span>
                <img class="rl-department-card__worked-at-logo" src="{{ $card['logo'] }}" alt="{{ $card['name'] }} past employer" width="80" height="28" loading="lazy" decoding="async">
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 1 · CLIENT LOGOS MARQUEE                          --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-bg-light border-y border-black/[0.06] py-10 overflow-hidden">
    @php
      $logos = [
        ['src'=>$imgBase.'/zone-4-1.webp','alt'=>'Zone 4'],
        ['src'=>$imgBase.'/sivia-law-white-306w-1.webp','alt'=>'Sivia Law'],
        ['src'=>$imgBase.'/coldwell-1.webp','alt'=>'Coldwell Banker'],
        ['src'=>$imgBase.'/remax-1.webp','alt'=>'RE/MAX'],
        ['src'=>$imgBase.'/rl-college-hunks.webp','alt'=>'College Hunks'],
        ['src'=>$imgBase.'/rl-farmers.webp','alt'=>'Farmers Insurance'],
        ['src'=>$imgBase.'/rl-mainstreet.webp','alt'=>'Mainstreet'],
        ['src'=>$imgBase.'/rl-adp.webp','alt'=>'ADP'],
        ['src'=>$imgBase.'/chick-fil-a-logo-1.webp','alt'=>'Chick-fil-A'],
        ['src'=>$imgBase.'/liberty-hill-1.webp','alt'=>'Liberty Hill'],
        ['src'=>$imgBase.'/adcenter-2.webp','alt'=>'Ad Center 360'],
        ['src'=>$imgBase.'/vercasa-1.webp','alt'=>'Vercasa'],
        ['src'=>$imgBase.'/garuz-1-1.webp','alt'=>'Garuz'],
        ['src'=>$imgBase.'/Prestige-Landscaping-1.webp','alt'=>'Prestige Landscaping'],
        ['src'=>$imgBase.'/greener-hill-1.webp','alt'=>'Greener Hill'],
        ['src'=>$imgBase.'/boe-1.webp','alt'=>'BOE'],
        ['src'=>$imgBase.'/Q-BitNewLogo-Photoroom-1.webp','alt'=>'Q-Bit'],
        ['src'=>$imgBase.'/carbon-1.webp','alt'=>'Carbon Solutions'],
        ['src'=>$imgBase.'/brrrr-1.webp','alt'=>'BRRRR'],
      ];
    @endphp
    <div class="w-full overflow-hidden">
      <div class="animate-marquee-logos flex items-center gap-12">
        @foreach (array_merge($logos, $logos) as $logo)
          <div class="h-[50px] flex items-center justify-center shrink-0">
            <img
              src="{{ $logo['src'] }}"
              alt="{{ $logo['alt'] }}"
              width="140"
              height="50"
              loading="lazy"
              decoding="async"
              class="max-h-[50px] max-w-[140px] w-auto object-contain grayscale opacity-70 hover:grayscale-0 hover:opacity-100 transition-all duration-300"
            >
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 2 · WORLD'S BEST TALENT                           --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-bg-light py-24 lg:py-28">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

      <div class="flex flex-wrap items-end justify-between gap-8 mb-14">
        <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black max-w-lg">
          World's Best Talent,<br>Hired Directly for You
        </h2>
        <p class="max-w-md text-base leading-relaxed text-text-dim">
          You hire talent directly into your business – no subscriptions, no monthly fees, and no markups on salary. Just deep-vetted, skilled professionals helping you run operations, manage communication, and stay organized.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @php
          $benefits = [
            ['img'=>$imgBase.'/Latin-american.webp','title'=>'Top-tier talents from Latin America and EU','desc'=>'Access exceptional global talent. We identify skilled professionals with the communication, expertise, and reliability needed to make an immediate impact.'],
            ['img'=>$imgBase.'/no-contracts.webp','title'=>"No contracts<br>No obligations",'desc'=>'Evaluate talent, interview candidates, and see our process firsthand before making any commitment. The decision is always yours.'],
            ['img'=>$imgBase.'/ongoing-middleman.webp','title'=>"No ongoing<br>middleman fees",'desc'=>'You hire talent directly into your business. No payroll markups, monthly management fees, or recurring commissions.'],
            ['img'=>$imgBase.'/payment.webp','title'=>"No payment if we don't find the right talent",'desc'=>'Our incentives are aligned with yours. We only succeed when you make a successful hire, so we focus relentlessly on finding the right fit.'],
            ['img'=>$imgBase.'/payment-compliance.webp','title'=>'Payments, compliance, onboarding support','desc'=>'Our Contractor Management solution simplifies onboarding, contracts, payroll, and compliance for international talent.'],
            ['img'=>$imgBase.'/one-dashboard.webp','title'=>"One dashboard<br>for your entire team",'desc'=>'Manage payroll, contracts, compliance, and workforce reporting from a single platform. Stay organized as your global team grows.'],
          ];
        @endphp
        @foreach ($benefits as $b)
          <div class="bg-white rounded-3xl p-8 border border-black/[0.06] shadow-sm hover:shadow-md hover:-translate-y-2 transition-all duration-300">
            <div class="bg-step-light-purple rounded-2xl p-6 flex items-center justify-center mb-6 h-36 overflow-hidden">
              <img src="{{ $b['img'] }}" alt="{!! strip_tags($b['title']) !!}" width="300" height="115" loading="lazy" decoding="async" class="max-h-full max-w-full object-contain w-full">
            </div>
            <h3 class="font-display text-xl font-extrabold text-black tracking-[-0.02em] leading-snug mb-3">{!! $b['title'] !!}</h3>
            <p class="text-sm leading-relaxed text-text-secondary">{{ $b['desc'] }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 3 · BEYOND THE "VIRTUAL ASSISTANT"                --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-bg-benefits py-24 lg:py-28">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10">
      <div class="text-center max-w-2xl mx-auto mb-14">
        <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black mb-4">
          Beyond the "Virtual Assistant."
        </h2>
        <p class="text-base leading-relaxed text-text-dim">
          We specialize in globally sourcing English-fluent professionals<br class="hidden sm:block"> for roles that require high-level execution.
        </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @php
          $specialties = [
            ['img'=>$imgBase.'/magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp','title'=>'Administrative &amp;<br>Executive Assistants','desc'=>'Executive support for busy founders and teams.'],
            ['img'=>$imgBase.'/magnific_wPmw8Jk7EI-1.webp','title'=>'Healthcare &amp;<br>Medical Assistants','desc'=>'Healthcare professionals supporting clinics and practices.'],
            ['img'=>$imgBase.'/magnific_ubzu0aUQLD-1.webp','title'=>'Sales &amp; Growth<br>Marketing Talents','desc'=>'Professionals focused on growth, leads, and revenue.'],
            ['img'=>$imgBase.'/magnific_YVjYLdkWeC-1.webp','title'=>'Operations &amp;<br>Finance Professionals','desc'=>'Experts in finance, operations, and business support.'],
          ];
        @endphp
        @foreach ($specialties as $sp)
          <div class="rl-department-card min-h-[420px]">
            <img class="rl-department-card__bg w-full h-full object-cover" src="{{ $sp['img'] }}" alt="{!! strip_tags($sp['title']) !!}" loading="lazy" decoding="async" width="300" height="420">
            <div class="rl-department-card__overlay"></div>
            <div class="rl-department-card__blur"></div>
            <div class="rl-department-card__content p-6">
              <h3 class="font-display text-[22px] font-bold text-white leading-snug mb-2 drop-shadow-sm">{!! $sp['title'] !!}</h3>
              <p class="text-sm text-white/85 leading-relaxed drop-shadow-sm">{{ $sp['desc'] }}</p>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 4 · 2,000+ BUSINESSES (MAP & TRUST)               --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="relative bg-bg-map py-24 lg:py-28 overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center opacity-30 pointer-events-none" style="background-image: url('{{ $imgBase }}/Map.webp');"></div>

    <div class="relative z-10 max-w-screen-xl mx-auto px-6 lg:px-10">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-20 items-center">

        {{-- Left: Stats Cards --}}
        <div class="rl-trust-cards flex flex-col gap-5">
          {{-- Card 1: VAs Onboarded --}}
          <div class="rl-trust-card rl-trust-card-onboarded">
            <span class="rl-trust-card-title">VAs Onboarded</span>
            <div class="rl-trust-card-avatars-group">
              <div class="rl-trust-card-avatars">
                <div class="rl-trust-card-avatar"><img src="{{ $imgBase }}/person_01.webp" alt="Remote Assistant" width="40" height="40" loading="lazy" decoding="async"></div>
                <div class="rl-trust-card-avatar"><img src="{{ $imgBase }}/person_02.webp" alt="Remote Assistant" width="40" height="40" loading="lazy" decoding="async"></div>
                <div class="rl-trust-card-avatar"><img src="{{ $imgBase }}/Person_03.webp" alt="Remote Assistant" width="40" height="40" loading="lazy" decoding="async"></div>
                <div class="rl-trust-card-avatar"><img src="{{ $imgBase }}/Person_04.webp" alt="Remote Assistant" width="40" height="40" loading="lazy" decoding="async"></div>
              </div>
              <span class="rl-trust-card-extra">+2500</span>
            </div>
          </div>

          {{-- Card 2: Countries & Economic Impact --}}
          <div class="rl-trust-card rl-trust-card-payments">
            <div class="rl-trust-card-bg-media">
              <img src="{{ $imgBase }}/globe.webp" alt="Global Coverage" width="120" height="120" loading="lazy" decoding="async">
            </div>
            <div>
              <div class="rl-trust-card-header">
                <span class="rl-trust-card-title">Countries</span>
                <div class="rl-trust-card-flags">
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/costa-rica.webp" alt="Costa Rica" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/equador.webp" alt="Ecuador" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/chile.webp" alt="Chile" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/paraguai.webp" alt="Paraguay" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/brazil.webp" alt="Brazil" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/colombia.webp" alt="Colombia" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/argentina.webp" alt="Argentina" width="20" height="15" loading="lazy" decoding="async"></div>
                  <div class="rl-trust-card-flag"><img src="{{ $imgBase }}/mexico.webp" alt="Mexico" width="20" height="15" loading="lazy" decoding="async"></div>
                </div>
                <span class="font-display font-extrabold text-base text-black">+50</span>
              </div>
            </div>
            <div class="mt-auto">
              <span class="font-display text-[11px] font-semibold uppercase tracking-[0.08em] text-text-muted block mb-2.5">Economic Impact Created</span>
              <div class="rl-trust-card-progress">
                <div class="rl-trust-card-progress-fill w-[53%]"></div>
              </div>
              <div class="rl-trust-card-amount-row">
                <span class="rl-trust-card-amount">USD 41,920,000</span>
                <span class="rl-trust-card-status">Last 12 Months</span>
              </div>
            </div>
          </div>
        </div>

        {{-- Right: Content --}}
        <div class="rl-trust-content">
          <div class="rl-trust-stars" role="img" aria-label="5 out of 5 stars rating">
            @for ($i = 0; $i < 5; $i++)
              <svg width="24" height="24" viewBox="0 0 24 24" fill="black" aria-hidden="true"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
            @endfor
          </div>
          <h2 class="rl-trust-headline">
            We've helped more than 2,000 businesses hire exceptional talent from Latin America, the Caribbean, and Europe.
          </h2>
          <a href="#testimonials" class="rl-trust-cta">
            <span>Watch client testimonials</span>
            <span class="rl-cta-arrow">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
          </a>
        </div>

      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 5 · WHY COMPANIES CHOOSE REMOTE LEVERAGE          --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-bg-light py-24 lg:py-28">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

      <div class="text-center mb-14">
        <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black">
          Why Companies Choose<br>Remote Leverage
        </h2>
      </div>

      {{-- 4 Metric Cards --}}
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-14">
        @php
          $metrics = [
            ['img'=>$imgBase.'/hour.webp','title'=>'$6-10 /hr','desc'=>'Access experienced professionals at highly competitive rates. Most administrative, support, sales, and marketing roles can be filled within this range.'],
            ['img'=>$imgBase.'/lower-cost.webp','title'=>'70% Lower Costs','desc'=>'Reduce hiring costs without sacrificing quality. Reinvest the savings into growth, marketing, product development, or additional hires.'],
            ['img'=>$imgBase.'/day-average.webp','title'=>'4-Day Average','desc'=>'From opening a role to reviewing qualified candidates in days, not weeks. Our recruiting process is designed for speed without compromising quality.'],
            ['img'=>$imgBase.'/quality.webp','title'=>'Vetted for Quality','desc'=>'Every candidate is screened for English proficiency, experience, communication skills, and role-specific expertise before reaching your inbox.'],
          ];
        @endphp
        @foreach ($metrics as $m)
          <div class="bg-white rounded-3xl p-7 border border-black/[0.06] shadow-sm">
            <div class="h-20 flex items-center mb-5">
              <img src="{{ $m['img'] }}" alt="{{ $m['title'] }}" width="80" height="80" loading="lazy" decoding="async" class="max-h-20 max-w-full object-contain">
            </div>
            <h3 class="font-display text-[22px] font-extrabold text-black tracking-[-0.02em] mb-2">{{ $m['title'] }}</h3>
            <p class="text-sm leading-relaxed text-text-secondary">{{ $m['desc'] }}</p>
          </div>
        @endforeach
      </div>

      {{-- Comparison Table --}}
      <div class="bg-white rounded-3xl border border-black/[0.08] overflow-hidden shadow-sm mb-20">
        <div class="grid grid-cols-[40%_30%_30%] bg-slate-50 px-7 py-5 border-b border-black/[0.06]">
          <div></div>
          <div class="font-display text-xs font-bold uppercase tracking-[0.08em] text-slate-700">DIY</div>
          <div class="font-display text-xs font-extrabold uppercase tracking-[0.08em] text-brand-purple">Remote Leverage</div>
        </div>
        @php
          $rows = [
            ['feature'=>'Time to Hire','diy'=>'4 - 8 weeks','rl'=>'72 hrs'],
            ['feature'=>'Vetting Quality','diy'=>'Hit or miss','rl'=>'Top 1% pre-screened'],
            ['feature'=>'Payroll & taxes','diy'=>'DIY or expensive local lawyer','rl'=>'Fully managed'],
            ['feature'=>'Compliance risk','diy'=>'High - misclassification, local laws','rl'=>'Zero - 170+ countries covered'],
            ['feature'=>'Ongoing fees','diy'=>'Often 30-50% monthly markup','rl'=>'One-time flat fee only'],
            ['feature'=>'Replacement guarantee','diy'=>'None','rl'=>'12-months, no extra costs'],
            ['feature'=>'Centralized reporting','diy'=>'Spreadsheets','rl'=>'Dashboard to manage your team'],
          ];
        @endphp
        <div class="divide-y divide-black/[0.05]">
          @foreach ($rows as $row)
            <div class="grid grid-cols-[40%_30%_30%] items-center px-7 py-4 hover:bg-slate-50/50 transition-colors">
              <div class="font-display text-sm font-bold text-black pr-4">{{ $row['feature'] }}</div>
              <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded-full bg-cross-red shrink-0 flex items-center justify-center">
                  <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </div>
                <span class="text-xs text-text-secondary hidden sm:inline">{{ $row['diy'] }}</span>
              </div>
              <div class="flex items-center gap-2.5">
                <div class="w-5 h-5 rounded-full bg-check-green shrink-0 flex items-center justify-center">
                  <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <span class="text-xs font-bold text-black font-display">{{ $row['rl'] }}</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      {{-- From Vacancy to Onboarded --}}
      <div class="text-center max-w-2xl mx-auto mb-14">
        <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black mb-4">
          From Vacancy to<br>Onboarded in 4 Days
        </h2>
        <p class="text-sm sm:text-base leading-relaxed text-text-secondary">
          Tell us who you need. We source, screen, and present qualified candidates within days, helping you move from an open role to a productive team member faster than traditional hiring.
        </p>
      </div>

      <div class="rl-process-container">
        <div class="rl-process-line"></div>
        <div class="rl-process-grid">
          @php
            $steps = [
              ['num'=>'01','title'=>'Tell us your ideal hire','desc'=>'Tell us who you need. We handle sourcing, screening, and vetting candidates so you can focus on choosing the right person.'],
              ['num'=>'02','title'=>'Meet your top 1% shortlist','desc'=>'Within 48–72 hours, receive 4–6 candidates pre-vetted for skill, experience, and fit. You interview, you choose. No commitments, no pressure.'],
              ['num'=>'03','title'=>'Make your selection','desc'=>'Make your selection and get back to growing your business. We handle the details so your new hire can hit the ground running.'],
            ];
          @endphp
          @foreach ($steps as $step)
            <div class="rl-process-item">
              <div class="rl-process-marker">
                <span class="rl-process-number">{{ $step['num'] }}</span>
                <div class="rl-process-dot"></div>
              </div>
              <h3 class="rl-process-title">{{ $step['title'] }}</h3>
              <p class="rl-process-description">{{ $step['desc'] }}</p>
            </div>
          @endforeach
        </div>
      </div>

    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 6 · 12-MONTH REPLACEMENT GUARANTEE                --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section class="bg-brand-dark-violet py-24 lg:py-28 text-white">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10">
      <div class="grid grid-cols-1 lg:grid-cols-[7fr_5fr] gap-16 lg:gap-20 items-center">

        <div class="space-y-8">
          <h2 class="font-display text-4xl sm:text-5xl lg:text-[56px] font-bold leading-[1.05] tracking-[-0.03em] text-white">
            12-Month Replacement Guarantee
          </h2>
          <div class="space-y-4 text-base sm:text-lg leading-relaxed text-white/85">
            <p>12-Month free replacement guarantee on any remote talent you hire through us to ensure you have a perfect fit. You'll also have a dedicated manager to help you set up training, performance tracking, and any other support you need.</p>
            <p class="font-bold text-white">No contracts or commitments.</p>
            <p>We get paid a flat hiring fee if you choose to hire a Virtual Assistant after interviewing our applicants.</p>
          </div>
          <a
            href="#booking-footer"
            class="inline-flex items-center gap-8 px-9 py-[18px] bg-white text-black rounded-full font-display text-sm font-bold uppercase tracking-[0.08em] hover:bg-slate-100 transition-colors shadow-xl group"
          >
            <span>Book a consultation</span>
            <span class="w-[34px] h-[34px] rounded-full bg-black flex items-center justify-center shrink-0">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
          </a>
        </div>

        <div class="flex items-center justify-center">
          <img
            src="{{ $imgBase }}/Group-59-1-e1780958571501-300x283.webp"
            alt="12-Month Replacement Guarantee"
            width="300"
            height="283"
            loading="lazy"
            decoding="async"
            class="max-w-[380px] w-full h-auto drop-shadow-2xl"
          >
        </div>
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 7 · RESULTS, NOT PROMISES (TESTIMONIALS + FAQ)    --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section id="testimonials" class="bg-bg-light py-24 lg:py-28">
    <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

      <div class="flex flex-wrap items-end justify-between gap-8 mb-14">
        <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black">
          Results, Not Promises
        </h2>
        <p class="max-w-sm text-base leading-relaxed text-text-dim">
          Don't just take our word for it, hear from business owners who've hired through Remote Leverage. See why quality makes all the difference!
        </p>
      </div>

      @php
        $testimonials = [
          ['video_url'=>'https://vimeo.com/1067577208','image'=>$imgBase.'/PRES-Property-Management.jpg','duration'=>'00:38','quote'=>'"I can\'t say enough about how every step of the way it just wowed me."','company'=>'PRES Property Management'],
          ['video_url'=>'https://vimeo.com/1067577369','image'=>$imgBase.'/Coldwell-Banker.jpg','duration'=>'00:19','quote'=>'"I\'m very impressed with the quality of my VA, she\'s very intelligent and she aims to please."','company'=>'Coldwell Banker'],
          ['video_url'=>'https://vimeo.com/1067577665','image'=>$imgBase.'/Carbon-Solutions-Group.jpg','duration'=>'00:55','quote'=>'"I really recommend Remote Leverage; it was a fast process, and the results are good."','company'=>'Carbon Solutions Group'],
          ['video_url'=>'https://vimeo.com/1067577549','image'=>$imgBase.'/The-Zen-Zone-Wellness.jpg','duration'=>'04:06','quote'=>'"I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first."','company'=>'The Zen Zone Wellness'],
          ['video_url'=>'https://vimeo.com/1067577688','image'=>$imgBase.'/Color-Job.jpg','duration'=>'01:56','quote'=>'"The transition of working with you guys was absolutely smooth and amazing."','company'=>'Color Job'],
          ['video_url'=>'https://vimeo.com/1067577383','image'=>$imgBase.'/Connect-Church-Colorado.jpg','duration'=>'02:45','quote'=>'"She was just perfect, everything that we were looking for we found it in her."','company'=>'Connect Church Colorado'],
          ['video_url'=>'https://vimeo.com/1067577489','image'=>$imgBase.'/Cash-is-King.jpg','duration'=>'02:39','quote'=>'"Honestly, the reason why we keep hiring is because it is so incredibly easy."','company'=>'Cash is King'],
          ['video_url'=>'https://vimeo.com/1067577248','image'=>$imgBase.'/Liberty-Hill.jpg','duration'=>'02:31','quote'=>'"If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates."','company'=>'Liberty Hill'],
          ['video_url'=>'https://vimeo.com/1067577464','image'=>$imgBase.'/RE-MAX.jpg','duration'=>'01:02','quote'=>'"Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it\'s been a game changer for me."','company'=>'RE / MAX'],
          ['video_url'=>'https://vimeo.com/1067577620','image'=>$imgBase.'/Realty-One-Group.jpg','duration'=>'01:43','quote'=>'"As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business."','company'=>'Realty One Group'],
          ['video_url'=>'https://vimeo.com/1067577228','image'=>$imgBase.'/OneUp-Sportz-01.jpg','duration'=>'01:06','quote'=>'"Very Very happy with the system, you guys system worked well and it was efficient."','company'=>'OneUp Sportz'],
          ['video_url'=>'https://vimeo.com/1067577598','image'=>$imgBase.'/OneUp-Sportz.jpg','duration'=>'01:40','quote'=>'"It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome."','company'=>'OneUp Sportz'],
          ['video_url'=>'https://vimeo.com/1067577293','image'=>$imgBase.'/Greener-Hill-Psychiatric.jpg','duration'=>'05:59','quote'=>'"Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal."','company'=>'Greener Hill Psychiatric'],
          ['video_url'=>'https://vimeo.com/1067577442','image'=>$imgBase.'/Diamond-Detox.jpg','duration'=>'00:43','quote'=>'"I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient."','company'=>'Diamond Detox'],
          ['video_url'=>'https://vimeo.com/1067577645','image'=>$imgBase.'/Ad-Center-360.jpg','duration'=>'00:36','quote'=>'"It was awesome the best experience I\'ve ever had as far as hiring."','company'=>'Ad Center 360'],
        ];
      @endphp

      <div class="rl-testimonial-list columns-3 mb-20">
        @foreach ($testimonials as $t)
          <div class="rl-testimonial-card cursor-pointer" @click="openModal('{{ $t['video_url'] }}')">
            <div class="rl-testimonial-header">
              <div class="rl-testimonial-avatar shape-rounded relative">
                <img
                  src="{{ $t['image'] }}"
                  alt="{{ $t['company'] }}"
                  width="56"
                  height="56"
                  loading="lazy"
                  decoding="async"
                  class="w-full h-full object-cover"
                >
                <div class="absolute inset-0 bg-black/20 flex flex-col items-center justify-center rounded-xl pointer-events-none">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                  <span class="text-[9px] font-bold text-white mt-0.5">{{ $t['duration'] }}</span>
                </div>
              </div>
              <p class="rl-testimonial-quote">{{ $t['quote'] }}</p>
            </div>
            <div class="rl-testimonial-footer">
              <div class="rl-testimonial-action-row">
                <button class="rl-testimonial-play-btn" type="button" aria-label="Play testimonial video from {{ $t['company'] }}">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <div class="rl-testimonial-separator"></div>
                <span class="rl-testimonial-company">{{ $t['company'] }}</span>
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- FAQ --}}
      <div class="max-w-4xl mx-auto">
        <div class="text-center mb-14">
          <h2 class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black">
            Frequently Asked Questions
          </h2>
        </div>

        @php
          $faqs = [
            ['q'=>'What countries do you hire from?','a'=>'<p>We focus on four key regions:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p class="mt-3">Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their exceptional English fluency, strong cultural alignment, and convenient time zone overlap with North America.</p>'],
            ['q'=>'How do taxes & payroll work when hiring Virtual Assistants?','a'=>'<p>Your VA is an independent contractor, so there\'s no payroll involved. If you\'d rather not manage contractor agreements, documentation, and international payments yourself, our Contractor of Record (COR) add-on puts Remote Leverage in the contracting seat – we handle onboarding, verified time tracking, and cross-border payment, and you get a single invoice.</p>'],
            ['q'=>'How do you get paid?','a'=>'<p>It\'s simple – we charge a one-time flat fee, but only after you\'ve found your perfect match.</p><p class="mt-2">Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>'],
            ['q'=>'What\'s the difference between Staffing and Recruiting Agencies?','a'=>'<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly.</p>'],
            ['q'=>'What if I have questions and need help after hiring?','a'=>'<p>After hiring your Virtual Assistant, you\'ll have access to a dedicated Customer Success Manager who will help ensure your success with reviewing performance, monitoring progress, training guidance, and any other requests.</p>'],
            ['q'=>'What if they don\'t turn out to be a good fit?','a'=>'<p>We offer a 12-month replacement guarantee at no extra cost and unlimited candidate interviews to ensure you find the best match.</p>'],
            ['q'=>'How is their English and Communication skills?','a'=>'<p>We maintain extremely high standards for English fluency. All candidates must submit an English voice recording, and we only select those with fluent English and minimal accents.</p>'],
            ['q'=>'Can I start with Part-time?','a'=>'<p>Yes, you can start with either part-time or full-time. The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>'],
            ['q'=>'What time zone will they be working in?','a'=>'<p>Your Virtual Assistant will work according to your schedule and time zone. They\'re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>'],
            ['q'=>'How much does the average Virtual Assistant cost?','a'=>'<p>Virtual Assistant\'s hourly rates depend on skills, experience and region:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li><strong>Entry Level:</strong> $6-$10 per hour</li><li><strong>Highly Experienced:</strong> $11-$15 per hour</li></ul><p class="mt-3">The hourly rate you agree to pay goes directly to your Virtual Assistant.</p>'],
          ];
        @endphp

        <div class="flex flex-col gap-3" x-data="{ activeFaq: null }">
          @foreach ($faqs as $idx => $faq)
            <div class="bg-white rounded-2xl border border-black/[0.06] overflow-hidden shadow-xs">
              <button
                type="button"
                :aria-expanded="activeFaq === {{ $idx }} ? 'true' : 'false'"
                @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                class="w-full py-5 px-7 text-left flex items-center justify-between gap-4 cursor-pointer"
              >
                <span class="font-display text-base font-bold text-black">{{ $faq['q'] }}</span>
                <div
                  class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 transition-all duration-200"
                  :class="activeFaq === {{ $idx }} ? 'bg-black rotate-45' : ''"
                >
                  <svg class="w-3.5 h-3.5" :class="activeFaq === {{ $idx }} ? 'text-white' : 'text-slate-700'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                  </svg>
                </div>
              </button>
              <div x-show="activeFaq === {{ $idx }}" x-collapse style="display:none;">
                <div class="px-7 pb-6 pt-1 text-sm sm:text-base leading-relaxed text-text-secondary border-t border-black/[0.05]">
                  <div class="pt-4">{!! $faq['a'] !!}</div>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>

    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- SECTION 8 · READY TO SCALE (BOOKING FOOTER)               --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <section id="booking-footer" class="relative bg-brand-dark-violet py-24 lg:py-28 text-white overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center opacity-25 pointer-events-none" style="background-image: url('{{ $imgBase }}/Union-5.webp');"></div>
    <div class="relative z-10 max-w-4xl mx-auto px-6 lg:px-10">
      <div class="text-center mb-12">
        <h2 class="font-display text-4xl sm:text-5xl lg:text-[56px] font-bold leading-[1.05] tracking-[-0.03em] text-white mb-5">
          Ready to scale your<br>global team?
        </h2>
        <p class="text-base sm:text-lg leading-relaxed text-white/80 max-w-xl mx-auto">
          During this meeting we will go over the role you're planning to hire for, what the process looks like, answer any questions you have, and proceed to next steps.
        </p>
      </div>
      <div class="bg-white rounded-3xl p-8 lg:p-10 shadow-[0_20px_80px_rgba(0,0,0,0.3)]">
        <livewire:booking.multistep-booking-wizard />
      </div>
    </div>
  </section>

  {{-- ═══════════════════════════════════════════════════════════ --}}
  {{-- MODAL · VIMEO VIDEO PLAYER                                 --}}
  {{-- ═══════════════════════════════════════════════════════════ --}}
  <div
    x-show="activeVideo"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/88 backdrop-blur-sm"
    style="display:none;"
    @keydown.escape.window="closeModal()"
  >
    <div class="relative w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden" @click.outside="closeModal()">
      <button
        type="button"
        @click="closeModal()"
        class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 flex items-center justify-center text-white transition-colors focus:outline-none"
        aria-label="Close video player modal"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <template x-if="activeVideo">
        <iframe
          :src="'https://player.vimeo.com/video/' + activeVideo.split('/').pop() + '?autoplay=1&badge=0&autopause=0&player_id=0&app_id=58479'"
          title="Vimeo video player"
          class="w-full h-full border-0"
          allow="autoplay; fullscreen; picture-in-picture"
          allowfullscreen
          loading="lazy"
        ></iframe>
      </template>
    </div>
  </div>

</div>
@endsection
