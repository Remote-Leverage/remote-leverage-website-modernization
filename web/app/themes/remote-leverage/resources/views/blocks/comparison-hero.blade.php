{{-- Mirrors production's comparison-page hero: full-bleed photographic background,
     text column left, hero graphic right. Type scale and the purple pill are taken
     from production's computed styles (46/53 -1.44px, 20/25 -0.6px, #8A2BE2). --}}
<section class="relative w-full bg-brand-hero bg-cover bg-center"
    @if ($backgroundImage) style="background-image:url('{{ $backgroundImage }}')" @endif>

    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-[81px]">
        <div class="flex flex-col lg:flex-row lg:items-center gap-10 lg:gap-[70px]">

            <div class="w-full lg:w-[43%] flex flex-col gap-6 lg:gap-8">
                <h1 class="font-display font-bold text-[#F4F6FC] text-3xl sm:text-4xl lg:text-[46px] lg:leading-[53px] tracking-[-0.03em] lg:tracking-[-1.44px]">
                    {!! $headline !!}
                </h1>

                @if ($subheadline)
                    <p class="max-w-[422px] text-[#F4F6FC] text-lg lg:text-[20px] lg:leading-[25px] tracking-[-0.03em] lg:tracking-[-0.6px]">
                        {!! $subheadline !!}
                    </p>
                @endif

                @if ($ctaText)
                    <div>
                        <a href="{{ $ctaUrl }}"
                           class="inline-flex items-center justify-center rounded-pill bg-brand-purple hover:bg-brand-purple-deep text-white font-bold uppercase text-[15px] lg:text-[17px] lg:leading-[17px] tracking-[-0.45px] px-9 lg:px-[45px] py-5 lg:py-[22px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-hero">
                            {{ $ctaText }}
                        </a>
                    </div>
                @endif
            </div>

            @if ($heroImage)
                <div class="w-full lg:flex-1 flex justify-center">
                    <img src="{{ $heroImage }}"
                         alt=""
                         class="w-full max-w-[423px] h-auto object-contain"
                         loading="eager"
                         decoding="async">
                </div>
            @endif
        </div>
    </div>
</section>
