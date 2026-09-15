{{-- Production's 2026 Impact Report hero: a #DDE2F6 band with the report title and intro on
     the left and a purple-gradient lead-capture card (name + email + submit) on the right,
     followed by a full-width photographic mockup of the publication. Used on
     /impact-report-2026/.

     The capture posts to `form_action` (default /api/leads/gated-download, the Lead domain's
     gated-download endpoint) which records the visitor as a Lead and then delivers the PDF.
     `asset` is a slug into config/gated-assets.php, never a URL — the server decides which
     file it resolves to. Replaces production's Gravity Forms form 33, retired by ADR-0008. --}}
<section class="w-full" style="background-color:var(--color-lavender-tint);">
    <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-10 lg:pt-20 lg:pb-14">

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_656px] gap-10 lg:gap-16 items-start">
            <div class="lg:pt-10">
                <h1 class="font-display font-bold text-[34px] leading-[40px] sm:text-[46px] sm:leading-[53px] tracking-[-1.44px] text-black mb-5">
                    {!! nl2br(e($headline)) !!}
                </h1>

                <p class="text-[16px] leading-[26px] text-black/75 max-w-[520px]">{{ $intro }}</p>
            </div>

            <div class="rounded-card-lg p-8 sm:p-10 text-white"
                style="background-image:linear-gradient(var(--color-brand-purple) 0%, var(--color-brand-dark-violet) 100%);">
                <div class="flex items-start justify-between gap-4 mb-8">
                    <h2 class="font-display font-bold text-[24px] sm:text-[28px] leading-[32px] tracking-[-0.81px]">
                        {{ $formTitle }}
                    </h2>

                    <svg class="w-7 h-7 shrink-0 text-white/90" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12M7.5 10.5L12 15l4.5-4.5M4 19h16" />
                    </svg>
                </div>

                <form method="post" action="{{ $formAction }}" class="space-y-5">
                    <input type="hidden" name="asset" value="{{ $assetSlug }}">
                    <input type="hidden" name="landing_url" value="{{ $landingUrl }}">

                    <div>
                        <label for="impact-report-name" class="block text-[13px] font-semibold mb-2">
                            Name <span class="font-normal text-white/70">(Required)</span>
                        </label>
                        <input id="impact-report-name" name="name" type="text" required placeholder="Full Name"
                            class="w-full rounded-lg bg-white px-4 py-3 text-[15px] text-black placeholder-black/40 outline-none">
                    </div>

                    <div>
                        <label for="impact-report-email" class="block text-[13px] font-semibold mb-2">
                            Email <span class="font-normal text-white/70">(Required)</span>
                        </label>
                        <input id="impact-report-email" name="email" type="email" required placeholder="Your email..."
                            class="w-full rounded-lg bg-white px-4 py-3 text-[15px] text-black placeholder-black/40 outline-none">
                    </div>

                    <button type="submit"
                        class="rounded-md bg-[#2563EB] px-5 py-2.5 text-[14px] font-semibold text-white transition hover:opacity-90">
                        {{ $submitText }}
                    </button>
                </form>
            </div>
        </div>

        <img src="{{ $mockup }}" alt="{{ strip_tags($headline) }}" width="1300" height="927" loading="eager"
            fetchpriority="high" decoding="async" class="w-full h-auto object-contain mt-6">

    </div>
</section>
