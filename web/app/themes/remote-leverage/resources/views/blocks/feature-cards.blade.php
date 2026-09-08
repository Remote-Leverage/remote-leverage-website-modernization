<div class="grid grid-cols-1 sm:grid-cols-2 {{ $columns === '4' ? 'lg:grid-cols-4 mb-[12px]' : 'lg:grid-cols-3' }} gap-card w-full">
    @foreach ($cards as $card)
        <div class="bg-white rounded-card p-card flex flex-col justify-between border border-black/4 shadow-[0_4px_24px_rgba(0,0,0,0.03)] hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(0,0,0,0.06)] transition-all duration-300">
            <div>
                <div class="w-full overflow-hidden rounded-xl mb-5 bg-[#f7f8fc]">
                    <img src="{{ $card['img'] }}" alt="{!! strip_tags($card['title']) !!}"
                        width="{{ $columns === '4' ? '248' : '344' }}"
                        height="{{ $columns === '4' ? '190' : '230' }}"
                        loading="lazy" decoding="async"
                        class="w-full h-auto {{ $columns === '4' ? 'aspect-248/190' : 'aspect-344/230' }} object-cover rounded-xl">
                </div>
                <div class="p-3">
                    <h3 class="font-display text-xl sm:text-[22px] font-bold text-black tracking-[-0.02em] leading-snug mb-3">
                        {!! $card['title'] !!}
                    </h3>
                    <p class="text-[14px] leading-relaxed text-black">
                        {{ $card['desc'] }}
                    </p>
                </div>
            </div>
        </div>
    @endforeach
</div>
