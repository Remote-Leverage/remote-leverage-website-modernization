{{-- Production's /signedup/ confirmation band (page 10848). Production paints a #8A2BE2 →
     #6E1686 purple in League Spartan with a yellow closing line; per direction on 2026-09-15
     this renders in theme tokens — brand-purple → brand-purple-deep, the theme display face,
     and brand-orange for the closing line. --}}
<section class="w-full bg-gradient-to-br from-brand-purple to-brand-purple-deep">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">

        <h1 class="font-display font-bold text-white text-center text-[30px] leading-[38px] sm:text-[40px] sm:leading-[48px] tracking-[-1.2px]">
            {{ $headline }}
        </h1>

        @if ($stepsLabel)
            <p class="mt-8 font-bold text-white text-[15px] leading-[24px]">{{ $stepsLabel }}</p>
        @endif

        @if ($steps)
            <ol class="mt-4 flex flex-col gap-4">
                @foreach ($steps as $i => $step)
                    <li class="text-white text-[15px] leading-[24px]">
                        <strong class="font-bold">{{ $i + 1 }}. {{ $step['label'] }}:</strong>
                        {{ $step['text'] }}
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($badgeImage)
            <div class="mt-10 flex justify-center">
                <img src="{{ \App\Support\BlockDefaults::preferWebp($badgeImage) }}" alt="100% satisfaction guaranteed"
                     width="223" height="261" loading="lazy" decoding="async"
                     class="w-[112px] h-auto">
            </div>
        @endif

        @if ($footnote)
            <p class="mt-8 text-center text-brand-orange font-bold text-[15px] leading-[24px]">
                {{ $footnote }}
            </p>
        @endif

    </div>
</section>
