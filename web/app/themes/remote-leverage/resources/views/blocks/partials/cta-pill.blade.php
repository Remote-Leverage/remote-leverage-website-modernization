{{-- The 2026 homepage's call-to-action pill, used five times down the page (hero, roles,
     process, "Skip the Hiring Headache", guarantee) and identical at every one of them.

     Measured off Homepage V3.png at 1366px on 2026-09-15: 348x61, fill #F90066
     (--color-brand-magenta), 14px/700 uppercase white with tracking -0.45px, a 22px circled
     chevron, and a 1.5px #F90066 ring sitting 4px outside the fill. The ring is an `outline`
     rather than a second box-shadow layer so it stays correct on the pale hero, the #F4F6FC
     bands and the purple guarantee panel alike — a box-shadow gap would need to know the
     background colour.

     Params: $text, $url, and optional $class for positioning at the call site. --}}
<a href="{{ $url ?? '#booking-footer' }}"
   class="group inline-flex items-center justify-center gap-3 rounded-full bg-brand-magenta px-9 py-[19px] font-display text-sm font-bold uppercase leading-none tracking-[-0.45px] text-white outline outline-[1.5px] outline-offset-4 outline-brand-magenta transition-all duration-200 hover:bg-brand-magenta-hover hover:outline-brand-magenta-hover focus:outline-brand-magenta focus-visible:ring-2 focus-visible:ring-brand-magenta focus-visible:ring-offset-2 {{ $class ?? '' }}">
    <span>{{ $text ?? 'BOOK A CONSULTATION' }}</span>
    <svg class="h-[22px] w-[22px] shrink-0 transition-transform duration-200 group-hover:translate-x-0.5"
         viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
        <circle cx="12" cy="12" r="10" stroke-width="1.6" />
        <path d="M10.5 8.5l3.5 3.5-3.5 3.5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</a>
