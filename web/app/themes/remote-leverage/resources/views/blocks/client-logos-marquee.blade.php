@php
  $logos = is_array($logos ?? null) ? $logos : [];
@endphp
<div class="rl-logo-marquee-wrapper px-4 overflow-hidden py-4">
    <div class="animate-marquee-logos flex items-center gap-12 sm:gap-14">
        @foreach (array_merge($logos, $logos) as $logo)
            @continue(! is_array($logo))
            <div class="rl-logo-marquee-item shrink-0">
                <img src="{{ $logo['src'] ?? '' }}" alt="{{ $logo['alt'] ?? '' }}" width="140" height="48"
                    loading="lazy" decoding="async">
            </div>
        @endforeach
    </div>
</div>
