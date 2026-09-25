{{-- Production's impact figures strip on /ecommerce-virtual-assistant/: a flat 225px
     #250D4A band with no gradient and no imagery, holding three equal columns whose
     second and third carry a 1px #F4F6FC hairline on their left edge. Values are 48px
     bold; the third column adds a small "USD" eyebrow above its value. Columns stack
     below sm, where the hairline moves to the top edge so nothing overflows at 390px.

     `layout: pills` is /become-a-partner/'s row of the same kind of figure, off the Partner LP
     Figma frame at 1366px: centred white 48px pills 10px apart, each a 20/24 bold value then a
     14/20 label 5px after it, 18px in from either end. No band of its own — it sits on whatever
     the pattern paints behind it — so `tone` does not apply. --}}
@php
    $isDark = ($tone ?? 'dark') !== 'light';
    $rule = $isDark ? 'border-bg-light' : 'border-black/10';
@endphp

@if (($layout ?? 'band') === 'pills')
<ul class="flex w-full flex-wrap items-center justify-center gap-2.5">
    @foreach ($stats as $stat)
        <li class="inline-flex items-baseline gap-[5px] rounded-full bg-white px-[18px] py-3">
            <span class="font-display text-[20px] font-bold leading-6 tracking-[-0.6px] text-black">{{ $stat['value'] }}</span>
            @if (! empty($stat['label']))
                <span class="font-display text-[14px] leading-5 tracking-[-0.42px] text-black">{{ $stat['label'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
@else
{{-- `bg-bg-light`, not `bg-light`: there is no `light` colour token, so the pale tone rendered
     transparent until the pills layout landed and the class was checked. It had no consumer. --}}
<section class="w-full {{ $isDark ? 'bg-roles-surface' : 'bg-bg-light' }} py-12 sm:py-0 sm:min-h-[225px] flex items-center">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            @foreach ($stats as $index => $stat)
                <div class="flex flex-col justify-center {{ $index > 0 ? 'border-t sm:border-t-0 sm:border-l '.$rule.' pt-5 sm:pt-0 sm:pl-5' : '' }}">
                    @if (! empty($stat['eyebrow']))
                        <span class="block text-[14px] leading-[20px] font-semibold {{ $isDark ? 'text-bg-light/70' : 'text-black/60' }}">
                            {{ $stat['eyebrow'] }}
                        </span>
                    @endif

                    <span class="font-display block text-4xl lg:text-section font-bold tracking-[-0.03em] {{ $isDark ? 'text-bg-light' : 'text-black' }}">
                        {{ $stat['value'] }}
                    </span>

                    @if (! empty($stat['label']))
                        <span class="mt-1 block text-[16px] leading-[24px] {{ $isDark ? 'text-bg-light/80' : 'text-black/70' }}">
                            {{ $stat['label'] }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
