<div class="w-full">
    <div class="text-center max-w-2xl mx-auto mb-10">
        @if (! empty($sectionTitle))
            <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-tight mb-3">
                {!! $sectionTitle !!}
            </h2>
        @endif
        @if (! empty($sectionDesc))
            <p class="text-black/70 leading-relaxed">{{ $sectionDesc }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-card">
        @foreach ($cards as $card)
            <div class="rounded-card bg-white border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] p-card flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    @if (! empty($card['image']))
                        <img src="{{ $card['image'] }}" alt="{!! strip_tags($card['title']) !!}" loading="lazy"
                            decoding="async" class="h-8 w-auto max-w-32 object-contain">
                    @endif
                    @if (! empty($card['title']))
                        <h3 class="font-display text-2xl font-bold text-black tracking-[-0.02em]">
                            {{ $card['title'] }}
                        </h3>
                    @endif
                </div>

                @if (! empty($card['tags']))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($card['tags'] as $tag)
                            <span
                                class="text-xs font-bold text-black/70 bg-[#f7f8fc] border border-black/8 rounded-full px-3 py-1">
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if (! empty($card['desc']))
                    <p class="text-sm text-black/70 leading-relaxed">{{ $card['desc'] }}</p>
                @endif

                @if (! empty($card['stats']))
                    <div class="flex flex-wrap gap-6 pt-3 border-t border-black/8 mt-1">
                        @foreach ($card['stats'] as $stat)
                            <div>
                                <div class="font-display text-xl font-bold text-black">{{ $stat['value'] }}</div>
                                <div class="text-xs text-black/60">{{ $stat['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (! empty($card['link_url']))
                    <a href="{{ $card['link_url'] }}"
                        class="inline-flex items-center gap-2 text-sm font-bold text-brand-purple mt-1 underline underline-offset-4 decoration-brand-purple/40 hover:decoration-brand-purple">
                        {{ $card['link_text'] ?: 'Read the Full Story' }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>
</div>
