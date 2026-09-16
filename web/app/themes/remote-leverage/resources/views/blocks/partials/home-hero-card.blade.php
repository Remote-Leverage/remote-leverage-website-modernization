{{-- One floating talent card in the homepage hero: name + flag, role, an optional black rate
     pill, and an optional cutout portrait clipped by the card's bottom edge.

     Measured off Homepage V3.png: 180x255 at 1366px (colour-run chords corrected for the
     card's own rotation), 16px radius, 18px padding, name 17/700, role 13px #60636C, rate pill
     black with 12/700 white. The surface colour is passed in — #DDE2F6
     (--color-lavender-tint) behind, #EBEBFF in front.

     Params: $card (name, role, rate, flag, photo), $surface. --}}
<div class="relative flex h-[255px] w-[180px] flex-col overflow-hidden rounded-2xl {{ $surface }} p-[18px] shadow-[0_18px_40px_-18px_rgba(19,19,47,0.35)]">
  <div class="relative z-10">
    {{-- The name holds one line: 180px of card less 36px of padding leaves 144px, which
         "André Vilalobos" plus a flag fills exactly at 15px. nowrap makes that a hard promise
         rather than something that survives until someone adds a longer name. --}}
    <p class="flex items-center gap-1.5 whitespace-nowrap font-display text-[15px] font-bold leading-tight text-brand-hero">
      <span>{{ $card['name'] }}</span>
      @if (! empty($card['flag']))
        <img src="{{ $card['flag'] }}" alt="" width="14" height="14" class="h-[14px] w-[14px] shrink-0" decoding="async">
      @endif
    </p>

    @if (! empty($card['role']))
      <p class="mt-1 font-display text-[13px] leading-tight text-[#60636C]">{{ $card['role'] }}</p>
    @endif

    @if (! empty($card['rate']))
      <span class="mt-3 inline-flex items-center rounded-full bg-black px-3 py-1 font-display text-[12px] font-bold leading-none text-white">
        {{ $card['rate'] }}
      </span>
    @endif
  </div>

  {{-- The portrait is a transparent cutout that sits on the card floor and is clipped by the
       card's own rounded bottom edge, which is why it is absolutely placed rather than in flow. --}}
  @if (! empty($card['photo']))
    <img src="{{ $card['photo'] }}" alt="" width="200" height="220"
         class="pointer-events-none absolute -bottom-px left-1/2 z-0 h-[150px] w-auto max-w-none -translate-x-1/2 object-contain object-bottom "
         decoding="async" loading="eager">
  @endif
</div>
