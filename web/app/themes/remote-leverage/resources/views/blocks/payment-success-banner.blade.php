{{-- Production's confirmation banner at the top of /referral-program-thank-you-page-deposit/
     (page 50304): check-circle mark, headline, and two lines of reassurance on a light band. --}}
<section class="w-full bg-bg-light py-12 sm:py-16">
    <div class="w-full max-w-[1260px] mx-auto px-4 sm:px-6 lg:px-8 text-center">

        <div class="flex justify-center">
            <svg class="w-14 h-14 text-table-leverage" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="8 12 11 15 16 9"></polyline>
            </svg>
        </div>

        <h2 class="mt-5 font-display font-bold text-black text-[28px] leading-[34px] sm:text-[36px] sm:leading-[42px] tracking-[-1.08px]">
            {{ $headline }}
        </h2>

        @if ($message)
            <p class="mt-4 text-[16px] leading-[26px] text-black">{{ $message }}</p>
        @endif

        @if ($submessage)
            <p class="mt-2 text-[15px] leading-[24px] text-black/60">{{ $submessage }}</p>
        @endif

    </div>
</section>
