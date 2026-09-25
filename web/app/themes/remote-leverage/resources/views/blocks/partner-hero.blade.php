{{-- Production's co-branded partner hero (/remote-leverage-x-oyster/, /remote-leverage-x-lano/):
     a full-bleed partner-brand gradient over the shared world map, everything centre-aligned — the
     "Brand × Remote Leverage" title, a stack of intro paragraphs with the first in bold, a
     black pill CTA, and four translucent stat cards along the bottom. The partner profile row
     that sits between the CTA and the stats is acf/talent-grid in `row` layout. --}}
@php
    $statIcons = [
        'bars' => '<path d="M4 17.5h16M6.5 17.5V11M11 17.5V7.5M15.5 17.5v-4M20 17.5V5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.3 2"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.5 3.6 5.7 3.6 9s-1.2 6.5-3.6 9c-2.4-2.5-3.6-5.7-3.6-9s1.2-6.5 3.6-9z"/>',
        'trend' => '<path d="M4 18L10 12l3.5 3.5L20 8"/><path d="M15 8h5v5"/>',
    ];
@endphp

@php
    // `tone: light` swaps the partner-brand gradient for production's #F4F6FC band with black
    // copy and a full-opacity illustration backdrop (/ecommerce-virtual-assistant/'s hero).
    $isLight = ($tone ?? 'brand-gradient') === 'light';
    $heroBg = $isLight
        ? 'background-color:#F4F6FC;'
        : 'background-image:linear-gradient(160deg,'.$brandColor.' 0%,'.$brandColorEnd.' 100%);';
    $heroText = $isLight ? 'text-black' : 'text-white';
    $heroBody = $isLight ? 'text-black/80' : 'text-white/90';
@endphp

{{-- rl-partner-hero is a styling hook, not a utility: app.css uses it to add header
     clearance on the pages where the site header floats over this hero (body class
     rl-header-over-hero). Without it the headline renders under the logo. --}}
<section class="rl-partner-hero relative w-full overflow-hidden pt-16 lg:pt-20" style="{{ $heroBg }}">
    {{-- The shared world map on brand heroes; a full-opacity illustration on the light tone. --}}
    <div @class([
            'pointer-events-none absolute inset-x-0 top-0 h-full z-0 bg-no-repeat bg-top',
            'opacity-25 bg-contain' => ! $isLight,
            'bg-cover' => $isLight,
        ])
        style="background-image:url('{{ $backdrop }}');"></div>

    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 text-center">

        {{-- Co-brand wordmark, top-left above the headline. Left-aligned against the
             centred hero, matching production's first content widget on the partner pages. --}}
        @if (! empty($lockup))
            <img src="{{ $lockup }}"
                alt="{{ $lockupLabel ?: 'Partner lockup' }}"
                class="mb-10 h-[18px] w-auto max-w-full mx-auto sm:mx-0"
                width="229" height="18" loading="eager" decoding="sync" />
        @endif

        <h2 class="font-display font-bold text-[34px] leading-[42px] sm:text-[52px] sm:leading-[60px] tracking-[-1.56px] {{ $heroText }} mb-6 max-w-[860px] mx-auto">
            {!! nl2br(e($headline)) !!}
        </h2>

        @foreach ($paragraphs as $paragraph)
            <p @class([
                'text-[16px] leading-[26px] mb-4 max-w-[720px] mx-auto',
                $heroBody,
                'font-bold' => $loop->first,
            ])>{{ $paragraph }}</p>
        @endforeach

        @if ($ctaText)
            <a href="{{ $ctaUrl }}"
                class="mt-7 inline-flex items-center gap-3 rounded-pill bg-black px-8 py-4 font-display text-[17px] font-bold uppercase tracking-[-0.51px] text-white transition hover:opacity-90">
                <span>{{ $ctaText }}</span>
                @include('partials.icon-circle-arrow', ['class' => 'w-[22px] h-[22px] shrink-0'])
            </a>
        @endif

        {{-- Checked reassurance pills, read row-major in two columns. Production's ecommerce
             hero runs six of these between the subhead and the CTA. --}}
        @if (! empty($badges))
            {{-- Shared with acf/checklist-grid, so the two cannot drift. --}}
            @include('blocks.partials.checklist-grid', [
                'items' => array_map(fn ($badge) => $badge['text'] ?? '', $badges),
                'class' => 'mt-8 max-w-[720px] mx-auto',
            ])
        @endif

        @if ($talentHtml)
            <div class="mt-12">{!! $talentHtml !!}</div>
        @endif

        @if (! empty($stats))
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 pb-16 pt-12 text-left">
                @foreach ($stats as $stat)
                    <div class="rounded-card p-6 backdrop-blur-sm" style="background-color:rgba(255,255,255,0.10);">
                        <div class="flex items-start justify-between gap-3 mb-6">
                            <h2 class="font-display font-bold text-[38px] leading-[44px] tracking-[-1.14px] text-white">
                                {{ $stat['value'] }}
                            </h2>

                            <svg class="w-8 h-8 shrink-0 text-white/60" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"
                                aria-hidden="true">
                                {!! $statIcons[$stat['icon']] ?? $statIcons['bars'] !!}
                            </svg>
                        </div>

                        <p class="text-[13px] leading-[19px] text-white/85">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</section>
