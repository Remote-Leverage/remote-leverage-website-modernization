{{-- Two reassurance cards with a CTA pill centred beneath each column.
     Type from the shared `eyebrow` / `card` / `lead` tokens. --}}
<section class="w-full bg-bg-light pb-14 lg:pb-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2.5">
                @foreach ($items as $item)
                    @continue(empty($item['title']))
                    <div class="flex flex-col">
                        <div class="flex flex-col gap-5 rounded-badge bg-white px-6 py-7 h-full">
                            <h3 class="font-display font-semibold text-black text-2xl lg:text-eyebrow">
                                {!! $item['title'] !!}
                            </h3>

                            @if (! empty($item['text']))
                                <p class="text-card text-black">{!! $item['text'] !!}</p>
                            @endif
                        </div>

                        @if (! empty($item['ctaText']))
                            <div class="flex justify-end mt-6">
                                <a href="{{ $item['ctaUrl'] }}"
                                   class="group inline-flex items-center gap-3 rounded-pill bg-brand-purple hover:bg-brand-purple-deep px-9 lg:px-[45px] py-5 font-bold uppercase text-white text-base lg:text-lead transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple focus-visible:ring-offset-2">
                                    <span>{{ $item['ctaText'] }}</span>
                                    @include('partials.icon-circle-arrow', ['class' => 'w-6 h-6 shrink-0 transition-transform group-hover:translate-x-0.5'])
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
