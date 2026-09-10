<div class="w-full">
    @if (! empty($sectionTitle))
        <h2 class="font-display text-3xl sm:text-4xl lg:text-[42px] font-bold text-black tracking-[-0.03em] leading-tight mb-10 sm:mb-12 text-center">
            {!! $sectionTitle !!}
        </h2>
    @endif

    {{-- Top Row: 3 Dark Midnight Highlight Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
        @foreach ($columns as $index => $col)
            <div class="bg-[#250D4A] rounded-2xl p-7 sm:p-8 text-white relative flex flex-col justify-between min-h-[170px] shadow-[0_4px_24px_rgba(37,13,74,0.15)] overflow-hidden">
                {{-- Top Row: Big Number & Icon --}}
                <div class="flex items-start justify-between gap-4">
                    @if (! empty($col['headline_value']))
                        <div class="font-display text-4xl sm:text-[46px] font-bold text-white tracking-[-0.03em] leading-none">
                            {{ $col['headline_value'] }}
                        </div>
                    @endif

                    <div class="text-white/60 flex-shrink-0">
                        @if (! empty($col['icon']))
                            <img src="{{ $col['icon'] }}" alt="" width="36" height="36" loading="lazy" decoding="async" class="w-9 h-9 object-contain brightness-0 invert opacity-70">
                        @elseif ($index === 0)
                            {{-- Trending Bar Chart Icon --}}
                            <svg class="w-8 h-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 8" />
                            </svg>
                        @elseif ($index === 1)
                            {{-- Stopwatch with checkmark icon --}}
                            <svg class="w-8 h-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 1v2m6-2v2" />
                            </svg>
                        @else
                            {{-- Bullseye Target Icon --}}
                            <svg class="w-8 h-8 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                                <circle cx="12" cy="12" r="9" />
                                <circle cx="12" cy="12" r="5" />
                                <circle cx="12" cy="12" r="1.5" />
                            </svg>
                        @endif
                    </div>
                </div>

                {{-- Bottom Row: Label --}}
                @if (! empty($col['headline_label']))
                    <p class="text-sm sm:text-base text-white/85 font-normal leading-snug mt-4">
                        {{ $col['headline_label'] }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Bottom Row: 3 Category Detail Stat Columns --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 sm:gap-10">
        @foreach ($columns as $col)
            <div class="flex flex-col">
                {{-- Category Title --}}
                @if (! empty($col['category_label']))
                    <div class="pb-3 border-b border-black/20 mb-1">
                        <span class="text-xs sm:text-sm font-bold tracking-[0.1em] text-black/50 uppercase">
                            {{ $col['category_label'] }}
                        </span>
                    </div>
                @endif

                {{-- Sub-Stats List --}}
                <div class="flex flex-col divide-y divide-black/15">
                    @foreach ($col['stats'] as $stat)
                        <div class="py-4 flex flex-col">
                            <span class="font-display text-2xl sm:text-[26px] font-bold text-black tracking-[-0.02em] leading-tight">
                                {{ $stat['value'] }}
                            </span>
                            <span class="text-sm text-black/70 mt-1 leading-snug">
                                {{ $stat['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
