{{-- Copy left, centre-mode talent carousel right. The card scale/overlap is ported
     from the legacy rl-elementor-blocks guarantee carousel (see .rl-profile-card in
     app.css); the behaviour is the shared vanilla carousel in app.js. --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="grid grid-cols-1 lg:grid-cols-[420px_1fr] gap-10 lg:gap-14 lg:items-center">

                <div class="flex flex-col">
                    @if ($headline)
                        <h2 class="font-display font-semibold text-black text-3xl sm:text-4xl lg:text-[48px] lg:leading-[0.95] tracking-[-1.44px] mb-6 lg:mb-[30px]">
                            {!! $headline !!}
                        </h2>
                    @endif

                    @if ($body)
                        <div class="text-card text-black [&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-bold">
                            {!! $body !!}
                        </div>
                    @endif

                    @if ($ctaText)
                        <div class="mt-8">
                            <a href="{{ $ctaUrl }}"
                               class="group inline-flex items-center gap-3 rounded-pill bg-black hover:bg-surface-black-hover px-8 py-4 font-bold uppercase text-white text-sm tracking-wide transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2">
                                <span>{{ $ctaText }}</span>
                                @include('partials.icon-circle-arrow', ['class' => 'w-5 h-5 shrink-0 transition-transform group-hover:translate-x-0.5'])
                            </a>
                        </div>
                    @endif
                </div>

                <div class="min-w-0" data-rl-carousel data-rl-carousel-center>
                    <div class="mx-auto max-w-[790px]">
                        <div data-rl-carousel-track
                             class="rl-talent-carousel-track flex overflow-x-auto snap-x snap-mandatory scroll-smooth cursor-grab select-none touch-pan-y [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            @foreach ($profiles as $profile)
                                <div data-rl-carousel-card class="rl-profile-card shrink-0 snap-center">
                                    <div class="rl-profile-card-inner">
                                        <div class="rl-profile-card-image">
                                            @if (! empty($profile['image']))
                                                <img src="{{ $profile['image'] }}" alt="{{ $profile['name'] }}"
                                                     loading="lazy" decoding="async">
                                            @endif
                                        </div>

                                        <div class="px-2 py-4">
                                            <h3 class="font-display text-[22px] font-extrabold text-black mb-1">{{ $profile['name'] }}</h3>

                                            @if (! empty($profile['role']))
                                                <p class="text-[13px] text-[#888] mb-4">{{ $profile['role'] }}</p>
                                            @endif

                                            @if (! empty($profile['rate']))
                                                <span class="inline-block rounded-pill bg-[#909EBF] px-5 py-2 text-[13px] font-bold text-white">{{ $profile['rate'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if (count($profiles) > 1)
                            <div class="mt-4 flex justify-center gap-2">
                                @foreach ($profiles as $i => $profile)
                                    <button type="button" data-rl-carousel-dot aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                            class="h-2 w-2 rounded-circle border-[1.5px] border-[#333] bg-transparent opacity-35 transition-all aria-[current=true]:bg-black aria-[current=true]:opacity-100"
                                            aria-label="{{ sprintf(__('Show %s', 'remote-leverage'), $profile['name']) }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>
