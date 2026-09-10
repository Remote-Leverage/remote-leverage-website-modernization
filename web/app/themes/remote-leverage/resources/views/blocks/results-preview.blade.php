<div class="w-full">
    {{-- Section Header & Filter Tabs --}}
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10 sm:mb-12">
        <div class="max-w-2xl">
            @if (! empty($sectionTitle))
                <h2 class="font-display text-3xl sm:text-4xl lg:text-[42px] font-bold text-black tracking-[-0.03em] leading-tight mb-3">
                    {!! $sectionTitle !!}
                </h2>
            @endif
            @if (! empty($sectionDesc))
                <p class="text-base sm:text-lg text-black/70 leading-relaxed">{{ $sectionDesc }}</p>
            @endif
        </div>

        {{-- Filter Pills --}}
        <div class="flex items-center gap-3 flex-shrink-0">
            <button type="button"
                class="bg-black text-white px-6 py-2.5 rounded-full text-sm font-bold tracking-wide transition-colors">
                Case Studies
            </button>
            <a href="/reviews/"
                class="bg-black/5 hover:bg-black/10 text-black px-6 py-2.5 rounded-full text-sm font-bold tracking-wide transition-colors">
                Testimonials
            </a>
        </div>
    </div>

    {{-- Case Study Preview Cards List --}}
    <div class="flex flex-col gap-8">
        @foreach ($cards as $card)
            <div class="bg-white rounded-3xl p-7 sm:p-10 lg:p-12 border border-black/6 shadow-[0_4px_30px_rgba(0,0,0,0.04)] flex flex-col gap-8">
                {{-- Top Header Row: Logo/Title & Category Tags --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-black/8">
                    <div class="flex items-center gap-3.5">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="{!! strip_tags($card['title']) !!}" loading="lazy"
                                decoding="async" class="h-8 sm:h-9 w-auto max-w-[160px] object-contain flex-shrink-0">
                        @endif
                        @if (! empty($card['title']))
                            <h3 class="font-display text-2xl sm:text-3xl font-bold text-black tracking-[-0.02em]">
                                {{ $card['title'] }}
                            </h3>
                        @endif
                    </div>

                    @if (! empty($card['tags']))
                        <div class="flex items-center gap-4 sm:gap-6 flex-wrap">
                            @foreach ($card['tags'] as $tag)
                                <div class="text-xs sm:text-sm font-bold text-black/70 border-b-2 border-black/20 pb-1">
                                    {{ $tag }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Middle Content: 2-Column Split (Story Narrative / Client Quote) --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">
                    {{-- Left Narrative --}}
                    <div class="lg:col-span-6">
                        @if (! empty($card['desc']))
                            <p class="text-base sm:text-lg text-black/75 leading-relaxed">
                                {{ $card['desc'] }}
                            </p>
                        @endif
                    </div>

                    {{-- Right Quote --}}
                    <div class="lg:col-span-6 flex gap-4 items-start">
                        <span class="text-[#250D4A] text-4xl sm:text-5xl font-serif font-black leading-none flex-shrink-0 select-none">
                            “
                        </span>
                        <div>
                            @if (! empty($card['quote_text']))
                                <blockquote class="text-base sm:text-lg font-medium text-black/90 leading-relaxed italic">
                                    {!! $card['quote_text'] !!}
                                </blockquote>
                            @endif
                            @if (! empty($card['quote_author']))
                                <div class="text-xs sm:text-sm font-bold text-black/60 mt-3 not-italic">
                                    {{ $card['quote_author'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Bottom Row: Metrics & Full Story CTA Button --}}
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6 pt-6 border-t border-black/8">
                    {{-- Left Metrics --}}
                    @if (! empty($card['stats']))
                        <div class="flex flex-wrap items-center gap-6 sm:gap-8 divide-x divide-black/15">
                            @foreach ($card['stats'] as $index => $stat)
                                <div class="{{ $index > 0 ? 'pl-6 sm:pl-8' : '' }} flex flex-col">
                                    <span class="font-display text-2xl sm:text-3xl font-bold text-black tracking-tight leading-tight">
                                        {{ $stat['value'] }}
                                    </span>
                                    <span class="text-xs sm:text-sm text-black/65 mt-0.5 leading-snug">
                                        {{ $stat['label'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div></div>
                    @endif

                    {{-- Right CTA Button --}}
                    @if (! empty($card['link_url']))
                        <a href="{{ $card['link_url'] }}"
                            class="inline-flex items-center justify-center bg-black hover:bg-neutral-800 text-white text-xs sm:text-sm font-bold uppercase tracking-[0.08em] px-8 sm:px-10 py-4 sm:py-4.5 rounded-lg transition-colors shadow-sm self-start sm:self-auto">
                            {{ $card['link_text'] ?: 'READ THE FULL STORY' }}
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
