@php
    $proseClasses = '[&_p]:mb-4 [&_p:last-child]:mb-0 [&_p]:text-black/70 [&_p]:leading-relaxed [&_p]:text-lg '
        .'[&_ul]:list-disc [&_ul]:pl-5 [&_ul]:mb-4 [&_ul]:space-y-2 [&_ul]:text-black/70 [&_ul]:text-lg '
        .'[&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:mb-4 [&_ol]:space-y-2 [&_ol]:text-black/70 [&_ol]:text-lg '
        .'[&_li]:leading-relaxed '
        .'[&_a]:text-brand-purple [&_a]:underline [&_a]:decoration-brand-purple/40 hover:[&_a]:decoration-brand-purple '
        .'[&_strong]:font-bold [&_strong]:text-black [&_b]:font-bold [&_b]:text-black';
@endphp

{{-- Hero: dark band matching production's case-study template family --}}
<div class="w-full bg-[#270028] pt-14 pb-20 sm:pb-24">
    <div class="max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8">
        <span class="inline-flex items-center text-xs font-bold tracking-[0.1em] text-white uppercase mb-6">
            Case Study
        </span>

        @if (! empty($clientName))
            <h1 class="font-display text-4xl sm:text-5xl lg:text-[60px] font-bold text-white tracking-[-0.02em] leading-tight mb-8 sm:mb-10">
                {{ $clientName }}
            </h1>
        @endif

        <div class="flex flex-col sm:flex-row gap-8 sm:gap-0">
            <div class="sm:w-[35%]">
                @if (! empty($logo))
                    <div class="max-w-[220px] rounded-2xl overflow-hidden bg-white p-6">
                        @if (! empty($websiteUrl))
                            <a href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-auto object-contain" loading="lazy" decoding="async">
                            </a>
                        @else
                            <img src="{{ $logo }}" alt="{{ $clientName }}" class="w-full h-auto object-contain" loading="lazy" decoding="async">
                        @endif
                    </div>
                @endif
            </div>

            <div class="sm:w-[65%] sm:pl-12">
                <h2 class="font-display text-2xl sm:text-3xl lg:text-[42px] font-medium text-[#FFFBFF] tracking-[0.01em] leading-tight mb-5">
                    {!! $headline !!}
                </h2>

                @if (! empty($subheadline))
                    <p class="text-base sm:text-lg text-white/70 leading-relaxed mb-5">
                        {{ $subheadline }}
                    </p>
                @endif

                @if (! empty($infoItems))
                    <div class="flex flex-col gap-1 mb-10">
                        @foreach ($infoItems as $item)
                            @if (! empty($item['value']))
                                <div class="flex items-baseline gap-4 text-lg">
                                    <span class="font-medium text-white w-40 shrink-0">{{ $item['label'] }}:</span>
                                    <span class="font-light text-white">{{ $item['value'] }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if (! empty($stats))
                    <div class="flex flex-wrap gap-x-14 gap-y-6">
                        @foreach ($stats as $stat)
                            <div>
                                <div class="font-display text-3xl sm:text-4xl lg:text-[46px] font-medium text-white leading-none mb-1">
                                    {{ $stat['value'] }}
                                </div>
                                <div class="text-lg sm:text-xl text-white/85 leading-snug">
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

<div class="w-full max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Pull quote --}}
    @if (! empty($quoteText))
        <div class="max-w-3xl py-16 sm:py-20">
            <p class="font-display text-2xl sm:text-3xl lg:text-[40px] font-medium text-black/80 tracking-[-0.01em] leading-tight mb-6">
                &ldquo;{{ $quoteText }}&rdquo;
            </p>
            <div class="flex items-center gap-4">
                @if (! empty($quotePhoto))
                    <img src="{{ $quotePhoto }}" alt="{{ $quoteName }}" width="64" height="64"
                        class="w-16 h-16 rounded-full object-cover shrink-0" loading="lazy" decoding="async">
                @endif
                <div class="text-lg leading-snug">
                    @if (! empty($quoteName))
                        <div class="text-black font-medium">{{ $quoteName }}</div>
                    @endif
                    @if (! empty($quoteCompany))
                        <div class="text-black font-medium">{{ $quoteCompany }}</div>
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
                        <h2 class="font-display text-2xl sm:text-[30px] font-medium text-black tracking-[-0.01em] leading-tight">
                            {{ $section['heading'] }}
                        </h2>
                    @endif
                </div>

                <div class="sm:w-[65%] sm:pl-12">
                    @if ($section['type'] === 'stats' && ! empty($section['rows']))
                        <div class="grid grid-cols-2 gap-x-10 gap-y-8 max-w-lg">
                            @foreach ($section['rows'] as $row)
                                <div>
                                    <div class="font-display text-3xl sm:text-4xl lg:text-[46px] font-medium text-black leading-none mb-1">
                                        {{ $row['value'] }}
                                    </div>
                                    <div class="text-lg sm:text-xl text-black/70 leading-snug">
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
            </div>
        @endforeach
    </div>

    {{-- Client video --}}
    @if (! empty($video))
        <div class="flex flex-col sm:flex-row gap-4 sm:gap-0 pb-16 sm:pb-20" x-data="{ playing: false }">
            <div class="sm:w-[35%]">
                @if (! empty($video['name']))
                    <h2 class="font-display text-2xl sm:text-[30px] font-medium text-black tracking-[-0.01em] leading-tight">
                        Video: {{ $video['name'] }}
                    </h2>
                @endif
            </div>

            <div class="sm:w-[65%] sm:pl-12 max-w-2xl">
                @if (! empty($video['caption']))
                    <p class="text-lg text-black/70 leading-relaxed mb-5">Video: {{ $video['caption'] }}</p>
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
