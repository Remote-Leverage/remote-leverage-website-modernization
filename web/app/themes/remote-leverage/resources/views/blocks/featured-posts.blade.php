{{-- Production's "Featured Content" band on /ecommerce-virtual-assistant/: a pure black
     surface with a white heading and a "Latest Posts" subhead, over a scrolling track of
     427px slides. Each card is transparent on the black ground with no radius and no
     chrome — just a link wrapping the post image and its title. Cards are either
     hand-picked in the repeater or pulled live from a category by the block. --}}
@php
    $isDark = ($tone ?? 'dark') !== 'light';
    $isCarousel = ($layout ?? 'carousel') !== 'grid';
@endphp

<section class="w-full {{ $isDark ? 'bg-surface-black' : 'bg-light' }} py-14 lg:py-20 overflow-hidden" @if ($isCarousel) data-rl-carousel @endif>
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-6 mb-10">
            <div>
                @if (! empty($headline))
                    <h2 class="font-display font-bold text-3xl sm:text-4xl lg:text-section {{ $isDark ? 'text-white' : 'text-black' }}">
                        {!! $headline !!}
                    </h2>
                @endif

                @if (! empty($subheadline))
                    <p class="mt-3 text-[15px] leading-[24px] {{ $isDark ? 'text-white/70' : 'text-black/70' }}">
                        {{ $subheadline }}
                    </p>
                @endif
            </div>

            @if ($isCarousel)
                <div class="flex gap-3">
                    <button type="button" data-rl-carousel-prev
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border {{ $isDark ? 'border-white/30 bg-white/10 text-white hover:bg-white/20' : 'border-black/15 bg-white text-black hover:bg-black/5' }} transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple"
                            aria-label="{{ __('Previous posts', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button type="button" data-rl-carousel-next
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border {{ $isDark ? 'border-white/30 bg-white/10 text-white hover:bg-white/20' : 'border-black/15 bg-white text-black hover:bg-black/5' }} transition-colors disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple"
                            aria-label="{{ __('More posts', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        <div @if ($isCarousel) data-rl-carousel-track @endif
             class="{{ $isCarousel
                 ? 'flex gap-2.5 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 cursor-grab select-none touch-pan-y [scrollbar-width:none] [&::-webkit-scrollbar]:hidden'
                 : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5' }}">
            @foreach ($cards as $card)
                <div @if ($isCarousel) data-rl-carousel-card @endif
                     class="{{ $isCarousel ? 'snap-start shrink-0 w-[calc(100vw-3rem)] max-w-[427px] sm:w-[427px]' : 'w-full' }} rounded-none bg-transparent">
                    <a href="{{ $card['url'] ?: '#' }}"
                       class="group flex h-full flex-col gap-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="{{ $card['title'] }}" width="427" height="240"
                                 loading="lazy" decoding="async"
                                 class="w-full h-auto object-cover">
                        @endif

                        <h3 class="font-display text-lead font-bold {{ $isDark ? 'text-white' : 'text-black' }} group-hover:underline">
                            {{ $card['title'] }}
                        </h3>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
