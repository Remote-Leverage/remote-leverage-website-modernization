{{-- Production's Contractor Management hero: pale #F4F6FC band over a faint world-map
     graphic, headline + subtitle + purple pill CTA on the left, a product composite image
     on the right, and three stat metrics beneath separated by vertical hairline dividers.
     Used on /contractor-management/. --}}
@php
    $statIcons = [
        'people' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><circle cx="17" cy="9" r="2.2"/><path d="M15 19c0-2.2 1.3-3.8 3.4-3.8 1.3 0 2.1.6 2.6 1.4"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.7 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.7-3.6-9s1.2-6.5 3.6-9z"/>',
        'speed' => '<path d="M12 20a8 8 0 1 1 8-8"/><path d="M12 12l4.5-3"/><circle cx="12" cy="12" r="1.4"/>',
    ];
@endphp

<section class="relative w-full overflow-hidden bg-no-repeat bg-center bg-cover bg-bg-light"
    style="background-image:url('{{ $mapImage }}');">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_700px] gap-10 items-center pt-14 lg:pt-16">
            <div>
                <h1 class="font-display font-bold text-[34px] leading-[40px] sm:text-[48px] sm:leading-[54px] tracking-[-1.44px] text-black mb-5">
                    {!! nl2br(e($headline)) !!}
                </h1>

                <p class="text-[16px] leading-[26px] text-black/75 max-w-[460px] mb-10">{{ $subtitle }}</p>

                @if ($ctaText)
                    <a href="{{ $ctaUrl }}"
                        class="inline-flex items-center gap-3 rounded-pill bg-brand-purple px-8 py-4 font-display text-[17px] font-bold uppercase tracking-[-0.51px] text-white transition hover:opacity-90">
                        <span>{{ $ctaText }}</span>
                        @include('partials.icon-circle-arrow', ['class' => 'w-[22px] h-[22px] shrink-0'])
                    </a>
                @endif
            </div>

            <div>
                <img src="{{ $heroImage }}" alt="" width="563" height="467" loading="eager" fetchpriority="high"
                    decoding="async" class="w-full h-auto object-contain">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 pb-14 lg:pb-16 pt-10">
            @foreach ($stats as $stat)
                <div @class([
                    'flex items-center justify-between gap-6 px-0 sm:px-8',
                    'sm:border-l sm:border-black/15' => ! $loop->first,
                ])>
                    <div>
                        <h2 class="font-display font-bold text-[40px] leading-[46px] sm:text-[48px] sm:leading-[53px] text-black">
                            {{ $stat['value'] }}
                        </h2>
                        <p class="text-[15px] text-black/70">{{ $stat['label'] }}</p>
                    </div>

                    <svg class="w-8 h-8 shrink-0 text-black/40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        {!! $statIcons[$stat['icon']] ?? $statIcons['people'] !!}
                    </svg>
                </div>
            @endforeach
        </div>

    </div>
</section>
