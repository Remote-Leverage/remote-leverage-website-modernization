{{-- Benefit/feature card grid in 2, 3 or 4 columns. The image is optional — production's
     "Built to help you grow" band is a 2-up of text-only cards. Each card is an image, a title and a short
     description. `variant` picks production's inset image or flush-to-edge treatment; `ratio` sets
     the image aspect. --}}
@php
    $cards = is_array($cards ?? null) ? $cards : [];
    $ratio = trim((string) ($ratio ?? ''));
    // Only a plain "w/h" is honoured, so a bad field value can never emit arbitrary CSS.
    $hasRatio = (bool) preg_match('#^\d{1,4}/\d{1,4}$#', $ratio);
    [$ratioW, $ratioH] = $hasRatio ? explode('/', $ratio) : [null, null];
    $variant = $variant ?? 'inset';
    $isFlush = $variant === 'flush';
    $isHorizontal = $variant === 'horizontal';

    // 'frosted' is the 2026 homepage band: a ~20% white wash over the mesh gradient instead of
    // an opaque card on a flat ground (measured on Homepage V1.png — #F1F2FB reads #F4F4FC
    // inside a card on the blue side, #F1DEF3 reads #F3E4F7 on the pink side, both ≈0.2 alpha),
    // with that band's roomier type: a 28/32 title held to two lines so every card's body
    // starts on the same baseline whether its title runs to one line or two, and 16/25 body.
    $isFrosted = ($surface ?? 'solid') === 'frosted';
@endphp
{{-- Two across on mobile, not one. A single column with a full-width image cost the homepage
     ~1,600px on the "World's Best Talent" band alone, rendering the same 1,265 characters
     production fits into 1,091px. A one-column deck stays one column. --}}
{{-- Frosted is the exception to the two-across rule above: the 2026 mobile comp stacks this
     band one card per row, and its cards carry 16px body copy that has no business in a
     ~163px column. --}}
<div @class([
    'grid gap-card w-full',
    'grid-cols-1' => $columns === '1' || $isFrosted,
    'grid-cols-2 sm:grid-cols-2' => $columns !== '1' && ! $isFrosted,
    'lg:grid-cols-4 mb-[12px]' => $columns === '4',
    'lg:grid-cols-2' => $columns === '2',
    'lg:grid-cols-3' => ! in_array($columns, ['1', '2', '4'], true),
])>
    @foreach ($cards as $card)
        @continue(!is_array($card))
        <div @class([
            'rounded-card flex flex-col justify-between transition-all duration-300',
            'bg-white border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)]' => ! $isFrosted,
            'bg-white/20 border border-white/45 hover:bg-white/35' => $isFrosted,
            'p-card' => ! $isFlush,
            'overflow-hidden' => $isFlush,
        ])>
            <div @class(['grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_200px] gap-5 items-center' => $isHorizontal])>
                @if (! empty($card['img']))
                <div @class([
                    'w-full overflow-hidden bg-[#f7f8fc]',
                    'rounded-xl mb-5' => ! $isFlush && ! $isHorizontal,
                    'rounded-xl sm:order-2' => $isHorizontal,
                ])>
                    <img src="{{ $card['img'] }}" alt="{!! strip_tags($card['title']) !!}"
                        width="{{ $hasRatio ? $ratioW : ($columns === '4' ? '248' : '344') }}"
                        height="{{ $hasRatio ? $ratioH : ($columns === '4' ? '190' : '230') }}"
                        loading="lazy" decoding="async"
                        @style(['aspect-ratio: ' . $ratioW . ' / ' . $ratioH => $hasRatio])
                        @class([
                            'w-full h-auto object-cover',
                            'rounded-xl' => ! $isFlush,
                            'aspect-248/190' => ! $hasRatio && $columns === '4',
                            'aspect-344/130' => ! $hasRatio && $columns !== '4',
                        ])>
                </div>
                @endif
                <div @class(['p-3' => ! $isFlush && ! $isHorizontal && ! $isFrosted, 'px-7 pt-6 pb-7' => $isFlush && ! $isFrosted, 'px-5 pt-6 pb-7' => $isFrosted, 'sm:order-1' => $isHorizontal])>
                    {{-- Type steps down below sm, where the deck is two across and a card is
                         ~163px wide. At the sm sizes a title wrapped to four ragged lines. --}}
                    <h3 @class([
                        'font-display font-bold text-black tracking-[-0.02em] mb-2 sm:mb-3',
                        'leading-snug' => ! $isFrosted,
                        'text-[17px] sm:text-[22px]' => ! $isFlush && ! $isFrosted,
                        'text-[18px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]' => $isFlush && ! $isFrosted,
                        // Two lines are reserved whether the title fills them or not, so every
                        // card in the row starts its body on the same baseline — which is what
                        // the comp does across "Hire in 48 hours" and "Top-tier Latin American
                        // talent" alike.
                        'text-[20px] leading-[1.14] sm:text-[28px] sm:leading-[32px] sm:min-h-[64px] sm:mb-8' => $isFrosted,
                    ])>
                        {!! $card['title'] !!}
                    </h3>
                    <p @class([
                        'text-black',
                        'text-[13px] sm:text-[14px] leading-relaxed' => ! $isFrosted,
                        'text-[15px] sm:text-[16px] sm:leading-[25px] leading-relaxed' => $isFrosted,
                    ])>
                        {{ $card['desc'] }}
                    </p>
                </div>
            </div>
        </div>
    @endforeach
</div>
