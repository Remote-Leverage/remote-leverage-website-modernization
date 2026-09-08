@php
    $imgBase = get_template_directory_uri() . '/public/images/home';
@endphp

<div class="w-full max-w-[470px] flex flex-col gap-4 shrink-0">
    {{-- Card 1: VAs Onboarded --}}
    <div
        class="w-full bg-white rounded-3xl px-6 py-4.5 sm:px-7 sm:py-5 shadow-[0_10px_30px_rgba(0,0,0,0.04)] flex items-center justify-between">
        <span class="font-display font-bold text-[15px] sm:text-base text-black">VAs Onboarded</span>
        <div class="flex items-center">
            <div class="flex items-center -space-x-2">
                <img src="{{ $imgBase }}/person_01.webp" alt="Remote Assistant" width="36"
                    height="36" class="w-9 h-9 rounded-full border-2 border-white object-cover"
                    loading="lazy" decoding="async">
                <img src="{{ $imgBase }}/person_02.webp" alt="Remote Assistant" width="36"
                    height="36" class="w-9 h-9 rounded-full border-2 border-white object-cover"
                    loading="lazy" decoding="async">
                <img src="{{ $imgBase }}/Person_03.webp" alt="Remote Assistant" width="36"
                    height="36" class="w-9 h-9 rounded-full border-2 border-white object-cover"
                    loading="lazy" decoding="async">
                <img src="{{ $imgBase }}/Person_04.webp" alt="Remote Assistant" width="36"
                    height="36" class="w-9 h-9 rounded-full border-2 border-white object-cover"
                    loading="lazy" decoding="async">
            </div>
            <span class="font-display font-extrabold text-base text-black ml-3">{{ $onboardedCount }}</span>
        </div>
    </div>

    {{-- Card 2: Countries & Economic Impact --}}
    <div
        class="w-full bg-white rounded-3xl sm:rounded-[28px] p-6 sm:p-7 shadow-[0_15px_35px_rgba(0,0,0,0.04)] relative overflow-hidden flex flex-col justify-between min-h-[400px]">
        {{-- Wireframe Globe Background Graphic --}}
        <div
            class="absolute -right-12 -bottom-16 w-[300px] sm:w-[340px] pointer-events-none select-none">
            <img src="{{ $imgBase }}/globe.webp" alt="Global Coverage" width="340"
                height="340" class="w-full h-auto object-contain" loading="lazy" decoding="async">
        </div>

        {{-- Top Row: Countries & Flags --}}
        <div class="relative z-10 flex items-center justify-between mb-8">
            <span class="text-sm sm:text-[15px] font-bold text-black">Countries</span>
            <div class="flex items-center">
                <div class="flex items-center -space-x-1.5">
                    <img src="{{ $imgBase }}/costa-rica.webp" alt="Costa Rica" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/equador.webp" alt="Ecuador" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/chile.webp" alt="Chile" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/paraguai.webp" alt="Paraguay" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/brazil.webp" alt="Brazil" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/colombia.webp" alt="Colombia" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/argentina.webp" alt="Argentina" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                    <img src="{{ $imgBase }}/mexico.webp" alt="Mexico" width="22"
                        height="22"
                        class="w-5.5 h-5.5 rounded-full border-2 border-white object-cover shadow-xs"
                        loading="lazy" decoding="async">
                </div>
                <span
                    class="font-display font-extrabold text-sm sm:text-base text-black ml-2.5">{{ $countriesCount }}</span>
            </div>
        </div>

        {{-- Bottom Block: Economic Impact --}}
        <div class="relative z-10 mt-auto">
            <span class="font-display text-[15px] sm:text-base font-bold text-black block mb-2">
                Economic Impact Created
            </span>

            {{-- Underline & Divider Row --}}
            <div class="w-full flex items-center mb-3">
                <div class="h-[2.5px] bg-black w-[170px] shrink-0"></div>
                <div class="h-[1px] bg-black/10 w-full"></div>
            </div>

            {{-- Amount & Timeline --}}
            <div class="flex items-baseline justify-between pt-0.5">
                <span
                    class="font-display font-extrabold text-2xl sm:text-[28px] lg:text-[30px] text-black tracking-tight">
                    {{ $economicImpact }}
                </span>
                <span class="text-[11px] sm:text-xs font-bold text-black leading-tight text-right whitespace-pre-line">
                    {{ $timeframe }}
                </span>
            </div>
        </div>
    </div>
</div>
