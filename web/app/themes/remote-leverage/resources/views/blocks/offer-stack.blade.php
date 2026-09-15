{{-- Production's /services/ shell: one #342567 → #6200A4 gradient band holding a stack of
     white rounded cards. Tokens below were read off the live page with getComputedStyle
     (2026-09-15): band gradient 180deg navy→purple-deep, 60px vertical padding, a 1320px
     container, 20px between cards, cards white/10px radius/40px 20px padding, the split
     divider a 2px #DBDBDB rule, pills a 240deg navy→purple-deep gradient at 10px radius,
     and the CTA production's orange #FB7501 at 5px radius.

     Production sets headings in League Spartan and body in Poppins; the theme dropped both
     fonts in favour of Inter Display (see the @font-face note in resources/css/app.css), so
     headings here use `font-display` and body the theme sans. --}}
@php
    $band = 'linear-gradient(180deg, var(--color-brand-navy) 0%, var(--color-brand-purple-deep) 100%)';
    $pill = 'linear-gradient(240deg, var(--color-brand-navy) 0%, var(--color-brand-purple-deep) 100%)';

    // Production's h2 is 40px/40px; it steps down on narrow screens where production simply
    // hides this whole stack (its mobile variant is a different, stale page — see the
    // pattern's note), so the small end is ours.
    $h2 = 'font-display font-bold text-[28px] leading-[32px] sm:text-[34px] sm:leading-[36px] lg:text-[40px] lg:leading-[40px] text-brand-navy text-center';

    // 16px/32px #333, with production's paragraph rhythm and bullet indent.
    // `break-words` matters: the onboarding card prints a bare guide URL, which otherwise
    // pushes the page into horizontal scroll at phone widths.
    $prose = 'text-[16px] leading-[32px] text-[#333] break-words [&_p]:mb-[14.4px] [&_p:last-child]:mb-0 [&_strong]:font-bold [&_em]:italic [&_ul]:list-disc [&_ul]:pl-10 [&_ul]:mb-[14.4px] [&_a]:underline';

    $cta = 'inline-flex items-center justify-center rounded-[5px] bg-brand-orange hover:bg-brand-orange-warm px-[60px] py-4 font-display text-[24px] leading-[24px] font-bold text-white transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-orange focus-visible:ring-offset-2';

    $footnote = 'font-display text-[20px] leading-[30px] lg:text-[26px] lg:leading-[39px] font-bold text-brand-navy text-center [&_p]:m-0 [&_strong]:font-bold';
@endphp

<section class="w-full py-[60px]" style="background: {{ $band }};">
    <div class="w-full px-5 sm:px-6">
        <div class="mx-auto w-full max-w-[1320px] flex flex-col gap-5">

            @foreach ($cards as $card)
                <div class="bg-surface-white rounded-badge px-5 py-10">

                    @if ($card['layout'] === 'split')
                        <div class="grid grid-cols-1 lg:grid-cols-2">

                            {{-- Copy column --}}
                            <div class="flex flex-col lg:pr-5">
                                @if ($card['headline'])
                                    <h2 class="{{ $h2 }} mb-8">{!! $card['headline'] !!}</h2>
                                @endif

                                @if ($card['widget'] === 'bundle-calculator')
                                    @include('blocks.partials.bundle-calculator')
                                @endif

                                @if ($card['body'])
                                    <div class="{{ $prose }}">{!! $card['body'] !!}</div>
                                @endif

                                @if ($card['ctaText'])
                                    <div class="mt-8 text-center">
                                        <a href="{{ $card['ctaUrl'] }}"
                                           @if ($card['ctaNewTab']) target="_blank" rel="noopener" @endif
                                           class="{{ $cta }}">{{ $card['ctaText'] }}</a>
                                    </div>
                                @endif
                            </div>

                            {{-- Offer pills + footnote. Production divides the two columns with
                                 a 2px #DBDBDB rule owned by the right column, and spreads the
                                 pills and the price to the card's top and bottom edges. --}}
                            <div @class([
                                'mt-10 lg:mt-0 flex flex-col items-center gap-5 lg:border-l-2 lg:border-[#DBDBDB] lg:p-[10px] lg:justify-between',
                                'lg:pt-[88px] lg:justify-start' => $card['pillsOffset'],
                            ])>
                                @foreach ($card['pills'] as $pillText)
                                    <div class="w-full max-w-[520px] rounded-badge px-[10px] py-5 text-center text-white"
                                         style="background: {{ $pill }};">
                                        <p @class([
                                            'font-display m-0',
                                            'text-[22px] leading-[26.4px] font-medium' => count($card['pills']) > 1,
                                            'text-[20px] leading-[26px] lg:text-[26px] lg:leading-[31.2px] font-bold' => count($card['pills']) <= 1,
                                        ])>{!! nl2br(str_replace(' - ', ' &#45; ', e($pillText))) !!}</p>
                                    </div>
                                @endforeach

                                @if ($card['footnote'])
                                    <div class="{{ $footnote }}">{!! $card['footnote'] !!}</div>
                                @endif
                            </div>

                        </div>
                    @else
                        {{-- Single centred column: the onboarding-guide card. --}}
                        <div class="flex flex-col">
                            @if ($card['headline'])
                                <h2 class="{{ $h2 }} mb-8">{!! $card['headline'] !!}</h2>
                            @endif

                            @if ($card['widget'] === 'bundle-calculator')
                                @include('blocks.partials.bundle-calculator')
                            @endif

                            @if ($card['body'])
                                <div class="{{ $prose }}">{!! $card['body'] !!}</div>
                            @endif

                            @if ($card['ctaText'])
                                <div class="mt-8 text-center">
                                    <a href="{{ $card['ctaUrl'] }}"
                                       @if ($card['ctaNewTab']) target="_blank" rel="noopener" @endif
                                       class="{{ $cta }}">{{ $card['ctaText'] }}</a>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            @endforeach

        </div>
    </div>
</section>
