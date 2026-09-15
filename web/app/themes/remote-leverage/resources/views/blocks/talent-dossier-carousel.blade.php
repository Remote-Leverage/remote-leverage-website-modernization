{{-- Production's "Meet Our Ecommerce Talent" band on /ecommerce-virtual-assistant/:
     a pale #F4F6FC surface with a left-aligned 48px heading and no subheading, above a
     horizontally scrolling track of white dossier cards. Each card opens with a photo
     carrying a country pill top-left and a green hourly-rate badge top-right, then the
     name with a verified tick, the role and years line, and the labelled EXPERIENCE /
     SKILLS / TOOLS / PREVIOUS COMPANIES blocks. Scrolling is native scroll-snap (the
     theme's shared [data-rl-carousel] behaviour); the arrows are progressive enhancement. --}}
@php
    $isCarousel = ($layout ?? 'carousel') !== 'grid';
    $labelClass = 'block text-[11px] font-semibold uppercase tracking-[0.06em] text-step-numeral';
    $valueClass = 'mt-1.5 text-[14px] leading-[22px] text-black/80';
@endphp

<section class="w-full bg-light py-14 lg:py-20 overflow-hidden" @if ($isCarousel) data-rl-carousel @endif>
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-6 mb-10">
            @if (! empty($headline))
                <h2 class="font-display font-bold text-black text-3xl sm:text-4xl lg:text-section text-left">
                    {!! $headline !!}
                </h2>
            @endif

            @if ($isCarousel)
                <div class="flex gap-3">
                    <button type="button" data-rl-carousel-prev
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border border-black/15 bg-white text-black transition-colors hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple"
                            aria-label="{{ __('Previous profiles', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button type="button" data-rl-carousel-next
                            class="inline-flex h-11 w-11 items-center justify-center rounded-circle border border-black/15 bg-white text-black transition-colors hover:bg-black/5 disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple"
                            aria-label="{{ __('More profiles', 'remote-leverage') }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>

        <div @if ($isCarousel) data-rl-carousel-track @endif
             class="{{ $isCarousel
                 ? 'flex gap-2.5 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 cursor-grab select-none touch-pan-y [scrollbar-width:none] [&::-webkit-scrollbar]:hidden'
                 : 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5' }}">
            @foreach ($cards as $card)
                <article @if ($isCarousel) data-rl-carousel-card @endif
                         class="{{ $isCarousel ? 'snap-start shrink-0 w-[calc(100vw-3rem)] max-w-[431px] sm:w-[431px]' : 'w-full' }} flex flex-col overflow-hidden rounded-badge bg-white border border-[rgba(146,180,244,0.15)]">
                    @if (! empty($card['photo']))
                        <div class="relative w-full h-[250px] shrink-0 bg-lavender-tint">
                            <img src="{{ $card['photo'] }}" alt="{{ $card['name'] }}" width="431" height="250"
                                 loading="lazy" decoding="async"
                                 class="w-full h-full object-cover object-top">

                            @if (! empty($card['country']))
                                <span class="absolute left-3 top-3 inline-flex items-center gap-2 rounded-pill bg-black/45 px-3 py-1.5 text-[13px] font-semibold text-white backdrop-blur-sm">
                                    @if (! empty($card['flag']))
                                        <img src="{{ $card['flag'] }}" alt="" width="19" height="19"
                                             loading="lazy" decoding="async"
                                             class="h-[19px] w-[19px] shrink-0 rounded-circle object-cover">
                                    @endif
                                    <span>{{ $card['country'] }}</span>
                                </span>
                            @endif

                            @if (! empty($card['rate']))
                                <span class="font-display absolute right-3 top-3 inline-flex items-center rounded-badge bg-table-leverage px-4 py-1.5 text-[15px] font-bold tracking-tight text-black whitespace-nowrap">
                                    {{ $card['rate'] }}
                                </span>
                            @endif
                        </div>
                    @endif

                    <div class="flex grow flex-col gap-3 p-5">
                        <div class="flex items-center gap-2">
                            <h3 class="font-display text-[22px] leading-[28px] font-bold tracking-[-0.02em] text-black">
                                {{ $card['name'] }}
                            </h3>

                            {{-- Same blue verified tick the sample-applicant cards use. --}}
                            <svg class="h-5 w-5 shrink-0 text-[#4A7EFF]" viewBox="0 0 19 20" fill="currentColor" aria-hidden="true">
                                <path d="M19 10.0002C19 10.8541 18.0299 11.5625 17.8301 12.3499C17.6231 13.1647 18.1208 14.2876 17.7286 15.001C17.3307 15.7248 16.1546 15.8301 15.5968 16.4173C15.0386 17.0048 14.9389 18.2428 14.2513 18.6613C13.5637 19.0799 12.5068 18.5506 11.7328 18.7681C10.9847 18.9785 10.3118 19.9996 9.50051 19.9996C8.68925 19.9996 8.01632 18.9785 7.26825 18.7681C6.49422 18.5502 5.42745 19.0741 4.74974 18.6613C4.06213 18.2425 3.96205 17.0045 3.40424 16.4173C2.84609 15.8298 1.67001 15.7248 1.27241 15.001C0.880265 14.2876 1.37761 13.1647 1.17095 12.3499C0.970102 11.5625 0 10.8541 0 10.0002C0 9.14622 0.970102 8.43788 1.16993 7.65043C1.37693 6.83566 0.879241 5.71274 1.27138 4.99937C1.66933 4.27557 2.84541 4.17022 3.40321 3.58305C3.96102 2.99588 4.06111 1.75755 4.74872 1.33901C5.42642 0.926236 6.4932 1.44976 7.26723 1.23222C8.01598 1.02116 8.68891 0 9.50017 0C10.3114 0 10.9844 1.02116 11.7324 1.2315C12.5065 1.4494 13.5732 0.925516 14.2509 1.3383C14.9386 1.75719 15.0386 2.99516 15.5964 3.58233C16.1546 4.16986 17.3307 4.27485 17.7283 4.99865C18.1204 5.71203 17.6231 6.83494 17.8297 7.64971C18.0296 8.43716 18.9997 9.1455 18.9997 9.99946L19 10.0002Z" />
                                <path d="M8.17966 14.4422C7.86198 14.4422 7.55763 14.3095 7.33287 14.0729L4.36244 10.9462C3.89481 10.4539 3.89481 9.65606 4.36244 9.16382C4.83007 8.67158 5.58805 8.67158 6.05568 9.16382L8.17966 11.3996L12.3193 7.04203C12.787 6.54979 13.5449 6.54979 14.0126 7.04203C14.4802 7.53428 14.4802 8.33215 14.0126 8.82439L9.02645 14.0729C8.80203 14.3092 8.49733 14.4422 8.17966 14.4422Z" fill="white" />
                            </svg>
                        </div>

                        @php
                            // Production reads "Role • N Years of Experience", dropping the
                            // separator when either half is missing.
                            $meta = array_values(array_filter([
                                $card['role'],
                                $card['years'] !== '' ? $card['years'].' '.__('Years of Experience', 'remote-leverage') : '',
                            ], fn (string $part): bool => $part !== ''));
                        @endphp

                        @if ($meta !== [])
                            <p class="text-[14px] leading-[20px] text-step-numeral">
                                {{ implode(" \u{2022} ", $meta) }}
                            </p>
                        @endif

                        <hr class="border-0 border-t border-[rgba(146,180,244,0.15)]">

                        @if (! empty($card['experience']))
                            <div>
                                <span class="{{ $labelClass }}">{{ __('Experience', 'remote-leverage') }}</span>
                                <p class="{{ $valueClass }}">{{ $card['experience'] }}</p>
                            </div>
                        @endif

                        @if (! empty($card['skills']))
                            <div>
                                <span class="{{ $labelClass }}">{{ __('Skills', 'remote-leverage') }}</span>
                                <p class="{{ $valueClass }}">{{ $card['skills'] }}</p>
                            </div>
                        @endif

                        @if (! empty($card['tools']))
                            <div>
                                <span class="{{ $labelClass }}">{{ __('Tools', 'remote-leverage') }}</span>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    @foreach ($card['tools'] as $tool)
                                        <img src="{{ $tool['src'] }}" alt="{{ $tool['alt'] }}" width="77" height="34"
                                             loading="lazy" decoding="async"
                                             class="h-auto w-[77px] max-w-full object-contain">
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (! empty($card['previous_companies']))
                            <div class="mt-auto">
                                <span class="{{ $labelClass }}">{{ __('Previous Companies', 'remote-leverage') }}</span>
                                <p class="{{ $valueClass }}">{{ $card['previous_companies'] }}</p>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
