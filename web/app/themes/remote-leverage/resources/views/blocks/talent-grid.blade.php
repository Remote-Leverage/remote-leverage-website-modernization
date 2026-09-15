{{-- Candidate profile cards — portrait, name with verified badge, job title, bio and a 'Worked at'
     logo. `layout` renders either a wrapping four-across grid or one horizontally scrolling row. --}}
@php
    $isRow = ($layout ?? 'grid') === 'row';
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
    @else
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-card">
        @foreach ($cards as $card)
            @include('blocks.partials.talent-grid-card', ['card' => $card, 'eager' => $loop->first])
        @endforeach
    </div>
    @endif
</div>
