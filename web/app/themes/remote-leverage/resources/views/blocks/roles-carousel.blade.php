{{-- Production's roles/industries band: dark surface, white heading, and a
     horizontally scrolling track of brand-purple cards that bleeds off the right
     edge. Scrolling is native scroll-snap; the arrows are progressive enhancement. --}}
<section class="w-full bg-roles-surface py-14 lg:py-20 overflow-hidden" data-rl-carousel>
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="flex flex-wrap items-end justify-between gap-6 mb-10">
                @if ($headline)
                    <h2 class="font-display font-bold text-bg-light text-3xl sm:text-4xl lg:text-section">
                        {!! $headline !!}
                    </h2>
                @endif

                <div class="flex gap-3">
                    <button type="button" data-rl-carousel-prev
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border border-white/30 bg-white/10 text-white transition-colors hover:bg-white/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="{{ __('Previous roles', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button type="button" data-rl-carousel-next
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border border-white/30 bg-white/10 text-white transition-colors hover:bg-white/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                            aria-label="{{ __('More roles', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Track starts at the container's left edge but runs to the viewport edge so
         the next card is visibly cut off, the way production's slider reads. --}}
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-[1380px] lg:max-w-none lg:ml-[max(2rem,calc((100vw-1380px)/2))]">
            <div data-rl-carousel-track
                 class="flex gap-2.5 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 cursor-grab select-none touch-pan-y [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($cards as $card)
                    <div data-rl-carousel-card
                         class="snap-start shrink-0 w-[280px] sm:w-[322px] flex flex-col gap-5 rounded-badge bg-brand-purple p-[30px]">
                        @if (! empty($card['icon']))
                            <img src="{{ $card['icon'] }}" alt="" loading="lazy" decoding="async"
                                 class="h-20 w-20 object-contain">
                        @endif

                        <h3 class="font-display font-bold text-white text-lead">{!! $card['title'] !!}</h3>

                        @if (! empty($card['text']))
                            <p class="text-card text-white">{{ $card['text'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
