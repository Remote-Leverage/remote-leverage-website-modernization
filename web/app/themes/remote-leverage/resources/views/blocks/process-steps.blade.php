<div class="rl-process-container w-full">
    <div class="rl-process-line"></div>
    <div class="rl-process-grid">
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
