{{-- Production's /Service-Hiring/: a bare Calendly inline embed on a navy → violet gradient
     band, under a centred heading and two centred paragraphs.

     Tokens read off the live page with getComputedStyle (2026-09-16): the band is a 200deg
     #342567 → #6200A4 gradient painted on the container's ::before, the inner column is a
     40px-padded flex stack with a 20px gap, the h2 is 54px/54px bold white (32px on mobile)
     with a 10px bottom margin, body copy is 18px/36px white centred with 14.4px paragraph
     margins (16px/1.4em on mobile), and the embed is a 700px-tall full-width box.

     Production sets the heading in League Spartan and body in Poppins; the theme dropped both
     in favour of Inter Display (see the @font-face note in resources/css/app.css), so the
     heading uses `font-display` here.

     Production's breakpoint is 767/768px, which is Tailwind's `md`, not `sm`. --}}
@php
    $band = match ($background) {
        'navy-violet' => 'linear-gradient(200deg, var(--color-brand-navy) 0%, var(--color-brand-purple-deep) 100%)',
        'dark-violet' => 'var(--color-brand-dark-violet)',
        'light' => 'var(--color-bg-light)',
        default => '',
    };

    $isDark = in_array($background, ['navy-violet', 'dark-violet'], true);
@endphp

<section class="w-full" @if ($band !== '') style="background: {{ $band }};" @endif>
    <div class="mx-auto w-full max-w-[1380px] px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-5 py-10">

            @if ($heading)
                <h1 @class([
                    'mb-[10px] text-center font-display text-[32px] leading-[32px] font-bold md:text-[54px] md:leading-[54px]',
                    'text-white' => $isDark,
                    'text-brand-navy' => ! $isDark,
                ])>
                    {{ $heading }}
                </h1>
            @endif

            @if ($paragraphs)
                <div @class([
                    'text-center text-[16px] leading-[1.4em] md:text-[18px] md:leading-[36px] [&_p]:mb-[14.4px]',
                    'text-white' => $isDark,
                    'text-[#333]' => ! $isDark,
                ])>
                    @foreach ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endif

            @if ($embedUrl)
                {{-- Calendly replaces this div's contents with its own iframe, sized to the
                     box, so the height belongs here rather than on an iframe of ours. --}}
                <div @class(['mx-auto w-full' => $maxWidth > 0]) @if ($maxWidth > 0) style="max-width:{{ $maxWidth }}px" @endif>
                    <div
                        class="calendly-inline-widget w-full"
                        data-url="{{ $embedUrl }}"
                        style="min-width:320px;height:{{ $minHeight }}px;"
                    ></div>
                </div>

                {{-- One loader per page, however many embeds are on it. Calendly's widget.js
                     scans for .calendly-inline-widget on load and initialises every match. --}}
                @once
                    <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
                @endonce
            @elseif ($isPreview)
                <p @class(['text-center', 'text-white/70' => $isDark, 'text-black/60' => ! $isDark])>
                    Set a Calendly URL to embed the scheduler.
                </p>
            @endif

        </div>
    </div>
</section>
