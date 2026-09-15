{{-- Production's impact figures strip on /ecommerce-virtual-assistant/: a flat 225px
     #250D4A band with no gradient and no imagery, holding three equal columns whose
     second and third carry a 1px #F4F6FC hairline on their left edge. Values are 48px
     bold; the third column adds a small "USD" eyebrow above its value. Columns stack
     below sm, where the hairline moves to the top edge so nothing overflows at 390px. --}}
@php
    $isDark = ($tone ?? 'dark') !== 'light';
    $rule = $isDark ? 'border-bg-light' : 'border-black/10';
@endphp

<section class="w-full {{ $isDark ? 'bg-roles-surface' : 'bg-light' }} py-12 sm:py-0 sm:min-h-[225px] flex items-center">
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
