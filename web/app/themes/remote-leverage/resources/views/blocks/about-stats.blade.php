<div class="w-full">
    @if (! empty($sectionTitle))
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-tight mb-10 text-center">
            {!! $sectionTitle !!}
        </h2>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-card">
        @foreach ($columns as $col)
            <div class="bg-white rounded-card p-card border border-black/5 shadow-[0_4px_24px_rgba(0,0,0,0.03)] flex flex-col">
                @if (! empty($col['icon']))
                    <img src="{{ $col['icon'] }}" alt="" width="40" height="40" loading="lazy" decoding="async"
                        class="w-10 h-10 mb-4 object-contain">
                @endif

                @if (! empty($col['headline_value']))
                    <div class="font-display text-4xl font-bold text-brand-purple tracking-[-0.02em] mb-1">
                        {{ $col['headline_value'] }}
                    </div>
                @endif
                @if (! empty($col['headline_label']))
                    <p class="text-sm text-black/70 leading-snug mb-6">{{ $col['headline_label'] }}</p>
                @endif

                @if (! empty($col['category_label']))
                    <div class="mt-auto pt-4 border-t border-black/8">
                        <span class="text-xs font-bold tracking-[0.08em] text-black/50 uppercase">
                            {{ $col['category_label'] }}
                        </span>

                        <div class="mt-3 flex flex-col gap-3">
                            @foreach ($col['stats'] as $stat)
                                <div class="flex items-baseline justify-between gap-3">
                                    <span class="font-display text-lg font-bold text-black">{{ $stat['value'] }}</span>
                                    <span class="text-xs text-black/60 text-right">{{ $stat['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
