{{-- Feature-by-feature comparison rows.

     Two layouts, chosen by `variant`:

     · 'diy' (default) — production's arrangement on the comparison pages: a label column, then
       DIY with a red ✗, then Remote Leverage with a green ✓. Unchanged.

     · 'leverage-first' — the 2026 homepage's "Remote Leverage vs Other Agencies". Remote
       Leverage comes second in the row and wins the green tick; the competitor moves to the
       right with a slate ✗ rather than a red one. Measured off Homepage V1.png at 1366px:
       tick #0EBC67, cross #878EA0 (--color-step-numeral), columns roughly 34/34/32.

     The mobile comp is a different composition, not this table reflowed: it drops the row
     labels entirely, puts the two column names in pill headers over a #DDE6FF band, and
     shortens every value. Those short values come from `rows[].diy_short` / `rows[].rl_short`
     when supplied, so one row set feeds both breakpoints rather than two tables fighting over
     one slot in the page. --}}
@php
    $leverageFirst = ($variant ?? 'diy') === 'leverage-first';
    $rows = is_array($rows ?? null) ? $rows : [];

    $tick = '<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    $cross = '<svg class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
@endphp

@if ($leverageFirst)
    {{-- ── Desktop: label, Remote Leverage, competitor ─────────────────────────────────── --}}
    <div class="hidden w-full flex-col gap-3 sm:flex">
        <div class="rounded-card border border-black/4 bg-white px-6 py-4 shadow-[0_2px_8px_rgba(0,0,0,0.02)] sm:px-8">
            <div class="grid grid-cols-[34%_34%_32%] items-center">
                <div class="font-display text-base font-bold text-black sm:text-[17px]">{{ $col0Header ?? '' }}</div>
                <div class="font-display text-base font-bold text-black sm:text-[17px]">{{ $col2Header }}</div>
                <div class="font-display text-base font-bold text-black sm:text-[17px]">{{ $col1Header }}</div>
            </div>
        </div>

        @foreach ($rows as $row)
            <div class="rounded-card border border-black/4 bg-white px-6 py-4 shadow-[0_2px_8px_rgba(0,0,0,0.02)] sm:px-8">
                <div class="grid grid-cols-[34%_34%_32%] items-center">
                    <div class="font-display text-[15px] font-bold text-black sm:text-base">{{ $row['feature'] }}</div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#0EBC67]">{!! $tick !!}</span>
                        <span class="text-[14px] text-black sm:text-[15px]">{{ $row['rl'] }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-step-numeral">{!! $cross !!}</span>
                        <span class="text-[14px] text-black sm:text-[15px]">{{ $row['diy'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Mobile: pill headers, no row labels, short values ────────────────────────────── --}}
    <div class="-mx-4 flex flex-col gap-3 bg-[#DDE6FF] px-4 py-10 sm:hidden">
        <div class="mb-1 grid grid-cols-2 gap-3">
            <span class="flex items-center justify-center rounded-xl bg-brand-purple px-3 py-3 text-center font-display text-[15px] font-bold leading-tight text-white">{{ $col2Header }}</span>
            <span class="flex items-center justify-center rounded-xl bg-[#7C818F] px-3 py-3 text-center font-display text-[15px] font-bold leading-tight text-white">{{ $col1Header }}</span>
        </div>

        @foreach ($rows as $row)
            <div class="grid grid-cols-2 items-center gap-0 rounded-2xl bg-[#F4F6FC] px-3 py-4">
                <div class="flex items-center gap-2 pr-3">
                    <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-[#0EBC67]">{!! $tick !!}</span>
                    <span class="font-display text-[14px] font-bold leading-tight text-black">{{ $row['rl_short'] ?? $row['rl'] }}</span>
                </div>
                <div class="flex items-center gap-2 border-l border-black/10 pl-3">
                    <span class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-step-numeral">{!! $cross !!}</span>
                    <span class="font-display text-[14px] font-bold leading-tight text-black">{{ $row['diy_short'] ?? $row['diy'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="mb-20 flex w-full flex-col gap-3 sm:mb-24">
        {{-- Header Row --}}
        <div
            class="bg-white rounded-card px-6 sm:px-8 py-4 border border-black/4 shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
            <div class="grid grid-cols-1 sm:grid-cols-[38%_31%_31%] items-center">
                <div class="hidden sm:block font-display text-base sm:text-[17px] font-bold text-black">{{ $col0Header ?? '' }}</div>
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
                            {!! $cross !!}
                        </div>
                        <span class="text-[14px] sm:text-[15px] text-black">{{ $row['diy'] }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-5 h-5 rounded-full bg-[#65B72E] shrink-0 flex items-center justify-center">
                            {!! $tick !!}
                        </div>
                        <span class="text-[14px] sm:text-[15px] text-black">{{ $row['rl'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
