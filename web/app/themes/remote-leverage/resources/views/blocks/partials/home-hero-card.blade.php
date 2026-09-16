{{-- One talent card in the homepage hero: name + flag, role, and a cutout portrait standing on
     the card floor, clipped by its rounded bottom edge.

     The card fills its positioned wrapper, which is sized as a share of the fan box — 240x322
     at xl (the comp's 180x255 scaled up a quarter on request, 2026-09-16) and 75% of that at
     lg. The portrait is 73% of the card's height for the same reason: one number holds at both
     sizes. Type is the one thing that cannot be a percentage, so it steps at xl. The surface
     colour is passed in: #DDE2F6 (--color-lavender-tint) for the two cards set back, #EBEBFF
     for the one in front.

     No rate pill: dropped 2026-09-16. The portrait took the space back.

     Params: $card (name, role, flag, photo), $surface. --}}
<div class="relative flex h-full w-full flex-col overflow-hidden rounded-2xl {{ $surface }} p-4 xl:p-[22px] shadow-[0_18px_40px_-18px_rgba(19,19,47,0.35)] transition-shadow duration-500 group-hover:shadow-[0_30px_46px_-20px_rgba(19,19,47,0.5)]">
  <div class="relative z-10">
    {{-- Name and role each hold one line at both sizes: 240px of card less 44px of padding
         leaves 196px, which "Bruno Carvalho" plus a flag fills at 17px and "Administrative
         Assistant" at 14px. nowrap makes that a hard promise rather than something that
         survives until someone adds a longer name, and keeps every photo on one baseline. --}}
    <p class="flex items-center gap-1.5 whitespace-nowrap font-display text-[15px] font-bold leading-tight text-brand-hero xl:text-[17px]">
      <span>{{ $card['name'] }}</span>
      @if (! empty($card['flag']))
        <img src="{{ $card['flag'] }}" alt="" width="16" height="16" class="h-3.5 w-3.5 shrink-0 xl:h-4 xl:w-4" decoding="async">
      @endif
    </p>

    @if (! empty($card['role']))
      <p class="mt-1 whitespace-nowrap font-display text-[12.5px] leading-tight text-[#60636C] xl:text-[14px]">{{ $card['role'] }}</p>
    @endif
  </div>

  {{-- Absolutely placed rather than in flow so the figure stands on the card's floor and is
       clipped by its own rounded bottom edge. Every portrait is trimmed to its subject and set
       to a common 560px height upstream, so one CSS height gives all three the same scale. --}}
  @if (! empty($card['photo']))
    <img src="{{ $card['photo'] }}" alt="" width="350" height="560"
         class="pointer-events-none absolute -bottom-px left-1/2 z-0 h-[73%] w-auto max-w-none -translate-x-1/2 object-contain object-bottom"
         decoding="async" loading="eager">
  @endif
</div>
