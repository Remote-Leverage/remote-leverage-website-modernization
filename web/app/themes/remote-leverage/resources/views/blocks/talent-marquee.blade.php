<div class="w-full overflow-hidden alignfull">
    <h2 class="sr-only">Pre-Vetted Remote Professionals</h2>
    <div class="animate-marquee-left gap-card py-2 pb-6">
        @foreach (array_merge($cards, $cards) as $card)
            <div class="rl-talent-card-wrapper rl-talent-card">
                <img class="rl-department-card__bg w-full h-full object-cover object-top"
                    src="{{ $card['bg'] }}" alt="{{ $card['name'] }}" loading="lazy" decoding="async"
                    width="250" height="400">
                <div class="rl-department-card__overlay"></div>
                <div class="rl-department-card__blur"></div>
                <div class="rl-department-card__content">
                    <h3 class="rl-department-card__title">
                        <span>{{ $card['name'] }}</span>
                        <svg class="rl-department-card__verified" viewBox="0 0 24 24" fill="#ffffff"
                            fill-rule="evenodd">
                            <path fill-rule="evenodd"
                                d="M23 12l-2.44-2.79.34-3.69-3.61-.82-1.89-3.2L12 2.96 8.6 1.5 6.71 4.69 3.1 5.5l.34 3.7L1 12l2.44 2.79-.34 3.7 3.61.82L8.6 22.5l3.4-1.47 3.4 1.46 1.89-3.19 3.61-.82-.34-3.69L23 12zm-12.91 4.72l-3.8-3.81 1.48-1.48 2.32 2.33 5.85-5.87 1.48 1.48-7.33 7.35z" />
                        </svg>
                    </h3>
                    <div class="rl-department-card__subtitle">{{ $card['title'] }}</div>
                    <p class="rl-department-card__description">{{ $card['desc'] }}</p>
                    @if (! empty($card['logo']))
                        <div class="rl-department-card__worked-at">
                            <span class="rl-department-card__worked-at-text">Worked at</span>
                            <img class="rl-department-card__worked-at-logo" src="{{ $card['logo'] }}"
                                alt="{{ $card['name'] }} past employer" width="80" height="20" loading="lazy"
                                decoding="async">
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
