{{-- Production's consultation landing-page hero, shared by /1monthonus-flp/, /hire-va-email/,
     the three isolated-form-fields variants and /hire-real-estate-virtual-assistants-flp/.

     A compact banner band (603px on production, not a full viewport) painted with banner-02.jpg
     over #060218: near-black on the left, magenta on the right. Headline, a short intro and a
     single-column tick list on the left; a translucent glass card on the right holding the booking
     wizard with its business-email field isolated as step one.

     Measured off production 2026-09-15 with getComputedStyle: band #060218 + banner-02.jpg cover,
     inner 80px top/bottom, h1 52/58 700 white, intro 17/25.5 #CBD5E1, glass card 500px wide at
     rgba(255,255,255,0.19), card title 20/25 600 white centred.

     The card deliberately wraps the wizard's "naked" skin rather than its "glass" skin: only the
     non-glass branch of multistep-booking-wizard.blade.php implements the isolated sub-steps this
     page family is built around. --}}

<section class="relative overflow-hidden bg-[#060218] text-white">
  {{-- Banner art. An <img> layer rather than a CSS background, matching this theme's convention. --}}
  <img src="{{ $backgroundImage }}"
       alt=""
       aria-hidden="true"
       class="absolute inset-0 w-full h-full object-cover object-left-top pointer-events-none select-none" />

  <div class="relative z-10 w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 py-14 sm:py-16 lg:py-20">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-10 items-center">

      {{-- Left: headline, intro, tick list --}}
      <div>
        <h1 class="font-display font-bold text-white tracking-tight text-[34px] leading-[40px] sm:text-[44px] sm:leading-[50px] lg:text-[52px] lg:leading-[58px]">
          {!! $headline !!}
        </h1>

        @if (! empty($intro))
          <p class="mt-5 max-w-[620px] text-[17px] leading-[25.5px] text-[#CBD5E1]">
            {!! $intro !!}
          </p>
        @endif

        @if (! empty($checklist))
          <ul class="mt-7 space-y-2.5">
            @foreach ($checklist as $item)
              <li class="flex items-center gap-3 text-[16px] leading-[24px] text-white">
                <svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 512 512" fill="{{ $tickColor }}" aria-hidden="true">
                  <path d="M504 256c0 137-111 248-248 248S8 393 8 256 119 8 256 8s248 111 248 248zM227.3 387.3l184-184c6.2-6.2 6.2-16.4 0-22.6l-22.6-22.6c-6.2-6.2-16.4-6.2-22.6 0L216 308.1l-70.1-70.1c-6.2-6.2-16.4-6.2-22.6 0l-22.6 22.6c-6.2 6.2-6.2 16.4 0 22.6l104 104c6.2 6.2 16.4 6.2 22.6 0z"/>
                </svg>
                <span>{{ $item }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Right: glass booking card --}}
      <div>
        <div id="consultation-card"
             class="w-full max-w-[500px] mx-auto rounded-2xl bg-white/20 backdrop-blur-md border border-white/10 shadow-2xl p-6 sm:p-7 text-white">
          <h2 class="text-center text-[20px] leading-[25px] font-semibold text-white mb-4">
            {{ $bookingTitle }}
          </h2>

          <livewire:booking.multistep-booking-wizard
            skin="naked"
            :lazy="false"
            :enable-isolated-fields="true"
            :enableIsolatedFields="true"
            :isolated-steps="$isolatedSteps"
            :isolatedSteps="$isolatedSteps"
            :hide-profile-header="true"
            :hideProfileHeader="true"
            :hide-progress-bar="true"
            :hideProgressBar="true"
            :button-text="$formButtonText"
            :buttonText="$formButtonText"
          />
        </div>
      </div>

    </div>
  </div>
</section>
