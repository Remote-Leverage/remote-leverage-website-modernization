{{-- Production's /vaonboardingguide/ training grid: purple gradient cards, each holding a
     16:9 player, a centred caption and an optional orange CTA. Tokens read off the live page
     with getComputedStyle (2026-09-15) — see app/Blocks/VideoCardGridBlock.php.

     Rows are flex, not grid: production pairs two 505px players with a 100px gutter inside a
     1170px column, and gives a full-width card the whole 1130px. A trailing unpaired half
     card therefore stays half-width instead of stretching, matching production. --}}
@php
    // Production's card gradient is offer-stack's band gradient inverted — purple at the top,
    // navy at the foot — so the cards read as lit from above against the white page.
    $card = 'linear-gradient(180deg, var(--color-brand-purple-deep) 0%, var(--color-brand-navy) 100%)';

    // 24/24 600 white, centred, shrink-to-fit. Production sets it in League Spartan.
    $caption = 'font-display text-[20px] leading-[24px] sm:text-[24px] sm:leading-[24px] font-semibold text-center text-white';

    $cta = 'inline-flex items-center justify-center rounded-[5px] bg-brand-orange hover:bg-brand-orange-warm px-10 py-[10px] font-display text-[20px] leading-[24px] sm:text-[24px] font-bold text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange focus-visible:ring-offset-2';
@endphp

<section class="w-full bg-surface-white py-[30px]">
    {{-- 1210 - 2×20 = production's 1170px inner column. Rows are 78px apart there, which is
         the row's own 38px bottom margin plus 2×10px row padding plus the column's 20px flex
         gap — all four, or the grid drifts ~20px per row against production. --}}
    <div class="mx-auto flex w-full max-w-[1210px] flex-col gap-5 px-5">

        @foreach ($rows as $row)
            {{-- 38px between rows; 100px between the two cards in a paired row. --}}
            <div class="mb-[38px] flex flex-col gap-10 p-[10px] last:mb-0 lg:flex-row lg:gap-[100px]">

                @foreach ($row as $item)
                    <div @class([
                        'flex flex-col gap-[14px] rounded-badge px-[10px] pb-5 pt-[10px]',
                        'w-full' => $item['width'] === 'full',
                        'w-full lg:w-[525px] lg:shrink-0' => $item['width'] === 'half',
                    ]) style="background: {{ $card }};">

                        {{-- Player and caption are one group 20px apart; the CTA sits 14px
                             below that group. Production splits the two gaps exactly this way
                             (measured on the live cards), so the caption does not drift toward
                             its button. --}}
                        <div class="flex flex-col gap-5">
                            {{-- `aspect-video` holds the 16:9 box before the iframe loads, so
                                 the card does not jump as six embeds settle. --}}
                            <div class="aspect-video w-full overflow-hidden rounded-badge bg-black">
                                <iframe src="{{ $item['videoUrl'] }}"
                                        title="{{ $item['title'] ?: 'Video' }}"
                                        class="h-full w-full"
                                        frameborder="0"
                                        loading="lazy"
                                        allow="autoplay; fullscreen; picture-in-picture; clipboard-write"
                                        allowfullscreen></iframe>
                            </div>

                            @if ($item['title'])
                                <h4 class="{{ $caption }}">
                                    @if ($item['titleUrl'])
                                        <a href="{{ $item['titleUrl'] }}" rel="noopener" target="_blank"
                                           class="text-white no-underline hover:underline">{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                </h4>
                            @endif
                        </div>

                        @if ($item['ctaText'])
                            <div class="text-center">
                                <a href="{{ $item['ctaUrl'] }}" rel="noopener" target="_blank"
                                   class="{{ $cta }}">{{ $item['ctaText'] }}</a>
                            </div>
                        @endif
                    </div>
                @endforeach

            </div>
        @endforeach

    </div>
</section>
