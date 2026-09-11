@php
    // Production uses this shape at two scales: a 4-across row of small-titled cards
    // and a 3-across row of large-titled ones. Image height follows the column count.
    $isThree = ($columns ?? 4) === 3;
    $isLarge = ($titleSize ?? 'small') === 'large';
@endphp

<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline || $subheadline)
                <div class="mb-10 max-w-[660px]">
                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section">{!! $headline !!}</h2>
                    @endif

                    @if ($subheadline)
                        <p class="text-black {{ $isLarge ? 'text-base lg:text-lead mt-3' : 'text-lg lg:text-eyebrow font-normal' }}">
                            {!! $subheadline !!}
                        </p>
                    @endif
                </div>
            @endif

            <div @class([
                'grid grid-cols-1 sm:grid-cols-2 gap-2.5',
                'lg:grid-cols-3' => $isThree,
                'lg:grid-cols-4' => ! $isThree,
            ])>
                @foreach ($cards as $card)
                    <div class="flex flex-col overflow-hidden rounded-badge bg-white">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                                 class="w-full object-cover {{ $isThree ? 'h-[168px]' : 'h-[212px]' }}">
                        @endif

                        <div class="flex flex-col gap-2.5 {{ $isLarge ? 'px-[30px] pt-2.5 pb-5' : 'px-5 py-4' }}">
                            @if (! empty($card['eyebrow']))
                                <span class="font-display font-bold text-step-numeral text-lead">{{ $card['eyebrow'] }}</span>
                            @endif

                            @if (! empty($card['title']))
                                <h3 @class([
                                    'font-display font-bold text-black',
                                    'text-xl lg:text-eyebrow' => $isLarge,
                                    'text-card' => ! $isLarge,
                                ])>{!! $card['title'] !!}</h3>
                            @endif

                            @if (! empty($card['text']))
                                <p class="text-card text-black">{{ $card['text'] }}</p>
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
