{{-- The four reason cards, shared by both of acf/why-hire's arrangements.

     The split arrangement stacks them in a column beside the proof card and keeps the icon
     beside the copy at every width. The banner arrangement lays them out 2x2 beneath the proof
     banner and, per the role comps' mobile stack, drops the icon above the copy below sm.

     Params: $cards, $cardIcons, $isBanner. --}}
@foreach ($cards as $i => $card)
    <div @class([
        'bg-white rounded-card-md border border-black/5 shadow-xs transition-all duration-200',
        'p-6 sm:p-7 hover:shadow-sm hover:border-brand-purple/20',
        'flex items-start gap-5' => ! $isBanner,
        'flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-5' => $isBanner,
    ])>
        <div @class([
            'w-12 h-12 rounded-xl flex items-center justify-center shrink-0',
            'bg-brand-purple/10' => ! $isBanner,
            // Measured: the comp's tile is 3-6 units off white, which is --color-lavender-surface.
            'bg-lavender-surface' => $isBanner,
        ])>
            {!! $cardIcons[$i % count($cardIcons)] !!}
        </div>
        <div>
            <h4 class="text-lg sm:text-xl font-bold font-display text-brand-hero tracking-tight">
                {{ $card['title'] }}
            </h4>
            <p class="text-sm text-text-muted mt-1.5 leading-relaxed">
                {{ $card['desc'] }}
            </p>
        </div>
    </div>
@endforeach
