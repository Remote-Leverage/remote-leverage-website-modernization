{{-- Case study template body: dark hero band, then the study's prose, metrics and quotes. Rendered
     from case_study post content.

     Type, colour and component sizing measured off https://remoteleverage.com/case-study/chick-fil-a/
     and /case-study/bench-accounting/ with getComputedStyle at 1440px (2026-09-15):
       eyebrow       16px / 24px, weight 600, copy already uppercase
       client name   60px / 60px, weight 700, #FFFFFF
       headline      48px / 56px, weight 500, #FFFBFF
       logo tile     434 x 434, 20px radius, white
       info rows     18px / 32px, label weight 500 / value weight 300, label column 209px
       stat value    46px / 69px, weight 500 · stat label 26px / 39px, weight 500
       hero stats    three fixed 240px columns; Outcomes stats two fixed 340px columns
       pull quote    50px / 56px, weight 500, #333, full container width
       attribution   24px / 36px, #333 · headshot 100 x 100
       section head  30px / 40px, weight 500, #333
       body copy     18px / 28px, #333

     Container is deliberately NOT production's 1240px: the canonical theme container is
     1380px (CLAUDE.md "Conventions" rule 1 / docs/design-system.md rule 1). Width is the one
     value not measured off production. --}}
@php
    $proseClasses = '[&_p]:mb-4 [&_p:last-child]:mb-0 [&_p]:text-[#333333] [&_p]:leading-7 [&_p]:text-lg '
        .'[&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul]:space-y-2 [&_ul]:text-[#333333] [&_ul]:text-lg '
        .'[&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol]:space-y-2 [&_ol]:text-[#333333] [&_ol]:text-lg '
        .'[&_li]:leading-7 '
        .'[&_a]:text-brand-purple [&_a]:underline [&_a]:decoration-brand-purple/40 hover:[&_a]:decoration-brand-purple '
        .'[&_strong]:font-bold [&_strong]:text-black [&_b]:font-bold [&_b]:text-black '
        // A `table` section renders through the grid above, but a table pasted as raw HTML into a
        // richtext section lands here instead, and until now inherited nothing: `table-layout:auto`
        // with 0px cell padding put the second column flush against the container edge and ran it
        // into the first with no gutter. Styled to match the `table` renderer so the two are
        // indistinguishable. `table-fixed` + `w-1/2` is what stops a long cell sizing a column to
        // its content, and `break-words` keeps a long unbroken token from reintroducing overflow.
        .'[&_table]:w-full [&_table]:my-6 [&_table]:table-fixed [&_table]:border-collapse '
        .'[&_th]:w-1/2 [&_th]:px-3 [&_th]:py-3 [&_th]:text-left [&_th]:align-top [&_th]:bg-black/[0.03] '
        .'[&_th]:text-xs [&_th]:font-bold [&_th]:text-[#333333] [&_th]:uppercase [&_th]:tracking-wide '
        .'sm:[&_th]:px-5 sm:[&_th]:text-sm '
        .'[&_td]:px-3 [&_td]:py-3 [&_td]:text-left [&_td]:align-top [&_td]:text-sm [&_td]:leading-6 '
        .'[&_td]:text-[#333333] [&_td]:border-b [&_td]:border-black/6 [&_td]:break-words '
        .'sm:[&_td]:px-5';
@endphp

