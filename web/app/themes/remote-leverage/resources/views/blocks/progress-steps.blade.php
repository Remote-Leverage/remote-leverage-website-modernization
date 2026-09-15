{{-- Heading and intro over a three-segment progress bar — a hairline full-width rule with a
     5px and a 10px rounded segment stacked on its left — then the steps in equal columns
     separated by vertical hairlines. Production uses this for "How It Works" on the affiliate
     programme and "Three Steps to a Fully Staffed Team" on the partner landing pages. --}}
<section class="w-full py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($headline)
                <h2 class="w-full font-display text-3xl sm:text-4xl font-bold text-black tracking-[-0.03em] mb-4">
                    {!! $headline !!}
                </h2>
            @endif

            @if ($subheadline)
                <p class="w-full text-black/80 leading-relaxed mb-10">{{ $subheadline }}</p>
            @endif

            <svg class="w-full h-auto mb-14" width="1231" height="10" viewBox="0 0 1231 10" fill="none"
                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M5 5.97583L1230 4.00002" stroke="currentColor" stroke-linecap="round" />
                <path d="M79 5H815" stroke="currentColor" stroke-width="5" stroke-linecap="round" />
                <path d="M5 5H400" stroke="currentColor" stroke-width="10" stroke-linecap="round" />
            </svg>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-card w-full divide-y sm:divide-y-0 sm:divide-x divide-black/15">
                @foreach ($steps as $step)
                    <div @class([
                        'py-6 sm:py-8',
                        'sm:pr-8' => $loop->first,
                        'sm:px-8' => ! $loop->first && ! $loop->last,
                        'sm:pl-8' => $loop->last && ! $loop->first,
                    ])>
                        <h4 class="font-display text-lg font-bold text-black tracking-[-0.02em] mb-2">{{ $step['title'] }}</h4>
                        <p class="text-sm text-black/70 leading-relaxed">{{ $step['text'] }}</p>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>
