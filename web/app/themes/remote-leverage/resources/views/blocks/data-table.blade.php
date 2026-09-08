<div class="flex flex-col gap-3 mb-20 sm:mb-24 w-full">
    {{-- Header Row --}}
    <div
        class="bg-white rounded-card px-6 sm:px-8 py-4 border border-black/4 shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
        <div class="grid grid-cols-1 sm:grid-cols-[38%_31%_31%] items-center">
            <div class="hidden sm:block"></div>
            <div class="font-display text-base sm:text-[17px] font-bold text-black">{{ $col1Header }}</div>
            <div class="font-display text-base sm:text-[17px] font-bold text-black">{{ $col2Header }}</div>
        </div>
    </div>

    {{-- Data Rows --}}
    @foreach ($rows as $row)
        <div
            class="bg-white rounded-card px-6 sm:px-8 py-4 border border-black/4 shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
            <div class="grid grid-cols-1 sm:grid-cols-[38%_31%_31%] items-center gap-2 sm:gap-0">
                <div class="font-display text-[15px] sm:text-base font-bold text-black">
                    {{ $row['feature'] }}
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="w-5 h-5 rounded-full bg-[#E04B3B] shrink-0 flex items-center justify-center">
                        <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="3.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </div>
                    <span class="text-[14px] sm:text-[15px] text-black">{{ $row['diy'] }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div
                        class="w-5 h-5 rounded-full bg-[#65B72E] shrink-0 flex items-center justify-center">
                        <svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                            stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </div>
                    <span class="text-[14px] sm:text-[15px] text-black">{{ $row['rl'] }}</span>
                </div>
            </div>
        </div>
    @endforeach
</div>
