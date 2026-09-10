{{--
  Native archive for the case_study CPT (/case-study/).
  Queries the current archive loop directly rather than a hardcoded card list,
  so the grid always reflects real, published case-study entries.
--}}
@extends('layouts.app')

@php
    $allCaseStudies = [];
    if (have_posts()) {
        while (have_posts()) {
            the_post();
            $allCaseStudies[] = get_post();
        }
        rewind_posts();
    }
@endphp

@section('content')
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
        {{-- Hero --}}
        <div class="max-w-3xl mb-14 sm:mb-16">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-brand-purple/10 text-brand-purple text-xs font-bold tracking-wide uppercase mb-5">
                Case Studies
            </span>
            <h1 class="font-display text-3xl sm:text-4xl lg:text-[44px] font-bold text-black tracking-[-0.03em] leading-tight mb-5">
                Real Hiring Outcomes. Exceptional Remote Talent.
            </h1>
            <p class="text-base sm:text-lg text-black/70 leading-relaxed">
                These case studies show what happens when companies hire exceptional remote talent. See how founders and teams use Remote Leverage to expand capacity, improve execution, and scale with confidence.
            </p>
        </div>

        {{-- Client logo marquee --}}
        @if (! empty($allCaseStudies))
            <div class="rl-logo-marquee-wrapper px-4 overflow-hidden py-4 mb-16 sm:mb-20 maskshadow">
                <div class="animate-marquee-logos flex items-center gap-12 sm:gap-14">
                    @foreach (array_merge($allCaseStudies, $allCaseStudies) as $cs)
                        @php
                            $logoUrl = get_the_post_thumbnail_url($cs->ID, 'medium');
                        @endphp
                        @if (! empty($logoUrl))
                            <div class="rl-logo-marquee-item shrink-0">
                                {{-- filter-none/opacity-100: these client logos are opaque PNGs (not
                                transparent-background marks), so the shared marquee mono-silhouette
                                filter (brightness(0)) would render them as solid black squares --}}
                                <img src="{{ $logoUrl }}" alt="{{ get_the_title($cs) }}" width="140" height="48"
                                    class="h-10 w-auto object-contain filter-none opacity-100" loading="lazy" decoding="async">
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Case study cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-card">
            @foreach ($allCaseStudies as $cs)
                @php
                    $tags = get_post_meta($cs->ID, 'case_study_tags', true);
                    $tags = is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []);
                    $logoUrl = get_the_post_thumbnail_url($cs->ID, 'medium');
                @endphp
                <a href="{{ get_permalink($cs) }}"
                    class="bg-white rounded-card p-card flex flex-col border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300">
                    <div class="mb-6 h-10 flex items-center">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ get_the_title($cs) }}"
                                class="max-h-10 w-auto max-w-40 object-contain" loading="lazy" decoding="async">
                        @else
                            <span class="font-display text-sm font-bold text-brand-purple uppercase tracking-wide">
                                {{ get_the_title($cs) }}
                            </span>
                        @endif
                    </div>

                    <h3 class="font-display text-lg font-bold text-black tracking-[-0.02em] leading-snug mb-5 grow">
                        {{ get_the_title($cs) }}
                    </h3>

                    @if (! empty($tags))
                        <div class="flex flex-wrap gap-2 mb-5">
                            @foreach ($tags as $tag)
                                <span class="text-[11px] font-medium text-black/60 bg-black/4 rounded-full px-2.5 py-1">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <span class="text-sm font-bold text-brand-purple inline-flex items-center gap-1">
                        Read More
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
