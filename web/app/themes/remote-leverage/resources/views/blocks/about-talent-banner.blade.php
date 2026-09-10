<div class="relative w-full h-[320px] sm:h-[380px] lg:h-[440px] bg-[#250D4A] overflow-hidden flex items-center justify-center">
    {{-- Background Image: Talent Grid --}}
    @if (! empty($backgroundImage))
        <img src="{{ $backgroundImage }}" alt="Remote Leverage Talent Profiles" loading="lazy" decoding="async"
            class="absolute inset-0 w-full h-full object-cover object-center opacity-85">
    @endif

    {{-- Dark Purple Tone Gradient Overlay --}}
    <div class="absolute inset-0 bg-gradient-to-r from-[#250D4A]/80 via-[#3B126E]/65 to-[#250D4A]/80 mix-blend-multiply"></div>
    <div class="absolute inset-0 bg-[#250D4A]/40"></div>

    {{-- Centered Frosted Glass Pill --}}
    <div class="relative z-10">
        <div class="inline-flex items-center gap-4 sm:gap-6 backdrop-blur-md bg-white/20 border border-white/30 rounded-full px-7 py-3.5 sm:px-10 sm:py-5 shadow-[0_8px_32px_rgba(0,0,0,0.35)] transition-all duration-300 hover:bg-white/25 hover:scale-[1.02] cursor-pointer">
            <span class="font-display text-xl sm:text-2xl lg:text-[28px] font-bold text-white tracking-[-0.02em]">
                {!! $bannerText !!}
            </span>

            {{-- Play Icon Button --}}
            <span class="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-white text-[#250D4A] flex items-center justify-center flex-shrink-0 shadow-[0_2px_10px_rgba(0,0,0,0.2)]">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 translate-x-[1px] fill-current" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z" />
                </svg>
            </span>
        </div>
    </div>
</div>