{{-- Hero: dark band matching production's case-study template family --}}
<div class="w-full bg-[#270028] pt-14 pb-20 sm:pb-24">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <span class="inline-flex items-center text-base leading-6 font-semibold text-white uppercase mb-6">
            Case Study
        </span>

        @if (! empty($clientName))
            <h1 class="font-display text-4xl sm:text-5xl lg:text-[60px] lg:leading-[60px] font-bold text-white tracking-[-0.02em] leading-tight mb-8 sm:mb-10">
                {{ $clientName }}
            </h1>
        @endif

        <div class="flex flex-col sm:flex-row gap-8 sm:gap-0">
            <div class="sm:w-[35%]">
                @if (! empty($logo))
                    <div class="w-full max-w-[434px] aspect-square rounded-[20px] overflow-hidden bg-white p-8">
                        @if (! empty($websiteUrl))
                            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer" class="block w-full h-full">
                                <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-full object-contain" loading="lazy" decoding="async">
                            </a>
                        @else
                            <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-full object-contain" loading="lazy" decoding="async">
                        @endif
                    </div>
                @endif
            </div>

            <div class="sm:w-[65%] sm:pl-12">
                <h2 class="font-display text-2xl sm:text-3xl lg:text-[48px] lg:leading-[56px] font-medium text-[#FFFBFF] tracking-[0.01em] leading-tight mb-5">
                    {!! $headline !!}
                </h2>

                @if (! empty($subheadline))
                    <p class="text-base sm:text-lg text-white/70 leading-relaxed mb-5">
                        {{ $subheadline }}
                    </p>
                @endif

                @if (! empty($infoItems))
                    <div class="flex flex-col mb-10">
                        @foreach ($infoItems as $item)
                            @if (! empty($item['value']))
                                {{-- The 193px label column is a desktop measurement and only holds from `sm`
                                     up. At 414px it left 173px for the value, which a bare domain cannot
                                     wrap into — `nativehawaiianphilanthropy.org` pushed the document to
                                     485px wide. Stacked below `sm`, and `break-words` so a long unbroken
                                     token breaks instead of widening the page. --}}
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-4 text-lg leading-8">
                                    <span class="font-medium text-white sm:w-[193px] sm:shrink-0">{{ $item['label'] }}:</span>
                                    <span class="font-light text-white min-w-0 break-words">{{ $item['value'] }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if (! empty($stats))
                    <div class="flex flex-wrap gap-y-6">
                        @foreach ($stats as $stat)
                            <div class="w-full sm:w-[240px]">
                                <div class="font-display text-3xl sm:text-4xl lg:text-[46px] lg:leading-[69px] font-medium text-white leading-none">
                                    {{ $stat['value'] }}
                                </div>
                                <div class="text-xl sm:text-[26px] sm:leading-[39px] font-medium text-white leading-snug">
                                    {{ $stat['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Pull quote --}}
    @if (! empty($quoteText))
        <div class="py-16 sm:py-20">
            <p class="font-display text-2xl sm:text-3xl lg:text-[50px] lg:leading-[56px] font-medium text-[#333333] tracking-[-0.01em] leading-tight mb-6">
                &ldquo;{{ $quoteText }}&rdquo;
            </p>
            <div class="flex items-center gap-4">
                @if (! empty($quotePhoto))
                    <img src="{{ $quotePhoto }}" alt="{{ $quoteName }}" width="100" height="100"
                        class="w-[100px] h-[100px] rounded-full object-cover shrink-0" loading="lazy" decoding="async">
                @endif
                <div class="text-2xl leading-9">
                    @if (! empty($quoteName))
                        <div class="text-[#333333] font-medium">{{ $quoteName }}</div>
                    @endif
                    @if (! empty($quoteCompany))
                        <div class="text-[#333333] font-medium">{{ $quoteCompany }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Narrative sections: 35% label column / 65% content column, matching production's spec-sheet layout --}}
    <div class="flex flex-col gap-12 sm:gap-14 pb-16 sm:pb-20">
        @foreach ($sections as $section)
            <div class="flex flex-col sm:flex-row gap-4 sm:gap-0">
                <div class="sm:w-[35%]">
                    @if (! empty($section['heading']))
                        <h2 class="font-display text-2xl sm:text-[30px] sm:leading-10 font-medium text-[#333333] tracking-[-0.01em] leading-tight">
                            {{ $section['heading'] }}
                        </h2>
                    @endif
                </div>

                <div class="sm:w-[65%] sm:pl-12">
                    @if ($section['type'] === 'stats' && ! empty($section['rows']))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-8 max-w-[700px]">
                            @foreach ($section['rows'] as $row)
                                <div class="sm:w-[340px]">
                                    <div class="font-display text-3xl sm:text-4xl lg:text-[46px] lg:leading-[69px] font-medium text-black leading-none">
                                        {{ $row['value'] }}
                                    </div>
                                    <div class="text-xl sm:text-[26px] sm:leading-[39px] font-medium text-black leading-snug">
                                        {{ $row['label'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($section['type'] === 'table' && ! empty($section['rows']))
                        <div class="flex flex-col gap-2 w-full">
                            <div class="grid grid-cols-2 bg-black/[0.03] rounded-card px-5 sm:px-6 py-3">
                                <span class="text-xs sm:text-sm font-bold text-[#333333] uppercase tracking-wide">Success Metric</span>
                                <span class="text-xs sm:text-sm font-bold text-[#333333] uppercase tracking-wide">Outcome</span>
                            </div>
                            @foreach ($section['rows'] as $row)
                                <div class="grid grid-cols-2 border-b border-black/6 px-5 sm:px-6 py-3">
                                    <span class="text-sm text-[#333333]">{{ $row['value'] }}</span>
                                    <span class="text-sm text-[#333333] font-medium">{{ $row['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @elseif (! empty($section['body']))
                        <div class="{{ $proseClasses }}">
                            {!! $section['body'] !!}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Client video --}}
    @if (! empty($video))
        <div class="flex flex-col sm:flex-row gap-4 sm:gap-0 pb-16 sm:pb-20" x-data="{ playing: false }">
            <div class="sm:w-[35%]">
                @if (! empty($video['name']))
                    <h2 class="font-display text-2xl sm:text-[30px] sm:leading-10 font-medium text-[#333333] tracking-[-0.01em] leading-tight">
                        Video: {{ $video['name'] }}
                    </h2>
                @endif
            </div>

            <div class="sm:w-[65%] sm:pl-12 max-w-2xl">
                @if (! empty($video['caption']))
                    <p class="text-lg leading-7 text-[#333333] mb-5">Video: {{ $video['caption'] }}</p>
                @endif

                <div class="relative w-full aspect-video rounded-card overflow-hidden bg-slate-900 cursor-pointer group"
                    @click="playing = true" x-show="! playing">
                    @if (! empty($video['poster']))
                        <img src="{{ $video['poster'] }}" alt="{{ $video['name'] }}" loading="lazy" decoding="async"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @endif
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white/45 backdrop-blur-xs flex items-center justify-center shadow-lg transition-transform duration-300 group-hover:scale-110">
                            <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white translate-x-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <template x-if="playing">
                    <div class="relative w-full aspect-video rounded-card overflow-hidden bg-black">
                        <iframe
                            src="https://player.vimeo.com/video/{{ $video['vimeoId'] }}?autoplay=1&badge=0&autopause=0&player_id=0&app_id=58479"
                            title="{{ $video['name'] }}" class="w-full h-full border-0"
                            allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                    </div>
                </template>
            </div>
        </div>
    @endif
</div>
