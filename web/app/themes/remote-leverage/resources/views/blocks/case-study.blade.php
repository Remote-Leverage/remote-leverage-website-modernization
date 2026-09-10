@php
    $proseClasses = '[&_p]:mb-4 [&_p:last-child]:mb-0 [&_p]:text-black/80 [&_p]:leading-relaxed '
        .'[&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul]:space-y-2 [&_ul]:text-black/80 '
        .'[&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol]:space-y-2 [&_ol]:text-black/80 '
        .'[&_li]:leading-relaxed '
        .'[&_a]:text-brand-purple [&_a]:underline [&_a]:decoration-brand-purple/40 hover:[&_a]:decoration-brand-purple '
        .'[&_strong]:font-bold [&_strong]:text-black [&_b]:font-bold [&_b]:text-black '
        .'[&_h2]:font-display [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-black [&_h2]:mb-3 [&_h2]:mt-2 '
        .'[&_h3]:font-display [&_h3]:text-lg [&_h3]:font-bold [&_h3]:text-black [&_h3]:mb-2 [&_h3]:mt-2';
@endphp

<div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">

    {{-- Eyebrow --}}
    <div class="mb-6 sm:mb-8">
        <span class="inline-flex items-center px-3 py-1 rounded-full bg-brand-purple/10 text-brand-purple text-xs font-bold tracking-wide uppercase">
            Case Study
        </span>
    </div>

    {{-- Hero: client name / logo + headline + company info --}}
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-10 lg:gap-14 items-start mb-14 sm:mb-16">
        <div>
            @if (! empty($logo))
                <div class="mb-6 max-w-[220px]">
                    @if (! empty($websiteUrl))
                        <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">
                            <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-auto object-contain" loading="lazy" decoding="async">
                        </a>
                    @else
                        <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-auto object-contain" loading="lazy" decoding="async">
                    @endif
                </div>
            @else
                <p class="font-display text-sm font-bold text-brand-purple uppercase tracking-wide mb-4">{{ $clientName }}</p>
            @endif

            <h1 class="font-display text-3xl sm:text-4xl lg:text-[42px] font-bold text-black tracking-[-0.03em] leading-tight mb-4">
                {!! $headline !!}
            </h1>

            @if (! empty($subheadline))
                <p class="text-base sm:text-lg text-black/70 leading-relaxed mb-2">
                    {{ $subheadline }}
                </p>
            @endif

            @if (! empty($infoItems))
                <div class="flex flex-wrap gap-x-6 gap-y-2 mt-6">
                    @foreach ($infoItems as $item)
                        @if (! empty($item['value']))
                            <div class="text-sm">
                                <span class="text-black/50">{{ $item['label'] }}:</span>
                                <span class="text-black font-medium ml-1">{{ $item['value'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        @if (! empty($stats))
            <div class="grid grid-cols-3 lg:grid-cols-1 gap-3">
                @foreach ($stats as $stat)
                    <div class="bg-white rounded-card p-4 sm:p-5 border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] text-center lg:text-left">
                        <div class="font-display text-2xl sm:text-3xl font-bold text-brand-purple tracking-[-0.02em] leading-none mb-1.5">
                            {{ $stat['value'] }}
                        </div>
                        <div class="text-xs sm:text-sm text-black/60 leading-snug">
                            {{ $stat['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pull quote --}}
    @if (! empty($quoteText))
        <div class="bg-bg-light rounded-card p-8 sm:p-10 mb-14 sm:mb-16 flex flex-col sm:flex-row gap-6 sm:gap-8 items-start sm:items-center">
            <svg class="w-9 h-9 sm:w-10 sm:h-10 text-brand-purple/25 shrink-0" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true">
                <path d="M10 8c-3.3 0-6 2.7-6 6v10h10V14H8c0-1.1.9-2 2-2V8zm14 0c-3.3 0-6 2.7-6 6v10h10V14h-6c0-1.1.9-2 2-2V8z"/>
            </svg>
            <div class="flex-1">
                <p class="font-display text-lg sm:text-xl font-bold text-black tracking-[-0.01em] leading-snug mb-4">
                    &ldquo;{{ $quoteText }}&rdquo;
                </p>
                <div class="flex items-center gap-3">
                    @if (! empty($quotePhoto))
                        <img src="{{ $quotePhoto }}" alt="{{ $quoteName }}" width="44" height="44"
                            class="w-11 h-11 rounded-full object-cover shrink-0" loading="lazy" decoding="async">
                    @endif
                    <div class="text-sm">
                        @if (! empty($quoteName))
                            <span class="text-black font-bold">{{ $quoteName }}</span>
                        @endif
                        @if (! empty($quoteCompany))
                            <span class="text-black/60">{{ ! empty($quoteName) ? ', ' : '' }}{{ $quoteCompany }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Narrative sections --}}
    <div class="flex flex-col gap-12 sm:gap-14">
        @foreach ($sections as $section)
            <div>
                @if (! empty($section['heading']))
                    <h2 class="font-display text-2xl sm:text-[26px] font-bold text-black tracking-[-0.02em] leading-tight mb-5">
                        {{ $section['heading'] }}
                    </h2>
                @endif

                @if ($section['type'] === 'stats' && ! empty($section['rows']))
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($section['rows'] as $row)
                            <div class="bg-white rounded-card p-4 sm:p-5 border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] text-center">
                                <div class="font-display text-xl sm:text-2xl font-bold text-brand-purple tracking-[-0.02em] leading-none mb-1.5">
                                    {{ $row['value'] }}
                                </div>
                                <div class="text-xs sm:text-sm text-black/60 leading-snug">
                                    {{ $row['label'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif ($section['type'] === 'table' && ! empty($section['rows']))
                    <div class="flex flex-col gap-2 w-full">
                        <div class="grid grid-cols-2 bg-black/[0.03] rounded-card px-5 sm:px-6 py-3">
                            <span class="text-xs sm:text-sm font-bold text-black uppercase tracking-wide">Success Metric</span>
                            <span class="text-xs sm:text-sm font-bold text-black uppercase tracking-wide">Outcome</span>
                        </div>
                        @foreach ($section['rows'] as $row)
                            <div class="grid grid-cols-2 border-b border-black/6 px-5 sm:px-6 py-3">
                                <span class="text-sm text-black/70">{{ $row['value'] }}</span>
                                <span class="text-sm text-black font-medium">{{ $row['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @elseif (! empty($section['body']))
                    <div class="{{ $proseClasses }}">
                        {!! $section['body'] !!}
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Client video --}}
    @if (! empty($video))
        <div class="mt-14 sm:mt-16" x-data="{ playing: false }">
            @if (! empty($video['name']))
                <h2 class="font-display text-2xl sm:text-[26px] font-bold text-black tracking-[-0.02em] leading-tight mb-2">
                    {{ $video['name'] }}
                </h2>
            @endif
            @if (! empty($video['caption']))
                <p class="text-sm sm:text-base text-black/60 mb-5">{{ $video['caption'] }}</p>
            @endif

            <div class="relative w-full max-w-2xl aspect-video rounded-card overflow-hidden bg-slate-900 cursor-pointer group"
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
                <div class="relative w-full max-w-2xl aspect-video rounded-card overflow-hidden bg-black">
                    <iframe
                        src="https://player.vimeo.com/video/{{ $video['vimeoId'] }}?autoplay=1&badge=0&autopause=0&player_id=0&app_id=58479"
                        title="{{ $video['name'] }}" class="w-full h-full border-0"
                        allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>
            </template>
        </div>
    @endif
</div>
