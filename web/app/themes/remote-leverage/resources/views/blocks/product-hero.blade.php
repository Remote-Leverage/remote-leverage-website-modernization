<section class="relative w-full bg-[#18112C] overflow-hidden py-16 sm:py-20 lg:py-24 text-white" style="background-color: #18112C; background-image: url('{{ get_theme_file_uri('public/images/home/Union-5.png') }}'); background-position: center center; background-repeat: no-repeat; background-size: contain;">
    {{-- Blurred Radial Gradients matching Production --}}
    <div class="pointer-events-none absolute -top-40 -left-40 w-[600px] h-[600px] rounded-full bg-[#8A2BE2]/25 blur-[160px] z-0"></div>
    <div class="pointer-events-none absolute -bottom-40 -right-40 w-[600px] h-[600px] rounded-full bg-[#8A2BE2]/20 blur-[160px] z-0"></div>

    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-center">
            
            {{-- Left Column: Copy, CTAs, and Pill Metrics --}}
            <div class="lg:col-span-7 flex flex-col items-start text-left">
                @if (!empty($badge))
                    <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-white/10 border border-white/15 text-xs font-bold uppercase tracking-wider text-purple-200 mb-6 backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-[#00D982]"></span>
                        <span>{{ $badge }}</span>
                    </div>
                @endif

                <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-bold tracking-tight text-white leading-[1.1] mb-6">
                    {!! nl2br(e($headline)) !!}
                </h1>

                <p class="text-base sm:text-lg lg:text-xl text-slate-300 leading-relaxed max-w-xl mb-8">
                    {!! nl2br(e($subtitle)) !!}
                </p>

                {{-- CTA Buttons --}}
                <div class="flex flex-wrap items-center gap-4 mb-10">
                    @if (!empty($cta_primary_text))
                        <a 
                            href="{{ $cta_primary_url }}"
                            class="px-8 py-3.5 rounded-full bg-white text-slate-900 hover:bg-slate-100 font-bold text-sm sm:text-base shadow-xl transition duration-200 cursor-pointer"
                        >
                            {{ $cta_primary_text }} &rarr;
                        </a>
                    @endif

                    @if (!empty($cta_secondary_text))
                        <a 
                            href="{{ $cta_secondary_url }}"
                            class="px-8 py-3.5 rounded-full bg-transparent hover:bg-white/10 text-white font-semibold text-sm sm:text-base border border-white/30 transition duration-200 cursor-pointer"
                        >
                            {{ $cta_secondary_text }}
                        </a>
                    @endif
                </div>

                {{-- Metric Pill Tiles Matching Production (3 items) --}}
                @if (!empty($stats))
                    <div class="flex flex-wrap gap-3 sm:gap-4 pt-2">
                        @php
                            $metricIcons = [
                                'ICONS-outlined-1-1.png',
                                'ICONS14-1-1-1.png',
                                'ICONS-1-1.png',
                            ];
                        @endphp
                        @foreach ($stats as $idx => $stat)
                            @php
                                $iconName = $metricIcons[$idx % count($metricIcons)];
                                $iconPath = 'public/images/contractor-management/' . $iconName;
                                $iconUri = file_exists(get_theme_file_path($iconPath)) ? get_theme_file_uri($iconPath) : '';
                            @endphp
                            <div class="inline-flex items-center gap-2.5 px-4 py-2 rounded-full bg-white/10 border border-white/15 backdrop-blur-md text-xs sm:text-sm text-white">
                                @if ($iconUri)
                                    <img src="{{ $iconUri }}" alt="" class="w-4 h-4 object-contain" />
                                @endif
                                <span class="font-bold text-white">{{ $stat['value'] ?? '' }}</span>
                                <span class="text-slate-300">{{ $stat['label'] ?? '' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Right Column: Main Dashboard Graphic --}}
            <div class="lg:col-span-5 w-full flex items-center justify-center lg:justify-end">
                @php
                    $heroImageSrc = '';
                    if (!empty($image_file) && file_exists(get_theme_file_path($image_file))) {
                        $heroImageSrc = get_theme_file_uri($image_file);
                    } else {
                        $defaultHero = 'public/images/contractor-management/hero-main.png';
                        if (file_exists(get_theme_file_path($defaultHero))) {
                            $heroImageSrc = get_theme_file_uri($defaultHero);
                        }
                    }
                @endphp

                @if ($heroImageSrc)
                    <div class="relative w-full max-w-[560px] transition-transform duration-300 hover:scale-[1.02]">
                        <img 
                            src="{{ $heroImageSrc }}" 
                            alt="Contractor Management Dashboard" 
                            class="w-full h-auto drop-shadow-[0_20px_50px_rgba(0,0,0,0.5)] rounded-2xl"
                        />
                    </div>
                @endif
            </div>

        </div>
    </div>
</section>
