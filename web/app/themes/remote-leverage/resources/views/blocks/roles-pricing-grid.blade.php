{{-- Roles with pricing: per-role cards listing typical tasks, tools and an hourly rate band. --}}
<div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
    @if (! empty($headline))
        <h2 class="font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] leading-tight mb-10 text-center">
            {!! $headline !!}
        </h2>
    @endif

@php
    // `split-chip` is production's "Scale your brand smarter, faster" shape: a 2-up of wide
    // cards, each split into a gradient salary chip on the left and the role copy on the right.
    // The default `stacked` variant is the photo-on-top card the other pages use.
    $isSplitChip = ($variant ?? 'stacked') === 'split-chip';
    $cols = ($columns ?? '4') === '2' ? 'lg:grid-cols-2' : 'lg:grid-cols-4';
@endphp

@if ($isSplitChip)
    <div class="grid grid-cols-1 {{ $cols }} gap-2.5">
        @foreach ($cards as $card)
            <div class="flex flex-col gap-5 rounded-[10px] border border-[#92B4F4]/30 bg-white p-5 sm:flex-row sm:items-start">
                {{-- Salary chip --}}
                <div class="w-full shrink-0 rounded-[10px] p-5 sm:w-[307px]"
                     style="background-image:linear-gradient(180deg,#F4F6FC 0%,#92B4F4 100%);">
                    <div class="flex items-center gap-3">
                        @if (! empty($card['photo']))
                            <img src="{{ $card['photo'] }}" alt="{{ $card['chip_name'] ?? '' }}"
                                 width="45" height="45" loading="lazy" decoding="async"
                                 class="h-[45px] w-[45px] rounded-full object-cover">
                        @endif
                        <div>
                            <div class="font-display text-[15px] font-bold text-[#1D4ED8]">{{ $card['chip_name'] ?? '' }}</div>
                            <div class="text-xs text-black/70">{{ $card['chip_role'] ?? '' }}</div>
                        </div>
                    </div>

                    <hr class="my-4 border-t border-white/70">

                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <div class="text-xs text-black/70">{{ $card['price_label'] ?? 'Montly' }}</div>
                            <div class="font-display text-xl font-bold text-black">{{ $card['price'] ?? '' }}</div>
                        </div>
                        @if (! empty($card['cta_text']))
                            <a href="{{ $card['cta_url'] ?: '#booking-footer' }}"
                               class="rounded-pill bg-[#8A2BE2] px-5 py-2 text-xs font-bold text-white transition hover:bg-[#7b20d4]">
                                {{ $card['cta_text'] }}
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Role copy --}}
                <div class="min-w-0 flex-1">
                    <h3 class="font-display text-xl sm:text-2xl font-bold text-black tracking-[-0.02em] leading-snug mb-2">
                        {{ $card['title'] }}
                    </h3>
                    @if (! empty($card['intro']))
                        <p class="text-[15px] leading-relaxed text-black">{{ $card['intro'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 {{ $cols }} gap-card">
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
@endif
</div>
