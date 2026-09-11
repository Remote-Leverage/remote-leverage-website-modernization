{{-- Production's "How Remote Leverage Works": stacked full-width cards, each with an
     oversized grey numeral, the step copy, and a UI illustration on the right.
     Numeral and step type come from the `numeral` / `step` / `step-numeral` tokens. --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline || $subheadline)
                <div class="mb-10">
                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section">{!! $headline !!}</h2>
                    @endif

                    @if ($subheadline)
                        <p class="text-black text-lg lg:text-lead">{!! $subheadline !!}</p>
                    @endif
                </div>
            @endif

            <div class="flex flex-col gap-2.5">
                @foreach ($steps as $step)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-6 rounded-badge bg-white px-5 py-5 overflow-hidden">
                        <div class="shrink-0 font-display font-normal text-step-numeral text-6xl sm:text-7xl lg:text-numeral leading-none">
                            {{ $step['number'] }}
                        </div>

                        <div class="flex flex-col gap-3 sm:w-[38%] shrink-0">
                            <h3 class="font-display font-semibold text-black text-2xl lg:text-step">{{ $step['title'] }}</h3>
                            @if (! empty($step['text']))
                                <p class="text-black text-base lg:text-lead">{{ $step['text'] }}</p>
                            @endif
                        </div>

                        @if (! empty($step['image']))
                            <div class="flex-1 flex justify-end min-w-0">
                                <img src="{{ $step['image'] }}" alt="" loading="lazy" decoding="async"
                                     class="max-h-[232px] w-auto object-contain">
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>
