{{-- Numbered process timeline: a horizontal rule with a dot per step, an oversized numeral above
     each, and a centred title + description below. Production uses it for 3-step 'how it works'
     bands. --}}
{{-- `variant` only changes the mobile presentation: 'cards' adds the modifier the 2026
     homepage needs (left-aligned white cards under 769px). Desktop is identical either way. --}}
<div class="rl-process-container w-full {{ ($variant ?? 'timeline') === 'cards' ? 'rl-process-container--cards' : '' }}">
    <div class="rl-process-line"></div>
    <div class="rl-process-grid" style="--rl-process-cols:{{ max(1, min(4, count($steps))) }}">
        @foreach ($steps as $step)
            <div class="rl-process-item">
                <div class="rl-process-marker">
                    <span class="rl-process-number">{{ $step['num'] }}</span>
                    <div class="rl-process-dot"></div>
                </div>
                <h3 class="rl-process-title">{!! $step['title'] !!}</h3>
                <p class="rl-process-description">{{ $step['desc'] }}</p>
            </div>
        @endforeach
    </div>
</div>
