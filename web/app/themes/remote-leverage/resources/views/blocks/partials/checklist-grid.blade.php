{{-- A two-column grid of ticked statements. One markup for two places: acf/partner-hero's
     reassurance badges, and acf/checklist-grid standing on its own.

     `style`:
       · 'pill' (default) — acf/partner-hero's badges as production's ecommerce hero draws them:
         a white pill with a faint blue border, a 20px #00D982 disc and 15px copy.
       · 'row' — /become-a-partner/'s "Who Should Become a Partner?" list, off the Partner LP
         Figma frame at 1366px: 416x49 white rows on an 8px radius, a 25px #00D982 disc 12px in,
         14/20 copy 10px after it, rows 10px apart both ways.

     `icon` is 'check' (white tick) or 'arrow' (dark arrow, the partner comp's).

     Params: $items (list of strings), optional $style, $icon, $class for the grid's own
     positioning. Empty strings are skipped. --}}
@php
  $isRow = ($style ?? 'pill') === 'row';
  $isArrow = ($icon ?? 'check') === 'arrow';
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-left {{ $class ?? '' }}">
  @foreach ($items as $item)
    @continue(empty($item))
    <div @class([
      'flex items-center',
      'gap-3 rounded-pill border border-[#92B4F4]/30 bg-white px-[17px] py-3.5' => ! $isRow,
      'min-h-[49px] gap-2.5 rounded-lg bg-white px-3 py-3' => $isRow,
    ])>
      <span @class([
        'flex shrink-0 items-center justify-center rounded-full bg-[#00D982]',
        'h-5 w-5' => ! $isRow,
        'h-[25px] w-[25px]' => $isRow,
      ])>
        @if ($isArrow)
          <svg class="h-3.5 w-3.5 text-brand-hero" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        @else
          <svg class="h-3 w-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        @endif
      </span>
      <span @class(['text-black', 'text-[15px]' => ! $isRow, 'font-display text-[14px] leading-5 tracking-[-0.42px]' => $isRow])>{{ $item }}</span>
    </div>
  @endforeach
</div>
