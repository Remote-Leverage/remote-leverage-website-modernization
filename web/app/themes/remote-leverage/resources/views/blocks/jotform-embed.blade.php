{{-- Production's /payment/, /vaonboardingform/ and /contractoragreement/: a single hosted
     JotForm on a coloured band.
     Production loads form.jotform.com/jsform/<id>, a document.write() script that cannot run
     from a modern async context. The iframe embed renders the same form and is what JotForm
     recommends today.

     /contractoragreement/ is the JotForm Sign variant: a signable document is not served from
     form.jotform.com at all, so `product: sign` swaps the src for the www.jotform.com/sign/
     invite URL production itself embeds. Everything else about the block is unchanged.

     The heading, intro and card are additions beyond production, added on request
     (2026-09-15) so these funnel pages read like the rest of the site rather than a bare
     embed. The form's own interior is JotForm's and cannot be styled from here. --}}
@php
    $band = match ($background) {
        'light' => 'bg-bg-light',
        'lavender' => 'bg-lavender-surface',
        'dark-violet' => 'bg-brand-dark-violet',
        default => '',
    };
    $isDark = $background === 'dark-violet';
@endphp

<section @class(['w-full py-12 sm:py-16 lg:py-20', $band => $band !== ''])>
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8">

        @if ($heading || $intro)
            <div class="text-center mb-8 sm:mb-10">
                @if ($heading)
                    <h1 @class([
                        'font-display font-bold text-[30px] leading-[36px] sm:text-[42px] sm:leading-[48px] tracking-[-1.26px]',
                        'text-white' => $isDark,
                        'text-black' => ! $isDark,
                    ])>
                        {{ $heading }}
                    </h1>
                @endif

                @if ($intro)
                    <p @class([
                        'mt-4 text-[16px] leading-[26px] max-w-[640px] mx-auto',
                        'text-white/85' => $isDark,
                        'text-black/70' => ! $isDark,
                    ])>
                        {{ $intro }}
                    </p>
                @endif
            </div>
        @endif

        @if ($formId)
            <div class="mx-auto" style="max-width:{{ $maxWidth }}px">
                {{-- No padding: JotForm paints its own canvas inside the iframe, so any inset
                     here reads as a second frame around the form. The card clips it instead. --}}
                <div @class([
                    'overflow-hidden',
                    'rounded-card border border-black/4 shadow-[0_12px_32px_rgba(0,0,0,0.06)]' => $card,
                ])>
                    <iframe
                        id="JotFormIFrame-{{ $formId }}"
                        title="{{ $title ?: 'Form' }}"
                        src="{{ $embedSrc }}"
                        allow="geolocation; microphone; camera; fullscreen; payment"
                        frameborder="0"
                        scrolling="no"
                        class="w-full block border-0"
                        style="min-height:{{ $minHeight }}px;"
                    ></iframe>
                </div>
            </div>

            {{-- JotForm posts its rendered height to the parent; without this the iframe keeps
                 the reserved min-height and the form scrolls inside its own box. --}}
            <script>
                (function () {
                    var frame = document.getElementById('JotFormIFrame-{{ $formId }}');
                    if (!frame) return;
                    window.addEventListener('message', function (e) {
                        if (typeof e.data !== 'string' || e.data.indexOf('setHeight') !== 0) return;
                        var parts = e.data.split(':');
                        var height = parseInt(parts[1], 10);
                        if (height > 0) frame.style.minHeight = height + 'px';
                    });
                })();
            </script>
        @elseif ($isPreview)
            <p class="text-center text-black/60">Set a JotForm form ID to embed the form.</p>
        @endif

    </div>
</section>
