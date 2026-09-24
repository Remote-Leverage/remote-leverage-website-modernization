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
    // The split only makes sense when nothing occupies the media cell.
    $splitHeading = ($headingBesideBody ?? false) && empty($videoUrl) && empty($image);

    $headingTone = ($headingColor ?? 'default') === 'navy'
        ? 'text-brand-navy'
        : ($isDark ? 'text-white' : 'text-black');
@endphp
<section @class([
    'w-full',
    'py-14 lg:py-20' => ($padding ?? 'default') !== 'roomy',
    // /become-a-partner/'s bands all run 100px above and below.
    'py-16 lg:py-[100px]' => ($padding ?? 'default') === 'roomy',
    'bg-bg-light' => ! $isDark && ! $isWhite,
    'bg-surface-white' => $isWhite,
    'bg-brand-dark-violet' => $isDark,
])>
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div @class([
                'grid grid-cols-1 gap-10',
                'lg:grid-cols-2 lg:gap-16' => ! $splitHeading,
                'lg:items-center' => ! $splitHeading && ($verticalAlign ?? 'center') !== 'top',
                // The partner comp tops the heading level with the photo.
                'lg:items-start' => ! $splitHeading && ($verticalAlign ?? 'center') === 'top',
                // Production's text-only band, measured off /compare-athena/ on
                // 2026-09-15: a 196px heading column, a 160px gutter and a 714px
                // measure for the copy, top-aligned. The copy measure is what makes
                // the paragraph wrap — and so the band stand — as production's does.
                'lg:grid-cols-[196px_minmax(0,714px)] lg:gap-x-[160px] lg:items-start' => $splitHeading,
            ])>

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
                             @class(['w-full h-auto object-contain', 'rounded-badge' => $imageRounded ?? false])>
                    </div>
                @endif

                @if ($splitHeading && $headline)
                    <div @class(['flex flex-col', $alignText])>
                        <h2 @class(['font-display font-bold text-3xl sm:text-4xl lg:text-[42px] lg:leading-[48px] lg:max-w-[146px]', $headingTone])>
                            {!! $headline !!}
                        </h2>
                    </div>
                @endif

                <div @class(['flex flex-col', $alignText, 'lg:order-1' => $imagePosition === 'right'])>
                    @if ($headline && ! $splitHeading)
                        <h2 @class(['font-display font-bold text-3xl sm:text-4xl lg:text-section mb-6', $headingTone])>
                            {!! $headline !!}
                        </h2>
                    @endif

                    @if ($body)
                        {{-- Body follows $isDark like the heading does. It hard-coded text-black
                             until 2026-09-15, so selecting tone: dark rendered black copy on a
                             dark band — invisible, with nothing in the editor to warn you. --}}
                        {{-- The split band sets copy 16px/24px, as production does there;
                             every other usage keeps the shared 15px `text-card` step. --}}
                        <div @class([
                            'text-card' => ! $splitHeading,
                            'text-[16px] leading-[24px]' => $splitHeading,
                            'text-white' => $isDark,
                            'text-black' => ! $isDark,
                            '[&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-bold' => true,
                        ])>
                            {!! $body !!}
                        </div>
                    @endif

                    {{-- A numbered vertical timeline, /become-a-partner/'s "You identify the need"
                         list off the Partner LP Figma frame at 1366px: 34px brand-purple discs
                         joined by a 1px brand-purple rule, 18/25 medium copy 14px to the right,
                         items 57px apart. Only the last item wraps in the comp, so the rule runs
                         from each disc to the next rather than being one line behind them all. --}}
                    @if (! empty($timeline))
                        <ol class="mt-3 flex flex-col">
                            @foreach ($timeline as $i => $item)
                                <li class="relative flex items-start gap-3.5 pb-6 last:pb-0">
                                    @unless ($loop->last)
                                        <span class="absolute left-[17px] top-[33px] bottom-0 w-px bg-brand-purple" aria-hidden="true"></span>
                                    @endunless
                                    <span class="relative flex h-[33px] w-[34px] shrink-0 items-center justify-center rounded-full bg-brand-purple font-display text-[13px] font-bold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                                    <span @class(['pt-1 font-display text-[16px] font-medium leading-[25px] sm:text-[18px]', 'text-white' => $isDark, 'text-black' => ! $isDark])>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ol>
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
