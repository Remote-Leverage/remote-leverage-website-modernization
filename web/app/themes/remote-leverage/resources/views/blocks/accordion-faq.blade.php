{{-- Accordion FAQ. `columns` (2 = two balanced columns, the default; 1 = a single full-width
     column in reading order) and `schema` (Schema.org FAQPage structured data, on by default)
     come from the block's fields. The row markup is rendered once and looped over the columns
     so the two layouts can never drift apart. --}}
@php
    $faqColumns = ($columns ?? '2') === '1' ? [$faqsLeft] : [$faqsLeft, $faqsRight];

    $gridClasses = ($columns ?? '2') === '1'
        ? 'grid grid-cols-1 items-start'
        : 'grid grid-cols-1 lg:grid-cols-2 gap-x-12 lg:gap-x-16 items-start';

    // Tailwind's preflight strips list markers and the theme has no global ul/ol rule (only
    // .rl-legal-doc re-adds them), so an answer carrying <ul>/<ol> would render as unmarked,
    // unindented lines. These restore the markers at the same 20px indent the block's own
    // default answers already hand-apply, so nothing that already styled its lists moves.
    $answerClasses = 'rl-faq-answer pb-7 pt-1 text-[15px] sm:text-base leading-relaxed text-black'
        .' [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:pl-5 [&_ol]:pl-5';
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 pt-12">
    <div class="rl-container">
    @if (! empty($headline))
        <h2 class="font-display text-4xl sm:text-5xl lg:text-[48px] font-bold leading-[1.1] tracking-[-0.03em] text-black mb-12 sm:mb-16 text-left">
            {{ $headline }}
        </h2>
    @endif

    <div class="{{ $gridClasses }}" x-data="{ activeFaq: null }">
        @foreach ($faqColumns as $column)
            <div class="flex flex-col">
                @foreach ($column as $idx => $faq)
                    <div class="border-b border-black">
                        <button type="button"
                            :aria-expanded="activeFaq === {{ $idx }} ? 'true' : 'false'"
                            @click="activeFaq = (activeFaq === {{ $idx }} ? null : {{ $idx }})"
                            class="w-full py-6 sm:py-7 text-left flex items-center justify-between gap-4 cursor-pointer focus:outline-none group">
                            <span
                                class="font-display text-[16.5px] sm:text-[18px] font-bold text-black leading-[1.3] group-hover:opacity-75 transition-opacity pr-3">
                                {{ $faq['q'] }}
                            </span>
                            <div class="w-7 h-7 sm:w-7.5 sm:h-7.5 rounded-full border border-black flex items-center justify-center shrink-0 transition-transform duration-300"
                                :class="activeFaq === {{ $idx }} ? 'rotate-180' : ''">
                                <svg class="w-3.5 h-3.5 text-black" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                    stroke-linejoin="round" aria-hidden="true">
                                    <path d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>
                        <div x-show="activeFaq === {{ $idx }}" x-collapse style="display:none;">
                            <div class="{{ $answerClasses }}">
                                {!! $faq['a'] !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    @if (! empty($schemaJson))
        <script type="application/ld+json">
            {!! $schemaJson !!}
        </script>
    @endif
</div>
</div>
