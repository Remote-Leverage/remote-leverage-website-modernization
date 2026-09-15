{{-- Image one side, heading + rich copy + CTA the other. Type from the shared
     `section` / `card` tokens; the CTA matches production's black pill. --}}
@php
    $isDark = ($tone ?? 'light') === 'dark';
    // 'white' was added for /referral-program/, whose proof rows sit on white rather than the
    // pale default. Unset/legacy values still resolve to the pale surface.
    $isWhite = ($tone ?? 'light') === 'white';

    // Whole class strings so Tailwind's scanner sees them literally.
    $alignText = match ($textAlign ?? 'left') {
        'right' => 'text-right',
        'center' => 'text-center',
        default => 'text-left',
    };
    $headingTone = ($headingColor ?? 'default') === 'navy'
        ? 'text-brand-navy'
        : ($isDark ? 'text-white' : 'text-black');
@endphp
<section @class([
    'w-full py-14 lg:py-20',
    'bg-bg-light' => ! $isDark && ! $isWhite,
    'bg-surface-white' => $isWhite,
    'bg-brand-dark-violet' => $isDark,
])>
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 lg:items-center">

                {{-- The media slot is an image, or a video player when `video_url` is set
                     (production's third "Real Businesses, Real Results" card is a video
                     testimonial in the same row shape). --}}
                @if (! empty($videoUrl))
                    <div @class(['flex justify-center', 'lg:order-2' => $imagePosition === 'right'])>
                        <video controls preload="none"
                               @if ($image) poster="{{ $image }}" @endif
                               style="max-width:{{ $imageMaxWidth ?? 560 }}px"
                               class="w-full h-auto rounded-card bg-black">
                            <source src="{{ $videoUrl }}" type="video/mp4">
                        </video>
                    </div>
                @elseif ($image)
                    <div @class(['flex justify-center', 'lg:order-2' => $imagePosition === 'right'])>
                        <img src="{{ $image }}" alt="" loading="lazy" decoding="async"
                             style="max-width:{{ $imageMaxWidth ?? 560 }}px"
                             class="w-full h-auto object-contain">
                    </div>
                @endif

                <div @class(['flex flex-col', $alignText, 'lg:order-1' => $imagePosition === 'right'])>
                    @if ($headline)
                        <h2 @class(['font-display font-bold text-3xl sm:text-4xl lg:text-section mb-6', $headingTone])>
                            {!! $headline !!}
                        </h2>
                    @endif

                    @if ($body)
                        {{-- Body follows $isDark like the heading does. It hard-coded text-black
                             until 2026-09-15, so selecting tone: dark rendered black copy on a
                             dark band — invisible, with nothing in the editor to warn you. --}}
                        <div class="text-card {{ $isDark ? 'text-white' : 'text-black' }} [&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-bold">
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
