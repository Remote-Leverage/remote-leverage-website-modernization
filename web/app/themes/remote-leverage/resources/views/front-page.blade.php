@extends('layouts.app')

@section('content')
    @php
        $img = fn (string $file) => \App\Support\BlockDefaults::homeImg($file);
    @endphp

    @while (have_posts())
        @php
            the_post();
        @endphp
        @if (has_blocks() || !empty(trim(get_the_content())))
            <div class="entry-content w-full font-display text-text-body antialiased overflow-x-hidden">
                @php
                    the_content();
                @endphp
            </div>
        @else
            <div class="w-full font-display text-text-body antialiased overflow-x-hidden">

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 0 · HERO                                           --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section class="relative bg-bg-light pt-14 pb-4 overflow-hidden">
                    {{-- World Map Background behind Hero --}}
                    <div class="absolute top-2 left-1/2 -translate-x-1/2 w-[1400px] max-w-[96vw] h-[540px] pointer-events-none z-0 opacity-45 bg-no-repeat bg-contain bg-center"
                        style="background-image: url('{{ $img('Map.webp') }}');"></div>

                    <div class="relative z-10 max-w-[1380px] mx-auto px-6 lg:px-10 text-center">

                        <h1
                            class="font-display text-5xl lg:text-[48] font-bold leading-[54px] tracking-[-0.03em] text-black max-w-3xl mx-auto mb-6">
                            Great Talent Changes<br>Everything
                        </h1>

                        <div class="max-w-4xl mx-auto mb-9">
                            <p
                                class="font-normal text-black text-[22px] sm:text-[24px] leading-[34px] tracking-[-0.015em] mb-2">
                                And we'll search the world to find your perfect match.
                            </p>
                            <p class="font-normal text-black text-[22px] sm:text-[24px] leading-[34px] tracking-[-0.015em]">
                                We help companies hire exceptional remote talent across sales, marketing, operations,
                                support, and
                                technology roles<br class="hidden lg:inline"> – without the overhead of traditional hiring.
                            </p>
                        </div>

                        <div class="flex justify-center mb-14">
                            <a href="#booking-footer"
                                class="inline-flex items-center gap-3 px-8 py-4 bg-brand-purple hover:bg-black text-white font-extrabold text-sm uppercase tracking-[0.06em] rounded-full transition-all duration-200 shadow-[0_10px_25px_rgba(138,43,226,0.3)] hover:scale-105">
                                <span>FIND MY NEXT HIRE</span>
                                <svg class="w-5 h-5 text-white shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
                                    <path d="M10 8l4 4-4 4" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Talent Cards Infinite Marquee --}}
                    @php
                        $talentCards = [
                            [
                                'name' => 'Daniela Costa',
                                'title' => 'Executive Assistant',
                                'desc' =>
                                    'Experienced Executive Assistant specializing in executive support, meeting coordination, travel planning, and operational workflows. Known for exceptional organization.',
                                'logo' => $img('rappi_logo-Small.webp'),
                                'bg' => $img('Frame-132-1.webp'),
                            ],
                            [
                                'name' => 'Lucas Mendes',
                                'title' => 'Marketing Manager',
                                'desc' =>
                                    'Marketing Manager with 8+ years of experience across demand generation, paid acquisition, lifecycle marketing, and funnel optimization with proven track record scaling pipeline.',
                                'logo' => $img('clickup.webp'),
                                'bg' => $img('Frame-133-1.webp'),
                            ],
                            [
                                'name' => 'Noah Martinez',
                                'title' => 'Sales Representative',
                                'desc' =>
                                    'Sales Development Representative who consistently exceeded quota by building high-quality outbound pipelines for B2B software companies.',
                                'logo' => $img('image-2.webp'),
                                'bg' => $img('Frame-135-1.webp'),
                            ],
                            [
                                'name' => 'Diego Navarro',
                                'title' => 'Sales Representative',
                                'desc' =>
                                    'Revenue-focused sales representative experienced in outbound prospecting, product demonstrations, and account management to convert qualified leads.',
                                'logo' => $img('image-11.webp'),
                                'bg' => $img('Frame-135-2.webp'),
                            ],
                            [
                                'name' => 'André Vilalobos',
                                'title' => 'Graphic Designer',
                                'desc' =>
                                    '6+ years of experience helping brands of all sizes, from small and mid-sized businesses to big companies, look professional, polished, and unmistakably them.',
                                'logo' => $img('State-Farm-01.webp'),
                                'bg' => $img('con-07.webp'),
                            ],
                            [
                                'name' => 'Juliana Silva',
                                'title' => 'Lead Generation (SDR)',
                                'desc' =>
                                    '6+ years of experience as an SDR, skilled in prospecting, active listening, clear communication, time management, and handling rejection to consistently generate and qualify sales leads.',
                                'logo' => $img('mercado.webp'),
                                'bg' => $img('cont-02.webp'),
                            ],
                            [
                                'name' => 'Valeria Andrea',
                                'title' => 'Medical Assistant',
                                'desc' =>
                                    '4+ years of experience in fast-paced clinic and hospital settings. Skilled in EMR systems (Epic, Cerner), patient intake, vital signs, and assisting physicians with exams and procedures.',
                                'logo' => $img('Allstate-01.webp'),
                                'bg' => $img('con-05.webp'),
                            ],
                            [
                                'name' => 'Laura Valentina',
                                'title' => 'Customer Support',
                                'desc' =>
                                    '+4 years in B2B SaaS customer support, I\'ve supported customers in North America, Europe, and Latin America, adapting to different cultural expectations and communication styles.',
                                'logo' => $img('image-12-1.webp'),
                                'bg' => $img('con-08.webp'),
                            ],
                            [
                                'name' => 'Sofía Pérez',
                                'title' => 'Marketing Assistant',
                                'desc' =>
                                    '4+ years of experience as a results-driven marketing professional, skilled in content creation, social media strategy, campaign management, and data analysis to drive brand awareness.',
                                'logo' => $img('Frame-74-1.webp'),
                                'bg' => $img('cont-03.webp'),
                            ],
                            [
                                'name' => 'Luana Dias',
                                'title' => 'Executive Assistant',
                                'desc' =>
                                    '3+ years of experience supporting C-level executives in fast-paced environments. High organization, anticipate needs, and protect executive\'s time like it\'s my own.',
                                'logo' => $img('NU-bank-01.webp'),
                                'bg' => $img('con-06.webp'),
                            ],
                            [
                                'name' => 'Sarah Martinez',
                                'title' => 'Sr Executive Assistant',
                                'desc' =>
                                    'Executive Assistant with 8+ years supporting founders and executives. Expert in calendar management, inbox organization, project coordination, and keeping fast-growing teams operating smoothly.',
                                'logo' => $img('1655873088shopify-logo-transparent.webp'),
                                'bg' => $img('Frame-131-1.webp'),
                            ],
                        ];
                    @endphp
                    <h2 class="sr-only">Pre-Vetted Remote Professionals</h2>
                    <div class="w-full overflow-hidden">
                        <div class="animate-marquee-left gap-card py-2 pb-6">
                            @foreach (array_merge($talentCards, $talentCards) as $card)
                                <div class="rl-talent-card-wrapper rl-talent-card">
                                    <img class="rl-department-card__bg w-full h-full object-cover object-top"
                                        src="{{ $card['bg'] }}" alt="{{ $card['name'] }}" loading="lazy" decoding="async"
                                        width="250" height="400">
                                    <div class="rl-department-card__overlay"></div>
                                    <div class="rl-department-card__blur"></div>
                                    <div class="rl-department-card__content">
                                        <h3 class="rl-department-card__title">
                                            <span>{{ $card['name'] }}</span>
                                            <svg class="rl-department-card__verified" viewBox="0 0 24 24" fill="#ffffff"
                                                fill-rule="evenodd">
                                                <path fill-rule="evenodd"
                                                    d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z" />
                                            </svg>
                                        </h3>
                                        <div class="rl-department-card__subtitle">{{ $card['title'] }}</div>
                                        <p class="rl-department-card__description">{{ $card['desc'] }}</p>
                                        <div class="rl-department-card__worked-at">
                                            <span class="rl-department-card__worked-at-text">Worked at</span>
                                            <img class="rl-department-card__worked-at-logo" src="{{ $card['logo'] }}"
                                                alt="{{ $card['name'] }} past employer" width="80" height="20"
                                                loading="lazy" decoding="async">
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
                <section class="bg-bg-light py-8 sm:py-10 overflow-hidden">
                    @php
                        $logos = [
                            ['src' => $img('brrrr-1.webp'), 'alt' => 'BRRRR'],
                            ['src' => $img('carbon-1.webp'), 'alt' => 'Carbon Solutions'],
                            ['src' => $img('Q-BitNewLogo-Photoroom-1.webp'), 'alt' => 'Q-Bit'],
                            ['src' => $img('boe-1.webp'), 'alt' => 'BOE'],
                            ['src' => $img('greener-hill-1.webp'), 'alt' => 'Greener Hill'],
                            ['src' => $img('Prestige-Landscaping-1.webp'), 'alt' => 'Prestige Landscaping'],
                            ['src' => $img('garuz-1-1.webp'), 'alt' => 'Garuz'],
                            ['src' => $img('vercasa-1.webp'), 'alt' => 'Vercasa'],
                            ['src' => $img('adcenter-2.webp'), 'alt' => 'Ad Center 360'],
                            ['src' => $img('liberty-hill-1.webp'), 'alt' => 'Liberty Hill'],
                            ['src' => $img('chick-fil-a-logo-1.webp'), 'alt' => 'Chick-fil-A'],
                            ['src' => $img('rl-adp.webp'), 'alt' => 'ADP'],
                            ['src' => $img('rl-mainstreet.webp'), 'alt' => 'Mainstreet'],
                            ['src' => $img('rl-farmers.webp'), 'alt' => 'Farmers Insurance'],
                            ['src' => $img('rl-college-hunks.webp'), 'alt' => 'College Hunks'],
                            ['src' => $img('remax-1.webp'), 'alt' => 'RE/MAX'],
                            ['src' => $img('coldwell-1.webp'), 'alt' => 'Coldwell Banker'],
                            ['src' => $img('sivia-law-white-306w-1.webp'), 'alt' => 'Sivia Law'],
                            ['src' => $img('zone-4-1.webp'), 'alt' => 'Zone 4'],
                        ];
                    @endphp
                    <div class="rl-logo-marquee-wrapper px-4">
                        <div class="animate-marquee-logos flex items-center gap-12 sm:gap-14">
                            @foreach (array_merge($logos, $logos) as $logo)
                                <div class="rl-logo-marquee-item">
                                    <img src="{{ $logo['src'] }}" alt="{{ $logo['alt'] }}" width="140" height="48"
                                        loading="lazy" decoding="async">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 2 · WORLD'S BEST TALENT                           --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section class="bg-bg-light py-20 lg:py-24">
                    <div class="max-w-[1380px] mx-auto px-6 lg:px-10">

                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 mb-14 lg:mb-16">
                            <h2
                                class="font-display text-4xl sm:text-5xl lg:text-[50px] font-bold leading-[1.08] tracking-[-0.03em] text-black max-w-xl">
                                World's Best Talent,<br>Hired Directly for You
                            </h2>
                            <p class="max-w-[480px] text-[15px] sm:text-base leading-relaxed text-black lg:pt-1">
                                You hire talent directly into your business – no subscriptions, no monthly fees, and no
                                markups on
                                salary. Just deep-vetted, skilled professionals helping you run operations, manage
                                communication,
                                and stay organized.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-card">
                            @php
                                $benefits = [
                                    [
                                        'img' => $img('Latin-american.webp'),
                                        'title' => 'Top-tier talents from Latin America and EU',
                                        'desc' =>
                                            'Access exceptional global talent. We identify skilled professionals with the communication, expertise, and reliability needed to make an immediate impact.',
                                    ],
                                    [
                                        'img' => $img('no-contracts.webp'),
                                        'title' => 'No contracts<br>No obligations',
                                        'desc' =>
                                            'Evaluate talent, interview candidates, and see our process firsthand before making any commitment. The decision is always yours.',
                                    ],
                                    [
                                        'img' => $img('ongoing-middleman.webp'),
                                        'title' => 'No ongoing<br>middleman fees',
                                        'desc' =>
                                            'You hire talent directly into your business. No payroll markups, monthly management fees, or recurring commissions.',
                                    ],
                                    [
                                        'img' => $img('payment.webp'),
                                        'title' => "No payment if we don't find the right talent",
                                        'desc' =>
                                            'Our incentives are aligned with yours. We only succeed when you make a successful hire, so we focus relentlessly on finding the right fit.',
                                    ],
                                    [
                                        'img' => $img('payment-compliance.webp'),
                                        'title' => 'Payments, compliance,<br>onboarding support',
                                        'desc' =>
                                            'Our Contractor Management solution simplifies onboarding, contracts, payroll, and compliance for international talent.',
                                    ],
                                    [
                                        'img' => $img('one-dashboard.webp'),
                                        'title' => 'One dashboard<br>for your entire team',
                                        'desc' =>
                                            'Manage payroll, contracts, compliance, and workforce reporting from a single platform. Stay organized as your global team grows.',
                                    ],
                                ];
                            @endphp
                            @foreach ($benefits as $b)
                                <div
                                    class="bg-white rounded-card p-card flex flex-col justify-between border border-black/[0.04] shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300">
                                    <div>
                                        <div class="w-full overflow-hidden rounded-xl mb-6 bg-[#f7f8fc]">
                                            <img src="{{ $b['img'] }}" alt="{!! strip_tags($b['title']) !!}" width="340"
                                                height="130" loading="lazy" decoding="async"
                                                class="w-full h-auto object-cover rounded-xl">
                                        </div>
                                        <div class="p-3">
                                            <h3
                                                class="font-display text-[22px] sm:text-[23px] font-bold text-black tracking-[-0.025em] leading-[1.2] mb-3.5">
                                                {!! $b['title'] !!}</h3>
                                            <p class="text-[14px] sm:text-[15px] leading-[1.6] text-black">
                                                {{ $b['desc'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 3 · BEYOND THE "VIRTUAL ASSISTANT"                --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section class="bg-bg-light py-24 lg:py-28">
                    <div class="max-w-[1380px] mx-auto px-6 lg:px-10">
                        <div class="text-center max-w-2xl mx-auto mb-14">
                            <h2
                                class="font-display text-4xl lg:text-5xl font-bold leading-[1.1] tracking-[-0.03em] text-black mb-4">
                                Beyond the "Virtual Assistant."
                            </h2>
                            <p class="text-base leading-relaxed text-black">
                                We specialize in globally sourcing English-fluent professionals<br class="hidden sm:block">
                                for
                                roles that require high-level execution.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card">
                            @php
                                $specialties = [
                                    [
                                        'img' => $img('magnific_half-body-shot-of-a-young_SOmwQLyUb8-1.webp'),
                                        'title' => 'Administrative &amp;<br>Executive Assistants',
                                        'desc' => 'Executive support for busy founders and teams.',
                                    ],
                                    [
                                        'img' => $img('magnific_wPmw8Jk7EI-1.webp'),
                                        'title' => 'Healthcare &amp;<br>Medical Assistants',
                                        'desc' => 'Healthcare professionals supporting clinics and practices.',
                                    ],
                                    [
                                        'img' => $img('magnific_ubzu0aUQLD-1.webp'),
                                        'title' => 'Sales &amp; Growth<br>Marketing Talents',
                                        'desc' => 'Professionals focused on growth, leads, and revenue.',
                                    ],
                                    [
                                        'img' => $img('magnific_YVjYLdkWeC-1.webp'),
                                        'title' => 'Operations &amp;<br>Finance Professionals',
                                        'desc' => 'Experts in finance, operations, and business support.',
                                    ],
                                ];
                            @endphp
                            @foreach ($specialties as $sp)
                                <div class="rl-department-card min-h-[420px]">
                                    <img class="rl-department-card__bg w-full h-full object-cover"
                                        src="{{ $sp['img'] }}" alt="{!! strip_tags($sp['title']) !!}" loading="lazy"
                                        decoding="async" width="300" height="420">
                                    <div class="rl-department-card__overlay"></div>
                                    <div class="rl-department-card__blur"></div>
                                    <div class="rl-department-card__content p-card">
                                        <h3
                                            class="font-display text-[22px] font-bold text-white leading-snug mb-2 drop-shadow-sm">
                                            {!! $sp['title'] !!}</h3>
                                        <p class="text-sm text-white/85 leading-relaxed drop-shadow-sm">
                                            {{ $sp['desc'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 4 · 2,000+ BUSINESSES (MAP & TRUST)               --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section id="trust-section" class="relative bg-[#E4ECFC] py-20 lg:py-28 overflow-hidden">
                    <div class="relative z-10 max-w-[1380px] mx-auto px-6 lg:px-10">
                        <div
                            class="flex flex-col lg:flex-row items-center lg:items-start justify-between gap-12 lg:gap-20">

                            {{-- Left: Stats Cards --}}
                            <div class="w-full max-w-[470px] flex flex-col gap-4 shrink-0">
                                {{-- Card 1: VAs Onboarded --}}
                                <div
                                    class="w-full bg-white rounded-[24px] px-6 py-4.5 sm:px-7 sm:py-5 shadow-[0_10px_30px_rgba(0,0,0,0.04)] flex items-center justify-between">
                                    <span class="font-display font-bold text-[15px] sm:text-base text-black">VAs
                                        Onboarded</span>
                                    <div class="flex items-center">
                                        <div class="flex items-center -space-x-2">
                                            <img src="{{ $img('person_01.webp') }}" alt="Remote Assistant"
                                                width="36" height="36"
                                                class="w-9 h-9 rounded-full border-2 border-white object-cover"
                                                loading="lazy" decoding="async">
                                            <img src="{{ $img('person_02.webp') }}" alt="Remote Assistant"
                                                width="36" height="36"
                                                class="w-9 h-9 rounded-full border-2 border-white object-cover"
                                                loading="lazy" decoding="async">
                                            <img src="{{ $img('Person_03.webp') }}" alt="Remote Assistant"
                                                width="36" height="36"
                                                class="w-9 h-9 rounded-full border-2 border-white object-cover"
                                                loading="lazy" decoding="async">
                                            <img src="{{ $img('Person_04.webp') }}" alt="Remote Assistant"
                                                width="36" height="36"
                                                class="w-9 h-9 rounded-full border-2 border-white object-cover"
                                                loading="lazy" decoding="async">
                                        </div>
                                        <span class="font-display font-extrabold text-base text-black ml-3">+2500</span>
                                    </div>
                                </div>

                                {{-- Card 2: Countries & Economic Impact --}}
                                <div
                                    class="w-full bg-white rounded-[24px] sm:rounded-[28px] p-6 sm:p-7 shadow-[0_15px_35px_rgba(0,0,0,0.04)] relative overflow-hidden flex flex-col justify-between min-h-[400px]">
                                    {{-- Wireframe Globe Background Graphic --}}
                                    <div
                                        class="absolute -right-12 -bottom-16 w-[300px] sm:w-[340px] pointer-events-none select-none">
                                        <img src="{{ $img('globe.webp') }}" alt="Global Coverage" width="340"
                                            height="340" class="w-full h-auto object-contain" loading="lazy"
                                            decoding="async">
                                    </div>

                                    {{-- Top Row: Countries & Flags --}}
                                    <div class="relative z-10 flex items-center justify-between mb-8">
                                        <span class="text-sm sm:text-[15px] font-bold text-black">Countries</span>
                                        <div class="flex items-center">
                                            <div class="flex items-center -space-x-1.5">
                                                <img src="{{ $img('costa-rica.webp') }}" alt="Costa Rica"
                                                    width="22" height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('equador.webp') }}" alt="Ecuador"
                                                    width="22" height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('chile.webp') }}" alt="Chile" width="22"
                                                    height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('paraguai.webp') }}" alt="Paraguay"
                                                    width="22" height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('brazil.webp') }}" alt="Brazil" width="22"
                                                    height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('colombia.webp') }}" alt="Colombia"
                                                    width="22" height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('argentina.webp') }}" alt="Argentina"
                                                    width="22" height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                                <img src="{{ $img('mexico.webp') }}" alt="Mexico" width="22"
                                                    height="22"
                                                    class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                                                    loading="lazy" decoding="async">
                                            </div>
                                            <span
                                                class="font-display font-extrabold text-sm sm:text-base text-black ml-2.5">+50</span>
                                        </div>
                                    </div>

                                    {{-- Bottom Block: Economic Impact --}}
                                    <div class="relative z-10 mt-auto">
                                        <span
                                            class="font-display text-[15px] sm:text-base font-bold text-black block mb-2">
                                            Economic Impact Created
                                        </span>

                                        {{-- Underline & Divider Row --}}
                                        <div class="w-full flex items-center mb-3">
                                            <div class="h-[2.5px] bg-black w-[170px] shrink-0"></div>
                                            <div class="h-[1px] bg-black/10 w-full"></div>
                                        </div>

                                        {{-- Amount & Timeline --}}
                                        <div class="flex items-baseline justify-between pt-0.5">
                                            <span
                                                class="font-display font-extrabold text-2xl sm:text-[28px] lg:text-[30px] text-black tracking-tight">
                                                USD 41,920,000
                                            </span>
                                            <span
                                                class="text-[11px] sm:text-xs font-bold text-black leading-tight text-right">
                                                Last 12<br>Months
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Right: Content --}}
                            <div class="w-full max-w-[480px] lg:pt-8">
                                {{-- 5 Purple Stars --}}
                                <div class="flex items-center mb-5 sm:mb-6" role="img"
                                    aria-label="5 out of 5 stars rating">
                                    @for ($i = 0; $i < 5; $i++)
                                        <svg class="w-6 h-6 text-[#9F53E7]" viewBox="0 0 24 24" fill="currentColor"
                                            aria-hidden="true">
                                            <path
                                                d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                                        </svg>
                                    @endfor
                                </div>

                                {{-- Headline --}}
                                <h2
                                    class="font-display text-3xl sm:text-[36px] lg:text-[38px] font-bold text-black leading-[1.18] tracking-tight mb-8">
                                    We've helped more than<br class="hidden sm:inline"> 2,000 businesses hire<br
                                        class="hidden sm:inline"> exceptional talent from<br class="hidden sm:inline">
                                    Latin
                                    America, the<br class="hidden sm:inline"> Caribbean, and Europe.
                                </h2>

                                {{-- Purple Pill CTA Button --}}
                                <a href="#testimonials"
                                    class="inline-flex items-center gap-6 py-4 px-7 sm:px-8 bg-[#8A2BE2] hover:bg-brand-purple-deep rounded-full text-white font-bold text-xs uppercase tracking-wider shadow-lg hover:shadow-purple-500/25 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] group">
                                    <span>Watch client testimonials</span>
                                    <span
                                        class="w-7 h-7 rounded-full border border-white/60 flex items-center justify-center shrink-0 transition-transform duration-200 group-hover:translate-x-0.5">
                                        <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </span>
                                </a>
                            </div>

                        </div>
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 5 · WHY COMPANIES CHOOSE REMOTE LEVERAGE          --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section class="bg-bg-light py-20 lg:py-24">
                    <div class="max-w-[1380px] mx-auto px-6 lg:px-10">

                        <div class="mb-12 sm:mb-14">
                            <h2
                                class="font-display text-4xl sm:text-5xl lg:text-[50px] font-bold leading-[1.08] tracking-[-0.03em] text-black">
                                Why Companies Choose<br>Remote Leverage
                            </h2>
                        </div>

                        {{-- 4 Metric Cards --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card mb-[12px]">
                            @php
                                $metrics = [
                                    [
                                        'img' => $img('hour.webp'),
                                        'title' => '$6-10 /hr',
                                        'desc' =>
                                            'Access experienced professionals at highly competitive rates. Most administrative, support, sales, and marketing roles can be filled within this range.',
                                    ],
                                    [
                                        'img' => $img('lower-cost.webp'),
                                        'title' => '70% Lower Costs',
                                        'desc' =>
                                            'Reduce hiring costs without sacrificing quality. Reinvest the savings into growth, marketing, product development, or additional hires.',
                                    ],
                                    [
                                        'img' => $img('day-average.webp'),
                                        'title' => '4-Day Average',
                                        'desc' =>
                                            'From opening a role to reviewing qualified candidates in days, not weeks. Our recruiting process is designed for speed without compromising quality.',
                                    ],
                                    [
                                        'img' => $img('quality.webp'),
                                        'title' => 'Vetted for Quality',
                                        'desc' =>
                                            'Every candidate is screened for English proficiency, experience, communication skills, and role-specific expertise before reaching your inbox.',
                                    ],
                                ];
                            @endphp
                            @foreach ($metrics as $m)
                                <div
                                    class="bg-white rounded-card p-card flex flex-col justify-between border border-black/[0.04] shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300">
                                    <div>
                                        <div class="w-full overflow-hidden rounded-xl mb-5 bg-[#f7f8fc]">
                                            <img src="{{ $m['img'] }}" alt="{{ $m['title'] }}" width="248"
                                                height="190" loading="lazy" decoding="async"
                                                class="w-full h-auto aspect-[248/190] object-cover rounded-xl">
                                        </div>
                                        <div class="p-3">
                                            <h3
                                                class="font-display text-xl sm:text-[22px] font-bold text-black tracking-[-0.02em] leading-snug mb-3">
                                                {{ $m['title'] }}</h3>
                                            <p class="text-[14px] leading-relaxed text-black">{{ $m['desc'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Comparison Table (Individual Floating Rows) --}}
                        @php
                            $rows = [
                                ['feature' => 'Time to Hire', 'diy' => '4 - 8 weeks', 'rl' => '72 hrs'],
                                ['feature' => 'Vetting Quality', 'diy' => 'Hit or miss', 'rl' => 'Top 1% pre-screened'],
                                [
                                    'feature' => 'Payroll & taxes',
                                    'diy' => 'DIY or expensive local lawyer',
                                    'rl' => 'Fully managed',
                                ],
                                [
                                    'feature' => 'Compliance risk',
                                    'diy' => 'High - misclassification, local laws',
                                    'rl' => 'Zero - 170+ countries covered',
                                ],
                                [
                                    'feature' => 'Ongoing fees',
                                    'diy' => 'Often 30-50% monthly markup',
                                    'rl' => 'One-time flat fee only',
                                ],
                                [
                                    'feature' => 'Replacement guarantee',
                                    'diy' => 'None',
                                    'rl' => '12-months, no extra costs',
                                ],
                                [
                                    'feature' => 'Centralized reporting',
                                    'diy' => 'Spreadsheets',
                                    'rl' => 'Dashboard to manage your team',
                                ],
                            ];
                        @endphp
                        <div class="flex flex-col gap-3 mb-20 sm:mb-24">
                            {{-- Header Row --}}
                            <div
                                class="bg-white rounded-card px-6 sm:px-8 py-4 border border-black/[0.04] shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
                                <div class="grid grid-cols-1 sm:grid-cols-[38%_31%_31%] items-center">
                                    <div class="hidden sm:block"></div>
                                    <div class="font-display text-base sm:text-[17px] font-bold text-black">DIY</div>
                                    <div class="font-display text-base sm:text-[17px] font-bold text-black">Remote Leverage
                                    </div>
                                </div>
                            </div>

                            {{-- Data Rows --}}
                            @foreach ($rows as $row)
                                <div
                                    class="bg-white rounded-card px-6 sm:px-8 py-4 border border-black/[0.04] shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
                                    <div class="grid grid-cols-1 sm:grid-cols-[38%_31%_31%] items-center gap-2 sm:gap-0">
                                        <div class="font-display text-[15px] sm:text-base font-bold text-black">
                                            {{ $row['feature'] }}
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-5 h-5 rounded-full bg-[#E04B3B] shrink-0 flex items-center justify-center">
                                                <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="3.5" stroke-linecap="round">
                                                    <line x1="18" y1="6" x2="6" y2="18" />
                                                    <line x1="6" y1="6" x2="18" y2="18" />
                                                </svg>
                                            </div>
                                            <span class="text-[14px] sm:text-[15px] text-black">{{ $row['diy'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-5 h-5 rounded-full bg-[#65B72E] shrink-0 flex items-center justify-center">
                                                <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <polyline points="20 6 9 17 4 12" />
                                                </svg>
                                            </div>
                                            <span class="text-[14px] sm:text-[15px] text-black">{{ $row['rl'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- From Vacancy to Onboarded --}}
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 mb-16 lg:mb-24 pt-8">
                            <h2
                                class="font-display text-4xl sm:text-5xl lg:text-[50px] font-bold leading-[1.08] tracking-[-0.03em] text-black max-w-xl">
                                From Vacancy to<br>Onboarded in 4 Days
                            </h2>
                            <p class="max-w-[480px] text-[15px] sm:text-base leading-relaxed text-black lg:pt-1">
                                Tell us who you need. We source, screen, and present qualified candidates within days,
                                helping you
                                move from an open role to a productive team member faster than traditional hiring.
                            </p>
                        </div>

                        <div class="rl-process-container">
                            <div class="rl-process-line"></div>
                            <div class="rl-process-grid">
                                @php
                                    $steps = [
                                        [
                                            'num' => '01',
                                            'title' => 'Tell us your<br>ideal hire',
                                            'desc' =>
                                                'Tell us who you need. We handle sourcing, screening, and vetting candidates so you can focus on choosing the right person.',
                                        ],
                                        [
                                            'num' => '02',
                                            'title' => 'Meet your<br>top 1% shortlist',
                                            'desc' =>
                                                'Within 48–72 hours, receive 4–6 candidates pre-vetted for skill, experience, and fit. You interview, you choose. No commitments, no pressure.',
                                        ],
                                        [
                                            'num' => '03',
                                            'title' => 'Make your<br>selection',
                                            'desc' =>
                                                'Make your selection and get back to growing your business. We handle the details so your new hire can hit the ground running.',
                                        ],
                                    ];
                                @endphp
                                @foreach ($steps as $step)
                                    <div class="rl-process-item">
                                        <div class="rl-process-marker">
                                            <span class="rl-process-number">{{ $step['num'] }}</span>
                                            <div class="rl-process-dot"></div>
                                        </div>
                                        <h3 class="rl-process-title">{!! $step['title'] !!}</h3>
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
                <section class="relative bg-brand-dark-violet overflow-hidden text-white">
                    <div class="max-w-[1380px] mx-auto px-6 lg:px-10">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">

                            <div class="pt-16 pb-12 lg:pt-24 lg:pb-24 space-y-7 max-w-xl">
                                <h2
                                    class="font-display text-4xl sm:text-5xl lg:text-[50px] font-bold leading-[1.08] tracking-[-0.03em] text-white">
                                    12-Month Replacement<br>Guarantee
                                </h2>
                                <div class="space-y-4 text-[15px] sm:text-base leading-relaxed text-white/80">
                                    <p>12-Month free replacement guarantee on any remote talent you hire through us to
                                        ensure you
                                        have a perfect fit. You'll also have a dedicated manager to help you set up
                                        training,
                                        performance tracking, and any other support you need.</p>
                                    <p class="text-white">No contracts or commitments.</p>
                                    <p>We get paid a flat hiring fee if you choose to hire a Virtual Assistant after
                                        interviewing
                                        our applicants.</p>
                                </div>
                                <div class="pt-2">
                                    <a href="#booking-footer"
                                        class="inline-flex items-center gap-4 px-8 py-3.5 bg-brand-purple hover:bg-brand-purple-deep text-white rounded-full font-display text-xs sm:text-sm font-bold uppercase tracking-[0.08em] transition-all duration-200 shadow-lg group">
                                        <span>Book a consultation</span>
                                        <span
                                            class="w-6 h-6 rounded-full border border-white/50 flex items-center justify-center shrink-0 group-hover:border-white transition-colors">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
                                                <path d="M9 18l6-6-6-6" stroke="#fff" stroke-width="2.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                    </a>
                                </div>
                            </div>

                            <div class="flex justify-center lg:justify-end self-start w-full pb-12 lg:pb-0">
                                <img src="{{ $img('Group-59-1-e1780958571501.webp') }}"
                                    alt="12-Month Replacement Guarantee" width="578" height="545" loading="lazy"
                                    decoding="async"
                                    class="w-full max-w-[460px] sm:max-w-[500px] lg:max-w-[560px] h-auto object-contain">
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Alpine lives here (below the fold) so livewire.min.js stays
                     off the TTI path. Video modal + FAQ need it; the hero does not. --}}
                <div x-data="{
                    activeVideo: null,
                    openModal(url) {
                        this.activeVideo = url;
                        document.body.style.overflow = 'hidden';
                    },
                    closeModal() {
                        this.activeVideo = null;
                        document.body.style.overflow = 'auto';
                    }
                }">
                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 7 · RESULTS, NOT PROMISES (TESTIMONIALS + FAQ)    --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section id="testimonials" class="bg-bg-light py-24 lg:py-28">
                    <div class="max-w-[1380px] mx-auto px-6 lg:px-10">

                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8 mb-12 sm:mb-16">
                            <h2
                                class="font-display text-4xl sm:text-5xl lg:text-[50px] font-bold leading-[1.08] tracking-[-0.03em] text-black">
                                Results, Not Promises
                            </h2>
                            <p class="max-w-[460px] text-[15px] sm:text-base leading-relaxed text-black lg:pt-1">
                                Don't just take our word for it, hear from business owners who've hired through Remote
                                Leverage. See
                                why quality makes all the difference!
                            </p>
                        </div>

                        @php
                            $testimonials = [
                                [
                                    'video_url' => 'https://vimeo.com/1067577208',
                                    'image' => $img('PRES-Property-Management.jpg'),
                                    'duration' => '00:38',
                                    'quote' =>
                                        '“I can\'t say enought about how every step of the way it just wowed me.”',
                                    'company' => 'PRES Property Management',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577369',
                                    'image' => $img('Coldwell-Banker.jpg'),
                                    'duration' => '00:19',
                                    'quote' =>
                                        '“I’m very impressed with the quality of my VA, she’s very intelligent and she aims to please.”',
                                    'company' => 'Coldwell Banker',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577665',
                                    'image' => $img('Carbon-Solutions-Group.jpg'),
                                    'duration' => '00:55',
                                    'quote' =>
                                        '“I really recommend Remote Leverage; it was a fast process, and the results are good.”',
                                    'company' => 'Carbon Solutions Group',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577549',
                                    'image' => $img('The-Zen-Zone-Wellness.jpg'),
                                    'duration' => '04:06',
                                    'quote' =>
                                        '“I got to talk to five amazing virtual assistants, and they all were good; it was kind of hard to make a choice at first.”',
                                    'company' => 'The Zen Zone Wellness',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577688',
                                    'image' => $img('Color-Job.jpg'),
                                    'duration' => '01:56',
                                    'quote' =>
                                        '“The transition of working with you guys was absolutely smooth and amazing.”',
                                    'company' => 'Color Job',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577383',
                                    'image' => $img('Connect-Church-Colorado.jpg'),
                                    'duration' => '02:45',
                                    'quote' =>
                                        '“She was just perfect, everything that we were looking for we found it in her.”',
                                    'company' => 'Connect Church Colorado',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577489',
                                    'image' => $img('Cash-is-King.jpg'),
                                    'duration' => '02:39',
                                    'quote' =>
                                        '“Honestly, the reason why we keep hiring is because it is so incredibly easy.”',
                                    'company' => 'Cash is King',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577248',
                                    'image' => $img('Liberty-Hill.jpg'),
                                    'duration' => '02:31',
                                    'quote' =>
                                        '“If somebody were asking me why they should work with Remote Leverage, I would say it\'s because of the quality of the candidates.”',
                                    'company' => 'Liberty Hill',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577464',
                                    'image' => $img('RE-MAX.jpg'),
                                    'duration' => '01:02',
                                    'quote' =>
                                        '“Its been about a year and a half since I\'ve been with them so far, I would definitely say go for it, it\'s been a game changer for me.”',
                                    'company' => 'RE / MAX',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577620',
                                    'image' => $img('Realty-One-Group.jpg'),
                                    'duration' => '01:43',
                                    'quote' =>
                                        '“As I look back, I was on the fence about it, It\'s probably one of the best decisions I ever made if not the best to help grow my business.”',
                                    'company' => 'Realty One Group',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577228',
                                    'image' => $img('OneUp-Sportz-01.jpg'),
                                    'duration' => '01:06',
                                    'quote' =>
                                        '“Very Very happy with the system, you guys system worked well and it was efficient.”',
                                    'company' => 'OneUp Sportz',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577598',
                                    'image' => $img('OneUp-Sportz.jpg'),
                                    'duration' => '01:40',
                                    'quote' =>
                                        '“It was a seamless process, all the applicants that we had they all had Masters in Marketing, which is awesome.”',
                                    'company' => 'OneUp Sportz',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577293',
                                    'image' => $img('Greener-Hill-Psychiatric.jpg'),
                                    'duration' => '05:59',
                                    'quote' =>
                                        '“Remote Leverage, presented six candidates and I did interview all of those very in depth, and I thought all of them were phenomenal.”',
                                    'company' => 'Greener Hill Psychiatric',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577442',
                                    'image' => $img('Diamond-Detox.jpg'),
                                    'duration' => '00:43',
                                    'quote' =>
                                        '“I\'m very impressed with the english, the capability, qualification, timeliness, they were all very timely, patient.”',
                                    'company' => 'Diamond Detox',
                                ],
                                [
                                    'video_url' => 'https://vimeo.com/1067577645',
                                    'image' => $img('Ad-Center-360.jpg'),
                                    'duration' => '00:36',
                                    'quote' => '“It was awesome the best experience I\'ve ever had as far as hiring.”',
                                    'company' => 'Ad Center 360',
                                ],
                            ];
                        @endphp

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-card mb-20">
                            @foreach ($testimonials as $t)
                                <div class="bg-transparent hover:bg-white rounded-card p-card flex flex-col justify-between border border-transparent hover:border-black/[0.04] hover:shadow-[0_12px_32px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                                    @click="openModal('{{ $t['video_url'] }}')">
                                    <div>
                                        <div
                                            class="relative w-full aspect-[4/3] rounded-xl overflow-hidden mb-4 bg-slate-900">
                                            <img src="{{ $t['image'] }}" alt="{{ $t['company'] }}" width="314"
                                                height="214" loading="lazy" decoding="async"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                            {{-- Center Glass Play Button --}}
                                            <div
                                                class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                <div
                                                    class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-white/45 backdrop-blur-xs flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110">
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white translate-x-0.5"
                                                        fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M8 5v14l11-7z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            {{-- Duration Badge (bottom right) --}}
                                            <span
                                                class="absolute bottom-2.5 right-2.5 px-2 py-0.5 rounded bg-black/60 backdrop-blur-xs text-white text-[10px] sm:text-[11px] font-medium font-mono pointer-events-none">
                                                {{ $t['duration'] }}
                                            </span>
                                        </div>
                                        <div class="pt-1 pb-2">
                                            <h3
                                                class="font-display text-[16px] sm:text-[17px] font-bold text-black leading-[1.35] tracking-[-0.01em] mb-4">
                                                {{ $t['quote'] }}
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="pb-1 mt-auto text-right">
                                        <span class="text-[11px] sm:text-[12px] text-black font-medium">
                                            {{ $t['company'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- FAQ --}}
                        <div class="w-full pt-16 lg:pt-24">
                            <h2
                                class="font-display text-4xl sm:text-5xl lg:text-[48px] font-bold leading-[1.1] tracking-[-0.03em] text-black mb-12 sm:mb-16 text-left">
                                Frequently Asked Questions
                            </h2>

                            @php
                                $faqs = [
                                    [
                                        'q' => 'What countries do you hire from?',
                                        'a' =>
                                            '<p>We focus on four key regions:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li>Latin America and The Caribbean</li><li>The Philippines</li><li>South Africa</li><li>Egypt</li></ul><p class="mt-3">Our Latin American Virtual Assistants are especially popular with US businesses, thanks to their exceptional English fluency, strong cultural alignment, and convenient time zone overlap with North America.</p>',
                                    ],
                                    [
                                        'q' => 'How do taxes & payroll work when hiring Virtual Assistants?',
                                        'a' =>
                                            '<p>Your VA is an independent contractor, so there\'s no payroll involved. If you\'d rather not manage contractor agreements, documentation, and international payments yourself, our Contractor of Record (COR) add-on puts Remote Leverage in the contracting seat – we handle onboarding, verified time tracking, and cross-border payment, and you get a single invoice.</p>',
                                    ],
                                    [
                                        'q' => 'How do you get paid?',
                                        'a' =>
                                            '<p>It\'s simple – we charge a one-time flat fee, but only after you\'ve found your perfect match.</p><p class="mt-2">Whatever hourly pay you decide to pay goes directly to the Virtual Assistant you hire.</p>',
                                    ],
                                    [
                                        'q' => 'What\'s the difference between Staffing and Recruiting Agencies?',
                                        'a' =>
                                            '<p>Staffing agencies charge monthly fees but only pay a small portion to Virtual Assistants. At Remote Leverage, we charge just one flat fee after you hire. Your Virtual Assistant receives 100% of what you pay them directly.</p>',
                                    ],
                                    [
                                        'q' => 'What if I have questions and need help after hiring?',
                                        'a' =>
                                            '<p>After hiring your Virtual Assistant, you\'ll have access to a dedicated Customer Success Manager who will help ensure your success with reviewing performance, monitoring progress, training guidance, and any other requests.</p>',
                                    ],
                                    [
                                        'q' => 'What if they don\'t turn out to be a good fit?',
                                        'a' =>
                                            '<p>We offer a 12-month replacement guarantee at no extra cost and unlimited candidate interviews to ensure you find the best match.</p>',
                                    ],
                                    [
                                        'q' => 'How is their English and Communication skills?',
                                        'a' =>
                                            '<p>We maintain extremely high standards for English fluency. All candidates must submit an English voice recording, and we only select those with fluent English and minimal accents.</p>',
                                    ],
                                    [
                                        'q' => 'Can I start with Part-time?',
                                        'a' =>
                                            '<p>Yes, you can start with either part-time or full-time. The minimum is 20 hours per week, as our most qualified Virtual Assistants prefer stable positions with consistent hours.</p>',
                                    ],
                                    [
                                        'q' => 'What time zone will they be working in?',
                                        'a' =>
                                            '<p>Your Virtual Assistant will work according to your schedule and time zone. They\'re accustomed to US hours, and you get to set the working hours that best fit your needs.</p>',
                                    ],
                                    [
                                        'q' => 'How much does the average Virtual Assistant cost?',
                                        'a' =>
                                            '<p>Virtual Assistant\'s hourly rates depend on skills, experience and region:</p><ul class="list-disc pl-5 mt-2 space-y-1"><li><strong>Entry Level:</strong> $6-$10 per hour</li><li><strong>Highly Experienced:</strong> $11-$15 per hour</li></ul><p class="mt-3">The hourly rate you agree to pay goes directly to your Virtual Assistant.</p>',
                                    ],
                                ];
                                $faqsLeft = array_slice($faqs, 0, 5, true);
                                $faqsRight = array_slice($faqs, 5, 5, true);
                            @endphp

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 lg:gap-x-16 items-start"
                                x-data="{ activeFaq: null }">
                                {{-- Left Column (items 0 to 4) --}}
                                <div class="flex flex-col">
                                    @foreach ($faqsLeft as $idx => $faq)
                                        <div class="border-b border-black">
                                            <button type="button"
                                                :aria-expanded="activeFaq === {{ $idx }} ? 'true' : 'false'"
                                                @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                                                class="w-full py-6 sm:py-7 text-left flex items-center justify-between gap-4 cursor-pointer focus:outline-none group">
                                                <span
                                                    class="font-display text-[16.5px] sm:text-[18px] font-bold text-black leading-[1.3] group-hover:opacity-75 transition-opacity pr-3">
                                                    {{ $faq['q'] }}
                                                </span>
                                                <div class="w-7 h-7 sm:w-7.5 sm:h-7.5 rounded-full border border-black flex items-center justify-center shrink-0 transition-transform duration-300"
                                                    :class="activeFaq === {{ $idx }} ? 'rotate-180' : ''">
                                                    <svg class="w-3.5 h-3.5 text-black" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        aria-hidden="true">
                                                        <path d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </button>
                                            <div x-show="activeFaq === {{ $idx }}" x-collapse
                                                style="display:none;">
                                                <div class="pb-7 pt-1 text-[15px] sm:text-base leading-relaxed text-black">
                                                    {!! $faq['a'] !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Right Column (items 5 to 9) --}}
                                <div class="flex flex-col">
                                    @foreach ($faqsRight as $idx => $faq)
                                        <div class="border-b border-black">
                                            <button type="button"
                                                :aria-expanded="activeFaq === {{ $idx }} ? 'true' : 'false'"
                                                @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                                                class="w-full py-6 sm:py-7 text-left flex items-center justify-between gap-4 cursor-pointer focus:outline-none group">
                                                <span
                                                    class="font-display text-[16.5px] sm:text-[18px] font-bold text-black leading-[1.3] group-hover:opacity-75 transition-opacity pr-3">
                                                    {{ $faq['q'] }}
                                                </span>
                                                <div class="w-7 h-7 sm:w-7.5 sm:h-7.5 rounded-full border border-black flex items-center justify-center shrink-0 transition-transform duration-300"
                                                    :class="activeFaq === {{ $idx }} ? 'rotate-180' : ''">
                                                    <svg class="w-3.5 h-3.5 text-black" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"
                                                        stroke-linecap="round" stroke-linejoin="round"
                                                        aria-hidden="true">
                                                        <path d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </div>
                                            </button>
                                            <div x-show="activeFaq === {{ $idx }}" x-collapse
                                                style="display:none;">
                                                <div class="pb-7 pt-1 text-[15px] sm:text-base leading-relaxed text-black">
                                                    {!! $faq['a'] !!}
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- SECTION 8 · READY TO SCALE (BOOKING FOOTER)               --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <section id="booking-footer"
                    class="relative bg-brand-dark-violet py-20 lg:py-28 text-white overflow-hidden">
                    {{-- Map in the bottom --}}
                    <div class="absolute inset-x-0 bottom-0 h-full max-h-[640px] bg-no-repeat bg-bottom bg-contain opacity-25 pointer-events-none"
                        style="background-image: url('{{ $img('Map.webp') }}');"></div>

                    <div class="relative z-10 max-w-[1380px] mx-auto px-6 lg:px-10">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-center">
                            {{-- Left Column --}}
                            <div class="lg:col-span-6 xl:col-span-7 max-w-xl">
                                <h2
                                    class="font-display text-4xl sm:text-5xl lg:text-[56px] xl:text-[62px] font-bold leading-[1.05] tracking-[-0.03em] text-white mb-6">
                                    Ready to scale your<br>global team?
                                </h2>
                                <p class="text-[16px] sm:text-[18px] leading-relaxed text-white/80 max-w-lg">
                                    During this meeting we will go over the role you're planning to hire for, what the
                                    process looks
                                    like, answer any questions you have, and proceed to next steps.
                                </p>
                            </div>

                            {{-- Right Column: Form --}}
                            <div class="lg:col-span-6 xl:col-span-5 w-full">
                                <livewire:booking.multistep-booking-wizard :skin="'glass'" />
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ═══════════════════════════════════════════════════════════ --}}
                {{-- MODAL · VIMEO VIDEO PLAYER                                 --}}
                {{-- ═══════════════════════════════════════════════════════════ --}}
                <div x-show="activeVideo" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/88 backdrop-blur-sm"
                    style="display:none;" @keydown.escape.window="closeModal()">
                    <div class="relative w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden"
                        @click.outside="closeModal()">
                        <button type="button" @click="closeModal()"
                            class="absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-white/20 hover:bg-white/40 flex items-center justify-center text-white transition-colors focus:outline-none"
                            aria-label="Close video player modal">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                        </button>
                        <template x-if="activeVideo">
                            <iframe
                                :src="'https://player.vimeo.com/video/' + activeVideo.split('/').pop() +
                                    '?autoplay=1&badge=0&autopause=0&player_id=0&app_id=58479'"
                                title="Vimeo video player" class="w-full h-full border-0"
                                allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                        </template>
                    </div>
                </div>

                </div>

            </div>
        @endif
    @endwhile
@endsection
