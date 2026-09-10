<div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    @if (! empty($headline))
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-tight mb-10 text-center">
            {!! $headline !!}
        </h2>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card">
        @foreach ($cards as $card)
            <div class="bg-white rounded-card overflow-hidden border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col">
                @if (! empty($card['photo']))
                    <div class="w-full aspect-4/3 overflow-hidden bg-slate-100">
                        <img src="{{ $card['photo'] }}" alt="{{ $card['title'] }}" loading="lazy" decoding="async"
                            class="w-full h-full object-cover">
                    </div>
                @endif

                <div class="p-card flex flex-col grow">
                    <h3 class="font-display text-lg font-bold text-black tracking-[-0.02em] leading-snug">
                        {{ $card['title'] }}
                    </h3>

                    @if (! empty($card['price']))
                        <span
                            class="inline-block mt-2 mb-3 self-start text-xs font-bold uppercase tracking-wide text-brand-purple bg-violet-50 px-2.5 py-1 rounded-full">
                            {{ $card['price'] }}
                        </span>
                    @endif

                    @if (! empty($card['intro']))
                        <p class="text-sm text-black/80 leading-relaxed mb-2">{{ $card['intro'] }}</p>
                    @endif

                    @if (! empty($card['tasks']))
                        <ul class="text-sm text-black/70 leading-relaxed space-y-1 mb-4 list-disc pl-5">
                            @foreach ($card['tasks'] as $task)
                                <li>{{ $task }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($card['tools']))
                        <div class="mt-auto pt-3 border-t border-black/5">
                            <span class="block text-[11px] font-bold uppercase tracking-wide text-black/40 mb-2">Tools</span>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($card['tools'] as $tool)
                                    <img src="{{ $tool }}" alt="" loading="lazy" decoding="async" class="h-5 w-auto object-contain">
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($card['cta_text']))
                        <a href="{{ $card['cta_url'] ?? '#booking-footer' }}"
                            class="mt-4 inline-flex items-center justify-center gap-1.5 text-sm font-bold text-brand-purple hover:text-[#7b20d4] transition-colors">
                            {{ $card['cta_text'] }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
