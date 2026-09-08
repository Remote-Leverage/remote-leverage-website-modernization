<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-card">
    @foreach ($cards as $sp)
        <div class="rl-department-card min-h-105">
            <img class="rl-department-card__bg w-full h-full object-cover" src="{{ $sp['img'] }}"
                alt="{!! strip_tags($sp['title']) !!}" loading="lazy" decoding="async" width="300" height="420">
            <div class="rl-department-card__overlay"></div>
            <div class="rl-department-card__blur"></div>
            <div class="rl-department-card__content p-card">
                <h3 class="font-display text-[22px] font-bold text-white leading-snug mb-2 drop-shadow-sm">
                    {!! $sp['title'] !!}</h3>
                <p class="text-sm text-white/85 leading-relaxed drop-shadow-sm">{{ $sp['desc'] }}</p>
            </div>
        </div>
    @endforeach
</div>
