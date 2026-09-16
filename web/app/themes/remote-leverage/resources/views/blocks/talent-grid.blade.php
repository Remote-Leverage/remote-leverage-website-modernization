{{-- Candidate profile cards — portrait, name with verified badge, job title, bio and a 'Worked at'
     logo. `layout` renders a wrapping four-across grid, one horizontally scrolling row, or a
     grid that becomes a swipeable row below sm. --}}
@php
    $layout = $layout ?? 'grid';
    $isRow = $layout === 'row';
    $isSwipe = $layout === 'swipe';
@endphp
<div class="w-full">
    <h2 class="sr-only">Pre-Vetted Remote Professionals</h2>
    @if ($isRow)
        {{-- Marquee: the track is rendered twice so the loop is seamless, and the wrapper
             bleeds full-width rather than stopping at the container. --}}
        <div class="rl-talent-grid-marquee">
            <div class="rl-talent-grid-row animate-marquee-left">
                @foreach (array_merge($cards, $cards) as $card)
                    @include('blocks.partials.talent-grid-card', ['card' => $card, 'eager' => $loop->first])
                @endforeach
            </div>
        </div>
    @elseif ($isSwipe)
        {{-- Below sm the cards swipe horizontally, one-and-a-bit in view, snapping; from sm up
             this is the identical four-across grid as the default layout. Eight portrait cards
             two-across cost 1,246px of vertical space on a 390px screen, against the 227px
             production spends scrolling the same profiles. --}}
        <div class="flex gap-card overflow-x-auto snap-x snap-mandatory scroll-px-4 px-4 -mx-4 pb-2
                    [scrollbar-width:none] [&::-webkit-scrollbar]:hidden
                    sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0 sm:mx-0 sm:pb-0">
            @foreach ($cards as $card)
                <div class="shrink-0 basis-[72%] snap-start sm:basis-auto sm:shrink">
                    @include('blocks.partials.talent-grid-card', ['card' => $card, 'eager' => $loop->first])
                </div>
            @endforeach
        </div>
    @else
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-card">
        @foreach ($cards as $card)
            @include('blocks.partials.talent-grid-card', ['card' => $card, 'eager' => $loop->first])
        @endforeach
    </div>
    @endif
</div>
