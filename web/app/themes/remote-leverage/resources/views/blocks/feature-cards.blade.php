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
@endphp
{{-- Two across on mobile, not one. A single column with a full-width image cost the homepage
     ~1,600px on the "World's Best Talent" band alone, rendering the same 1,265 characters
     production fits into 1,091px. A one-column deck stays one column. --}}
<div @class([
    'grid gap-card w-full',
    'grid-cols-1' => $columns === '1',
    'grid-cols-2 sm:grid-cols-2' => $columns !== '1',
    'lg:grid-cols-4 mb-[12px]' => $columns === '4',
    'lg:grid-cols-2' => $columns === '2',
    'lg:grid-cols-3' => ! in_array($columns, ['1', '2', '4'], true),
])>
    @foreach ($cards as $card)
        @continue(!is_array($card))
        <div @class([
            'bg-white rounded-card flex flex-col justify-between border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300',
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
                <div @class(['p-3' => ! $isFlush && ! $isHorizontal, 'px-7 pt-6 pb-7' => $isFlush, 'sm:order-1' => $isHorizontal])>
                    {{-- Type steps down below sm, where the deck is two across and a card is
                         ~163px wide. At the sm sizes a title wrapped to four ragged lines. --}}
                    <h3 @class([
                        'font-display font-bold text-black tracking-[-0.02em] leading-snug mb-2 sm:mb-3',
                        'text-[17px] sm:text-[22px]' => ! $isFlush,
                        'text-[18px] sm:text-[27px] sm:leading-[32px] tracking-[-0.81px]' => $isFlush,
                    ])>
                        {!! $card['title'] !!}
                    </h3>
                    <p class="text-[13px] sm:text-[14px] leading-relaxed text-black">
                        {{ $card['desc'] }}
                    </p>
                </div>
            </div>
        </div>
    @endforeach
</div>
