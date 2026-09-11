{{-- Production's comparison-page hero: full-bleed photographic background, text
     column left, hero graphic right. Type comes from the shared `display`/`lead`/
     `cta` scale in @theme rather than repeated magic numbers. --}}
<section class="relative w-full bg-brand-hero bg-cover bg-center"
    @if ($backgroundImage) style="background-image:url('{{ $backgroundImage }}')" @endif>

    <div class="w-full px-4 sm:px-6 lg:px-8 py-14 lg:py-20">
        <div class="rl-container">
            <div class="flex flex-col lg:flex-row lg:items-center gap-10 lg:gap-[70px]">

                <div class="w-full lg:w-[43%] flex flex-col gap-6 lg:gap-8">
                    <h1 class="font-display font-bold text-bg-light text-3xl sm:text-4xl lg:text-display">
                        {!! $headline !!}
                    </h1>

                    @if ($subheadline)
                        <p class="max-w-[422px] text-bg-light text-lg lg:text-lead">
                            {!! $subheadline !!}
                        </p>
                    @endif

                    @if ($ctaText)
                        <div>
                            <a href="{{ $ctaUrl }}"
                               class="group inline-flex items-center justify-center gap-3 rounded-pill bg-brand-purple hover:bg-brand-purple-deep text-white font-bold uppercase text-[15px] lg:text-cta px-9 lg:px-[45px] py-5 lg:py-[22px] transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-hero">
                                <span>{{ $ctaText }}</span>
                                @include('partials.icon-circle-arrow', ['class' => 'w-5 h-5 shrink-0 transition-transform group-hover:translate-x-0.5'])
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
    </div>
</section>
