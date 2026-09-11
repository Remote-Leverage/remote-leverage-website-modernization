{{-- Production's "which is more affordable" band: heading over two equal photo-topped
     narrative cards. Type from the shared `section` / `eyebrow` / `card` tokens. --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline)
                <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section max-w-[660px] mb-10">
                    {!! $headline !!}
                </h2>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2.5">
                @foreach ($cards as $card)
                    @continue(empty($card['title']) && empty($card['image']))
                    <div class="flex flex-col overflow-hidden rounded-badge bg-white">
                        @if (! empty($card['image']))
                            <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                                 class="w-full h-[338px] object-cover">
                        @endif

                        <div class="flex flex-col gap-4 px-6 py-6">
                            @if (! empty($card['title']))
                                <h3 class="font-display font-semibold text-eyebrow text-black">{{ $card['title'] }}</h3>
                            @endif

                            @if (! empty($card['body']))
                                <div class="text-card text-black [&_p]:mb-4 [&_p:last-child]:mb-0 [&_strong]:font-bold [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_li]:leading-relaxed [&_li]:marker:text-brand-purple">
                                    {!! $card['body'] !!}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>
