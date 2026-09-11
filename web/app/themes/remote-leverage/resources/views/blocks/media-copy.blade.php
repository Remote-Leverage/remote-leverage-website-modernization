{{-- Image one side, heading + rich copy + CTA the other. Type from the shared
     `section` / `card` tokens; the CTA matches production's black pill. --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 lg:items-center">

                @if ($image)
                    <div @class(['flex justify-center', 'lg:order-2' => $imagePosition === 'right'])>
                        <img src="{{ $image }}" alt="" loading="lazy" decoding="async"
                             class="w-full max-w-[560px] h-auto object-contain">
                    </div>
                @endif

                <div @class(['flex flex-col', 'lg:order-1' => $imagePosition === 'right'])>
                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section mb-6">
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

            </div>
        </div>
    </div>
</section>
