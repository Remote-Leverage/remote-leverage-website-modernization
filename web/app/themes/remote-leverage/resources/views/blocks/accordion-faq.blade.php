<div class="w-full px-4 sm:px-6 lg:px-8 pt-12">
    <div class="rl-container">
    @if (! empty($headline))
        <h2 class="font-display text-4xl sm:text-5xl lg:text-[48px] font-bold leading-[1.1] tracking-[-0.03em] text-black mb-12 sm:mb-16 text-left">
            {{ $headline }}
        </h2>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 lg:gap-x-16 items-start" x-data="{ activeFaq: null }">
        {{-- Left Column --}}
        <div class="flex flex-col">
            @foreach ($faqsLeft as $idx => $faq)
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
                        <div class="pb-7 pt-1 text-[15px] sm:text-base leading-relaxed text-black">
                            {!! $faq['a'] !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Right Column --}}
        <div class="flex flex-col">
            @foreach ($faqsRight as $idx => $faq)
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
                        <div class="pb-7 pt-1 text-[15px] sm:text-base leading-relaxed text-black">
                            {!! $faq['a'] !!}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if (! empty($schemaJson))
        <script type="application/ld+json">
            {!! $schemaJson !!}
        </script>
    @endif
</div>
</div>
