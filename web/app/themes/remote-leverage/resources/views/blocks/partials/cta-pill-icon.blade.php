{{-- The circled glyph inside a CTA pill. 23px, measured off Homepage V3.png.

     'arrow' points onward and is the default; 'chevron-down' marks a disclosure control, which
     is what the reviews wall's SHOW MORE uses. Shared so the two cannot drift in size or stroke
     weight — the circle is the part that reads, and a 1px difference in it is visible when a
     pill sits directly above another.

     Param: $icon. --}}
<svg class="h-[23px] w-[23px] shrink-0 transition-transform duration-200 {{ ($icon ?? 'arrow') === 'chevron-down' ? 'group-hover:translate-y-0.5' : 'group-hover:translate-x-0.5' }}"
     viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
    <circle cx="12" cy="12" r="10.4" stroke-width="1.5" />
    @if (($icon ?? 'arrow') === 'chevron-down')
        <path d="M8.4 10.5l3.6 3.6 3.6-3.6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
    @else
        <path d="M10.5 8.4l3.6 3.6-3.6 3.6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
    @endif
</svg>
