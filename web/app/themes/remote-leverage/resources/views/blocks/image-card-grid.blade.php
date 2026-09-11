{{-- Production's photo-card row: 4 equal cards, 10px radius, image capped to the
     card's top corners. Type from the shared `section` / `eyebrow` / `card` tokens. --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline || $subheadline)
                <div class="mb-10">
                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section">{!! $headline !!}</h2>
                    @endif

                    @if ($subheadline)
                        <p class="text-black text-lg lg:text-eyebrow font-normal">{!! $subheadline !!}</p>
                    @endif
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                @foreach ($cards as $card)
                    <div class="flex flex-col overflow-hidden rounded-badge bg-white">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                                 class="w-full h-[212px] object-cover">
                        @endif

                        <div class="flex flex-col gap-1 px-5 py-4">
                            @if (! empty($card['title']))
                                <h3 class="font-display font-bold text-card text-black">{{ $card['title'] }}</h3>
                            @endif

                            @if (! empty($card['text']))
                                <p class="text-card text-black">{{ $card['text'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>
