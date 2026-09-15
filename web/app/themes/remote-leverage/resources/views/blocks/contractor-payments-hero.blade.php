{{-- Production's Contractor Payments hero: a left-to-right black → #581FB0 gradient band,
     headline/subtitle/pill CTA in the left column, and a portrait with two floating UI cards
     over a large brand shape that bleeds past the right viewport edge. Three stat metrics
     (value, label, outline icon) sit along the bottom of the same band. Used on
     /contractor-payments/. --}}
@php
    $statIcons = [
        'dollar' => '<circle cx="12" cy="12" r="10"/><path d="M14.5 9.5c-.5-.9-1.5-1.4-2.5-1.4-1.4 0-2.5.8-2.5 1.9s1.1 1.6 2.5 1.9 2.5.8 2.5 1.9-1.1 1.9-2.5 1.9c-1 0-2-.5-2.5-1.4M12 6.5v11"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2c2.6 2.8 4 6.3 4 10s-1.4 7.2-4 10c-2.6-2.8-4-6.3-4-10s1.4-7.2 4-10z"/>',
        'shield' => '<path d="M12 3l7 3v5.5c0 4.3-3 8.2-7 9.5-4-1.3-7-5.2-7-9.5V6l7-3z"/><path d="M9.5 12l1.8 1.8 3.5-3.6"/>',
    ];
@endphp

<section class="relative w-full overflow-hidden" style="background-image:linear-gradient(90deg,var(--color-surface-black) 0%,var(--color-brand-purple-deep) 100%);">

    {{-- Desktop composition: anchored to the section, not the container, so it bleeds. --}}
    <div class="pointer-events-none absolute right-0 top-0 hidden lg:block w-[700px] h-[610px]">
        <img src="{{ $shape }}" alt="" width="470" height="470" loading="eager" decoding="async"
            class="absolute right-[120px] top-[30px] w-[470px] h-auto max-w-none object-contain opacity-85">
        <img src="{{ $portrait }}" alt="" width="368" height="600" loading="eager" fetchpriority="high" decoding="async"
            class="absolute right-[170px] bottom-0 h-[500px] w-auto max-w-none object-contain">
        <img src="{{ $cardTop }}" alt="" width="217" height="145" loading="eager" decoding="async"
            class="absolute right-[10px] top-[175px] w-[217px] h-auto">
        <img src="{{ $cardBottom }}" alt="" width="217" height="145" loading="eager" decoding="async"
            class="absolute right-[430px] bottom-[120px] w-[217px] h-auto">
    </div>

    <div class="relative w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

        <div class="pt-14 lg:pt-28 pb-8 lg:pb-16 max-w-full lg:max-w-[620px] lg:min-h-[610px]">
            <h1 class="font-display font-bold text-[34px] leading-[40px] sm:text-[46px] sm:leading-[53px] tracking-[-1.44px] text-bg-light mb-5">
                {!! nl2br(e($headline)) !!}
            </h1>

            <p class="text-[16px] leading-[26px] text-white/80 max-w-[470px] mb-10">{{ $subtitle }}</p>

            @if ($ctaText)
                <a href="{{ $ctaUrl }}"
                    class="inline-flex items-center gap-3 rounded-pill bg-brand-purple px-8 py-4 sm:px-10 sm:py-5 font-display text-[17px] sm:text-[20px] font-bold uppercase tracking-[-0.6px] text-white transition hover:opacity-90">
                    <span>{{ $ctaText }}</span>
                    @include('partials.icon-circle-arrow', ['class' => 'w-6 h-6 shrink-0'])
                </a>
            @endif
        </div>

        {{-- Mobile/tablet: the bleeding version is desktop-only, so stack it instead. --}}
        <div class="relative h-[320px] sm:h-[420px] mb-6 lg:hidden">
            <img src="{{ $shape }}" alt="" loading="lazy" decoding="async"
                class="absolute right-[40px] top-0 w-[260px] sm:w-[340px] h-auto max-w-none object-contain opacity-85">
            <img src="{{ $portrait }}" alt="" loading="lazy" decoding="async"
                class="absolute right-[70px] bottom-0 h-[86%] w-auto object-contain">
            <img src="{{ $cardTop }}" alt="" loading="lazy" decoding="async"
                class="absolute right-0 top-[30%] w-[150px] h-auto">
            <img src="{{ $cardBottom }}" alt="" loading="lazy" decoding="async"
                class="absolute left-0 bottom-[16%] w-[150px] h-auto">
        </div>

        <div class="relative grid grid-cols-1 sm:grid-cols-3 gap-8 pb-14 lg:pb-20">
            @foreach ($stats as $stat)
                <div class="flex items-center gap-12 sm:gap-20">
                    <div class="min-w-[150px]">
                        <p class="font-display font-bold text-[40px] leading-[50px] sm:text-[48px] sm:leading-[63px] tracking-[-1.44px] text-white">
                            {{ $stat['value'] }}
                        </p>
                        <p class="text-[16px] text-white/80">{{ $stat['label'] }}</p>
                    </div>

                    <svg class="w-9 h-9 shrink-0 text-white/35" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $statIcons[$stat['icon']] ?? $statIcons['dollar'] !!}
                    </svg>
                </div>
            @endforeach
        </div>

    </div>
</section>
