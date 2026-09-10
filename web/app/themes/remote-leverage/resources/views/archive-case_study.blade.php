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
    {{-- Hero: dark gradient band matching the case-study single template family --}}
    <div class="w-full" style="background-image:linear-gradient(65deg, #270028 0%, #4E1450 100%)">
        <div class="max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-14 sm:pt-20 sm:pb-16">
            <span class="inline-flex items-center text-xs font-bold tracking-[0.1em] text-white uppercase mb-4">
                Case Studies
            </span>
            <h1 class="font-display text-3xl sm:text-4xl lg:text-[48px] font-medium text-[#FFFBFF] tracking-[0.01em] leading-tight mb-5 max-w-2xl">
                Real Hiring Outcomes. Exceptional Remote Talent.
            </h1>
            <p class="text-base sm:text-lg font-light text-white/85 leading-relaxed max-w-2xl mb-14">
                These case studies show what happens when companies hire exceptional remote talent. See how founders and teams use Remote Leverage to expand capacity, improve execution, and scale with confidence.
            </p>

            {{-- Client logo marquee --}}
            @if (! empty($allCaseStudies))
                <div class="px-4 overflow-hidden py-4 maskshadow">
                    <div class="animate-marquee-logos flex items-center gap-12 sm:gap-14">
                        @foreach (array_merge($allCaseStudies, $allCaseStudies) as $cs)
                            @php
                                $logoUrl = get_post_meta($cs->ID, 'case_study_index_logo', true) ?: get_the_post_thumbnail_url($cs->ID, 'medium');
                            @endphp
                            @if (! empty($logoUrl))
                                <div class="shrink-0 h-8 flex items-center">
                                    <img src="{{ $logoUrl }}" alt="{{ get_the_title($cs) }}" width="140" height="32"
                                        class="h-8 w-auto max-w-[140px] object-contain brightness-0 [filter:brightness(0)_saturate(100%)_invert(37%)_sepia(89%)_saturate(4211%)_hue-rotate(280deg)_brightness(101%)_contrast(101%)]"
                                        loading="lazy" decoding="async">
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Case study cards --}}
    <div class="w-full max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($allCaseStudies as $cs)
                @php
                    $tags = get_post_meta($cs->ID, 'case_study_tags', true);
                    $tags = is_array($tags) ? $tags : (json_decode((string) $tags, true) ?: []);
                    $logoUrl = get_post_meta($cs->ID, 'case_study_index_logo', true) ?: get_the_post_thumbnail_url($cs->ID, 'medium');
                @endphp
                <div class="bg-white rounded-[5px] p-8 sm:p-10 flex flex-col border border-[#FB33FF]">
                    <div class="mb-8 h-12 flex items-center">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ get_the_title($cs) }}"
                                class="max-h-12 w-auto max-w-44 object-contain [filter:brightness(0)_saturate(100%)_invert(11%)_sepia(52%)_saturate(3207%)_hue-rotate(276deg)_brightness(90%)_contrast(101%)]"
                                loading="lazy" decoding="async">
                        @else
                            <span class="font-display text-sm font-bold text-[#4E1450] uppercase tracking-wide">
                                {{ get_the_title($cs) }}
                            </span>
                        @endif
                    </div>

                    <h3 class="font-display text-xl sm:text-2xl font-medium text-black tracking-[-0.01em] leading-snug mb-6 grow">
                        {{ get_the_title($cs) }}
                    </h3>

                    @if (! empty($tags))
                        <div class="flex flex-wrap gap-2 mb-6">
                            @foreach ($tags as $tag)
                                <span class="text-sm font-medium text-[#4E1450] bg-[#FECEFF] rounded-full px-4.5 py-2">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    <a href="{{ get_permalink($cs) }}"
                        class="inline-flex items-center justify-center bg-[#4E1450] hover:bg-[#3A0F3D] text-white text-sm font-bold rounded-md px-8 py-3.5 transition-colors self-start">
                        Read More
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
