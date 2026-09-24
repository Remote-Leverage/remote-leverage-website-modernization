{{-- Two-column grid of ticked statements on white pills or rows, with a check or arrow disc —
     /become-a-partner/'s "Who Should Become a Partner?" list, and the same markup as
     acf/partner-hero's badges (see blocks/partials/checklist-grid for both styles). The row
     style is centred at the partner comp's 842px (two 416px columns and the 10px between
     them); the pill style keeps acf/partner-hero's 720px.

     Wrapped so the measure survives a constrained wp:group: core's layout rules set max-width
     on the group's direct children and would override the grid's own. --}}
<div class="w-full">
  @include('blocks.partials.checklist-grid', [
    'items' => $items,
    'style' => $style,
    'icon' => $icon,
    'class' => $style === 'row' ? 'mx-auto w-full max-w-[842px]' : 'mx-auto w-full max-w-[720px]',
  ])
</div>
