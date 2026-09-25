@php
    // Production uses this shape at two scales: a 4-across row of small-titled cards
    // and a 3-across row of large-titled ones. Image height follows the column count.
    $isThree = ($columns ?? 4) === 3;
    $isLarge = ($titleSize ?? 'small') === 'large';

    // 'medium' is /become-a-partner/'s "many ways to partner" row, off the Partner LP Figma
    // frame at 1366px: a 270x560 card with a 150px photo, a 19/26 title, 14/20 text and 20px
    // padding (30px under the photo), in a band with 100px above and below. It is one
    // composition, so the type, padding and band rhythm travel together.
    $isMedium = ($titleSize ?? 'small') === 'medium';

    // Only a plain "w/h" is honoured, so a bad field value can never emit arbitrary CSS.
    $ratio = trim((string) ($imageRatio ?? ''));
    $hasRatio = (bool) preg_match('#^\d{1,4}/\d{1,4}$#', $ratio);

    // 'pill' is the partner row's black "BOOK A CALL" pill, pinned to the foot of every card
    // so the row's buttons line up whatever the length of the copy above them.
    $isPillCta = ($ctaStyle ?? 'button') === 'pill';

    // Chip tints, sampled off the same frame. Whole class strings so Tailwind sees them.
    $tagTones = [
        'purple' => 'bg-[#F4E7FF]',
        'teal' => 'bg-[#D1EFEB]',
        'blue' => 'bg-[#DAE5FF]',
        'red' => 'bg-[#FFDEDC]',
    ];
@endphp

@php
    // Production centres this band on the ecommerce page and left-aligns it on the comparison
    // pages, so the header alignment is an option rather than a fixed 660px left column.
    $isCentred = ($align ?? 'left') === 'center';
@endphp

<section @class(['w-full bg-bg-light', 'py-14 lg:py-20' => ! $isMedium, 'py-16 lg:py-[100px]' => $isMedium])>
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline || $subheadline)
                <div @class(['mb-10' => ! $isMedium, 'mb-10 lg:mb-[50px]' => $isMedium, 'max-w-[660px]' => ! $isCentred, 'max-w-[860px] mx-auto text-center' => $isCentred && ! $isMedium, 'max-w-[735px] mx-auto text-center' => $isCentred && $isMedium])>
                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section">{!! $headline !!}</h2>
                    @endif

                    @if ($subheadline)
                        <p @class([
                            'text-black',
                            'text-base lg:text-lead mt-3' => $isLarge,
                            'text-lg lg:text-eyebrow font-normal' => ! $isLarge && ! $isMedium,
                            'mt-5 font-display text-base lg:text-lg lg:leading-[25px] lg:tracking-[-0.36px]' => $isMedium,
                        ])>
                            {!! $subheadline !!}
                        </p>
                    @endif
                </div>
            @endif

            <div @class([
                'grid grid-cols-1 sm:grid-cols-2',
                'gap-2.5' => ! $isMedium,
                'gap-2' => $isMedium,
                'lg:grid-cols-3' => $isThree,
                'lg:grid-cols-4' => ! $isThree,
            ])>
                @foreach ($cards as $card)
                    {{-- `emphasis` draws production's black outline on the featured pricing card. --}}
                    <div @class([
                        'flex flex-col overflow-hidden rounded-badge bg-white',
                        'border border-black' => ! empty($card['emphasis']),
                    ])>
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                                 @style(['aspect-ratio: '.str_replace('/', ' / ', $ratio) => $hasRatio])
                                 @class([
                                     'w-full object-cover',
                                     'h-[168px]' => ! $hasRatio && $isThree,
                                     'h-[212px]' => ! $hasRatio && ! $isThree,
                                     'h-auto' => $hasRatio,
                                 ])>
                        @elseif (! empty($card['icon']))
                            {{-- An icon sits at its natural size rather than filling the card top.
                                 Production's pricing cards use ~88px and ~146px marks; painting
                                 those full-bleed would blow them up to the full card width. --}}
                            <div class="flex justify-center pt-[30px]">
                                <img src="{{ $card['icon'] }}" alt="" loading="lazy" decoding="async"
                                     style="width:{{ $card['icon_width'] ?? 88 }}px"
                                     class="h-auto max-w-full object-contain">
                            </div>
                        @endif

                        <div @class([
                            'flex flex-col',
                            'gap-2.5' => ! $isMedium,
                            'gap-3.5' => $isMedium,
                            'px-[30px] pt-2.5 pb-5' => $isLarge,
                            'px-5 py-4' => ! $isLarge && ! $isMedium,
                            'px-5 pt-[30px] pb-5' => $isMedium,
                            // Grow to the card's full height so mt-auto can pin the pill.
                            'flex-1' => $isPillCta,
                        ])>
                            @if (! empty($card['eyebrow']))
                                <span class="font-display font-bold text-step-numeral text-lead">{{ $card['eyebrow'] }}</span>
                            @endif

                            @if (! empty($card['title']))
                                <h3 @class([
                                    'font-display font-bold text-black',
                                    'text-xl lg:text-eyebrow' => $isLarge,
                                    'text-card' => ! $isLarge && ! $isMedium,
                                    'text-[19px] leading-[26px] tracking-[-0.02em]' => $isMedium,
                                ])>{!! $card['title'] !!}</h3>
                            @endif

                            @if (! empty($card['text']))
                                <p @class(['text-black', 'text-card' => ! $isMedium, 'font-display text-[14px] leading-5 tracking-[-0.42px]' => $isMedium])>{{ $card['text'] }}</p>
                            @endif

                            {{-- Audience chips, tinted per card. One per line in the field. --}}
                            @if (! empty($card['tags']))
                                <ul class="flex flex-wrap gap-1">
                                    @foreach ($card['tags'] as $tag)
                                        <li class="rounded-md px-2 py-1 font-display text-[11px] leading-5 tracking-[-0.33px] text-black {{ $tagTones[$card['tag_tone']] ?? $tagTones['purple'] }}">{{ $tag }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- Per-card CTA. Production's pricing cards each carry their own
                                 black, 5px-radius button rather than one CTA for the section. --}}
                            @if (! empty($card['cta_text']) && $isPillCta)
                                {{-- The wrapper takes the auto margin; its 10px on top of the column's
                                     14px gap is the least the pill ever sits under the chips. --}}
                                <div class="mt-auto pt-2.5">
                                    <a href="{{ $card['cta_url'] ?: '#booking-footer' }}"
                                       class="group inline-flex w-full items-center justify-center gap-3 rounded-pill bg-black px-5 py-[11px] font-display text-[15px] font-bold uppercase leading-[21px] tracking-[-0.15px] text-white transition-colors hover:bg-surface-black-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2">
                                        <span>{{ $card['cta_text'] }}</span>
                                        @include('partials.icon-circle-arrow', ['class' => 'h-5 w-5 shrink-0 transition-transform group-hover:translate-x-0.5'])
                                    </a>
                                </div>
                            @elseif (! empty($card['cta_text']))
                                <a href="{{ $card['cta_url'] ?: '#booking-footer' }}"
                                   class="mt-auto inline-flex w-full items-center justify-center rounded-[5px] bg-black px-5 py-3 text-xs font-bold uppercase tracking-wider text-white transition hover:bg-black/85">
                                    {{ $card['cta_text'] }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($ctaText)
                <a href="{{ $ctaUrl }}"
                   class="mt-2.5 flex w-full items-center justify-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep px-8 py-5 text-center font-bold uppercase text-white text-lead transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple focus-visible:ring-offset-2">
                    {{ $ctaText }}
                </a>
            @endif

        </div>
    </div>
</section>
