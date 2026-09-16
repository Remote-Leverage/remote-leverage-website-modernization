{{-- The 2026 homepage's call-to-action pill, used five times down the page (hero, roles,
     process, "Skip the Hiring Headache", guarantee) and identical at every one of them.

     Measured off Homepage V3.png at 1366px on 2026-09-15, and identical at all five sites:
     pill 348x61, fill #F90066 (--color-brand-magenta); label 19px/700 uppercase white with
     tracking -0.45px, ink 550..766; a 23px circled chevron at 801..823; 41px of padding left
     of the label and 33px right of the icon. The ring is a 1.5px #F90066 `outline` sitting 4px
     outside the fill — an outline rather than a second box-shadow layer so it stays correct on
     the pale hero, the #F4F6FC bands and the purple guarantee panel alike, where a shadow gap
     would have to know the background colour.

     Params: $text, $url, and optional $class for positioning at the call site. --}}
<a href="{{ $url ?? '#booking-footer' }}"
   class="group inline-flex items-center justify-center gap-9 rounded-full bg-brand-magenta py-[19px] pl-10 pr-8 font-display text-[17px] font-bold uppercase leading-none tracking-[-0.45px] text-white outline outline-[1.5px] outline-offset-4 outline-brand-magenta transition-all duration-200 hover:bg-brand-magenta-hover hover:outline-brand-magenta-hover focus:outline-brand-magenta focus-visible:ring-2 focus-visible:ring-brand-magenta focus-visible:ring-offset-2 sm:text-[19px] {{ $class ?? '' }}">
    <span>{{ $text ?? 'BOOK A CONSULTATION' }}</span>
    <svg class="h-[23px] w-[23px] shrink-0 transition-transform duration-200 group-hover:translate-x-0.5"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <circle cx="12" cy="12" r="10.4" stroke-width="1.5" />
        <path d="M10.5 8.4l3.6 3.6-3.6 3.6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</a>
