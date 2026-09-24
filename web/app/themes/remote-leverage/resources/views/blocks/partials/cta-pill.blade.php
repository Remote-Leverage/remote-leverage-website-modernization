{{-- The 2026 homepage's call-to-action pill, used down the page (hero, roles, process, "Skip the
     Hiring Headache", guarantee) and identical at every one of them.

     Measured off Homepage V3.png at 1366px: pill 348x61, fill #F90066 (--color-brand-magenta);
     label 19px/700 uppercase white with tracking -0.45px, ink 550..766; a 23px circled chevron
     at 801..823; 41px of padding left of the label and 33px right of the icon. The ring is a
     1.5px #F90066 `outline` sitting 4px outside the fill — an outline rather than a second
     box-shadow layer so it stays correct on the pale hero, the #F4F6FC bands and the purple
     guarantee panel alike, where a shadow gap would have to know the background colour.

     The class list comes from BlockDefaults::ctaPillClasses() so the reviews wall can put the
     same pill on a <button> carrying Alpine state without copying it.

     Params: $text, $url, optional $class for positioning, optional $icon ('arrow', the
     default, or 'chevron-down' for a disclosure control), and optional $size ('default', or
     'compact' to trim the gap and horizontal padding for a narrow container, or 'small' for the
     /become-a-partner/ comp's 311x50 pill with no ring). --}}
<a href="{{ $url ?? '#booking-footer' }}"
   class="{{ \App\Support\BlockDefaults::ctaPillClasses($class ?? '', $size ?? 'default') }}">
    <span>{{ $text ?? 'BOOK A CONSULTATION' }}</span>
    @include('blocks.partials.cta-pill-icon', ['icon' => $icon ?? 'arrow', 'iconSize' => ($size ?? 'default') === 'small' ? 18 : 23])
</a>
