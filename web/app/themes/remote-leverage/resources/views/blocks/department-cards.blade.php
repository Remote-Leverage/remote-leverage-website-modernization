{{-- Role/department cards: a four-across grid of photo-backed cards with a frosted overlay and
     white title + description sitting at the bottom.

     Two across below sm, like the rest of the card decks. One column put a 390x450 photo on
     every card and cost the homepage 1,836px for four of them; the shorter min-height applies
     only at that width, where the card is ~163px wide and 420px of photo is disproportionate. --}}
<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-card">
    @foreach ($cards as $sp)
        <div class="rl-department-card min-h-70 sm:min-h-105">
            <img class="rl-department-card__bg w-full h-full object-cover" src="{{ $sp['img'] }}"
                alt="{!! strip_tags($sp['title']) !!}" loading="lazy" decoding="async" width="300" height="420">
            <div class="rl-department-card__overlay"></div>
            <div class="rl-department-card__blur"></div>
            <div class="rl-department-card__content p-card">
                <h3 class="font-display text-[17px] sm:text-[22px] font-bold text-white leading-snug mb-1 sm:mb-2 drop-shadow-sm">
                    {!! $sp['title'] !!}</h3>
                <p class="text-[13px] sm:text-sm text-white/85 leading-relaxed drop-shadow-sm">{{ $sp['desc'] }}</p>
            </div>
        </div>
    @endforeach
</div>
