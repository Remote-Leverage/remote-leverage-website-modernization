{{-- Production's "which solution is right for you" band: two photo-topped cards
     on the left, heading and CTA on the right. Type and radii come from theme
     tokens (`display`/`lead`/`card`, `rounded-badge`). --}}
<section class="w-full bg-bg-light py-14 lg:pt-20 lg:pb-10">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        <div class="rl-container">
            <div class="flex flex-col lg:flex-row lg:items-center gap-10 lg:gap-[50px]">

                <div class="w-full lg:w-1/2 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($cards as $card)
                        @continue(empty($card['title']) && empty($card['image']))
                        <div class="flex flex-col overflow-hidden rounded-badge bg-white">
                            @if (! empty($card['image']))
                                <img src="{{ $card['image'] }}" alt="" loading="lazy" decoding="async"
                                     class="w-full h-[206px] object-cover">
                            @endif

                            <div class="flex flex-col gap-5 px-5 pt-2.5 pb-[30px]">
                                <div class="flex items-start justify-between gap-3">
                                    <h3 class="font-display text-xl font-bold leading-tight tracking-[-0.6px] text-black">
                                        {{ $card['title'] }}
                                    </h3>

                                    @if ($card['icon'] === 'check')
                                        <svg class="mt-1 h-5 w-5 shrink-0 text-status-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                        </svg>
                                    @elseif ($card['icon'] === 'asterisk')
                                        <svg class="mt-1 h-5 w-5 shrink-0 text-black" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M12 2.25a.75.75 0 01.75.75v7.19l6.22-3.59a.75.75 0 11.75 1.3L13.5 11.5l6.22 3.59a.75.75 0 11-.75 1.3l-6.22-3.59V21a.75.75 0 01-1.5 0v-8.2l-6.22 3.59a.75.75 0 01-.75-1.3l6.22-3.59-6.22-3.6a.75.75 0 01.75-1.3l6.22 3.6V3a.75.75 0 01.75-.75z" />
                                        </svg>
                                    @endif
                                </div>

                                @if (! empty($card['text']))
                                    <p class="text-card text-black">{{ $card['text'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="w-full lg:w-1/2 flex flex-col gap-5">
                    @if ($headline)
                        <h2 class="font-display text-3xl sm:text-4xl lg:text-display font-bold text-black">
                            {!! $headline !!}
                        </h2>
                    @endif

                    @if ($subheadline)
                        <p class="text-lg lg:text-lead text-black">
                            {!! $subheadline !!}
                        </p>
                    @endif

                    @if ($ctaText)
                        <div>
                            <a href="{{ $ctaUrl }}"
                               class="group inline-flex items-center gap-3 rounded-pill bg-brand-purple hover:bg-brand-purple-deep text-white font-bold uppercase text-base lg:text-lead pl-[30px] pr-10 py-5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple focus-visible:ring-offset-2">
                                <span>{{ $ctaText }}</span>
                                @include('partials.icon-circle-arrow', ['class' => 'w-6 h-6 shrink-0 transition-transform group-hover:translate-x-0.5'])
                            </a>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</section>
