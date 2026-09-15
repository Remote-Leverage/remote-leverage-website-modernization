{{-- Production's /referral-program/ hero band (page 30560), refreshed onto the theme's own
     hero language on request (2026-09-15): the brand-midnight → brand-navy → brand-purple-deep
     gradient with ambient glows used by acf/cta-banner's card variant, an eyebrow pill, the two
     earnings figures as glass cards (the Surface Material invariant), and the theme's magenta
     action pill. Production's flat #8A2BE2 → #6E1686 band, League Spartan type and green CTA
     are deliberately not reproduced.

     The four "How it Works" steps sat inside this band on production; they now render through
     acf/process-steps in their own section (see resources/patterns/referral-program-body.php). --}}
<section class="relative overflow-hidden bg-gradient-to-br from-brand-midnight via-brand-navy to-brand-purple-deep">

    {{-- Ambient lights --}}
    <div class="absolute -right-40 -top-40 w-[520px] h-[520px] rounded-full bg-brand-purple/25 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-40 -bottom-40 w-[520px] h-[520px] rounded-full bg-brand-magenta/20 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 lg:py-24 text-center">

        @if ($eyebrow)
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-pill bg-white/10 border border-white/15 text-white/85 text-[12px] font-bold uppercase tracking-[0.12em]">
                {{ $eyebrow }}
            </span>
        @endif

        <h1 class="mt-6 font-display font-bold text-white text-[38px] leading-[44px] sm:text-[56px] sm:leading-[62px] lg:text-[68px] lg:leading-[74px] tracking-[-2px] max-w-[900px] mx-auto">
            {{ $headline }}
        </h1>

        @if ($subheadline)
            <p class="mt-6 text-white/80 text-[17px] leading-[28px] sm:text-[20px] sm:leading-[32px] max-w-[760px] mx-auto">
                {{ $subheadline }}
            </p>
        @endif

        @if ($stats)
            <div class="mt-10 sm:mt-12 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 max-w-[720px] mx-auto">
                @foreach ($stats as $stat)
                    <div class="rounded-card bg-white/10 backdrop-blur-md border border-white/20 px-6 py-7 text-center shadow-2xl">
                        <div class="font-display font-bold text-white text-[38px] leading-[42px] sm:text-[44px] sm:leading-[48px] tracking-[-1.4px]">
                            {{ $stat['value'] }}
                        </div>
                        <div class="mt-2 text-white/75 text-[14px] leading-[20px]">
                            {{ $stat['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($ctaText)
            <div class="mt-10">
                <a href="{{ $ctaUrl }}"
                   class="inline-flex items-center gap-3 rounded-pill bg-brand-magenta hover:bg-brand-magenta-hover px-8 py-4 font-display text-[16px] font-bold uppercase tracking-[-0.45px] text-white shadow-xl transition hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand-navy">
                    <span>{{ $ctaText }}</span>
                    @include('partials.icon-circle-arrow', ['class' => 'w-5 h-5 shrink-0'])
                </a>
            </div>
        @endif

        @if ($portalText)
            <p class="mt-6 text-white/70 text-[15px] leading-[24px]">
                {{ $portalPrefix }}
                <a href="{{ $portalUrl }}" class="font-bold text-white underline underline-offset-4 hover:opacity-80">{{ $portalText }}</a>
            </p>
        @endif

    </div>
</section>
