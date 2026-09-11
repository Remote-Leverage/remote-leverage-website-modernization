{{-- Production's "Comparing costs" band: two independent tables side by side, each
     with its own coloured header bar. Colours and type come from theme tokens
     (`table-competitor` / `table-leverage`, `section` / `eyebrow` / `lead` / `card`). --}}
<section class="w-full bg-bg-light py-14 lg:py-20">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">

            @if ($eyebrow || $headline || $description)
                <div class="max-w-[640px] mb-10">
                    @if ($eyebrow)
                        <p class="font-display font-medium text-black text-xl lg:text-eyebrow">{!! $eyebrow !!}</p>
                    @endif

                    @if ($headline)
                        <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section">{!! $headline !!}</h2>
                    @endif

                    @if ($description)
                        <p class="text-black text-lg lg:text-lead mt-2">{!! $description !!}</p>
                    @endif
                </div>
            @endif

            <div class="flex flex-col lg:flex-row gap-2.5">
                @foreach ($tables as $table)
                    @continue(empty($table['rows']) && empty($table['title']))
                    <div class="w-full lg:w-1/2 flex flex-col gap-2.5">
                        @if (! empty($table['title']))
                            <div @class([
                                'rounded-[5px] px-2.5 py-3 text-center',
                                'bg-table-competitor' => $table['theme'] === 'competitor',
                                'bg-table-leverage' => $table['theme'] !== 'competitor',
                            ])>
                                <span class="font-display font-bold text-black text-lead">{{ $table['title'] }}</span>
                            </div>
                        @endif

                        @foreach ($table['rows'] as $row)
                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-5 rounded-[5px] bg-white px-2.5 py-4">
                                <span class="sm:w-[38%] shrink-0 text-card text-black">{{ $row['label'] ?? '' }}</span>
                                <span class="font-bold text-[17px] leading-snug tracking-[-0.45px] text-black">{!! nl2br(e($row['value'] ?? '')) !!}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            @if ($ctaText)
                <a href="{{ $ctaUrl }}"
                   class="mt-2.5 flex w-full items-center justify-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep px-8 py-5 text-center font-bold uppercase text-white text-lead transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple focus-visible:ring-offset-2">
                    {{ $ctaText }}
                </a>
            @endif

        </div>
    </div>
</section>
