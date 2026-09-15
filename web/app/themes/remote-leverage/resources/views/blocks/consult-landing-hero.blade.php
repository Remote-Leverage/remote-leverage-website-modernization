{{-- Shared landing-page hero. Two skins, selected by the block's `variant` field.

     `consultation` (the default, so every page that set no variant is untouched) is production's
     consultation landing hero — /1monthonus-flp/, /hire-va-email/, the three isolated-form-fields
     variants and /hire-real-estate-virtual-assistants-flp/: a compact banner band painted with
     banner-02.jpg over #060218, headline, a short intro and a single-column tick list on the left,
     and a translucent glass card on the right holding the booking wizard with its business-email
     field isolated as step one.

     Measured off production 2026-09-15 with getComputedStyle: band #060218 + banner-02.jpg cover,
     inner 80px top/bottom, h1 52/58 700 white, intro 17/25.5 #CBD5E1, glass card 500px wide at
     rgba(255,255,255,0.19), card title 20/25 600 white centred.

     The card deliberately wraps the wizard's "naked" skin rather than its "glass" skin: only the
     non-glass branch of multistep-booking-wizard.blade.php implements the isolated sub-steps this
     page family is built around.

     `va-roles` is the older "Virtual Assistant Roles" family — /1monthonus/, /hire-va-isolated-form/
     and /hire-va/. Same shape (copy left, card right) on a completely different surface: a
     #6200A4 -> #6E1686 violet band 780px tall, an h2 whose second line is a gold gradient span, a
     two-column tick list and, where the page has one, a solid white booking card. Every colour,
     size and box was read off production with getComputedStyle (2026-09-15) and the markup is a
     move of what resources/patterns/va-roles-landing.php used to hand-write.

     Every breakpoint class below is a full literal. Tailwind scans source text, so a class
     assembled by concatenation is never emitted and silently does nothing. --}}

@php
  $variant = ($variant ?? 'consultation') === 'va-roles' ? 'va-roles' : 'consultation';
@endphp

@if ($variant === 'va-roles')
  @php
    // Headline scale. /hire-va-isolated-form/ and /hire-va/ run 80/88 because their headline is
    // three lines; /1monthonus/ runs 64/70.4 because its fourth line ("First Month FREE!") would
    // otherwise overflow the 768px copy column.
    $vaHeadline = ($headlineSize ?? '64') === '80'
      ? 'text-[40px] leading-[1.1] sm:text-[64px] sm:leading-[70.4px] xl:text-[80px] xl:leading-[88px]'
      : 'text-[40px] leading-[1.1] sm:text-[64px] sm:leading-[70.4px]';

    // Width at which the hero stops being a centred single column and becomes the left-aligned
    // copy/card row. Production's own Elementor breakpoint is ~1500px: measured on /hire-va/
    // 2026-09-15, the hero h2 computes text-align:center at 1440 and start at 1600/1920.
    //
    // 'xl' (1280px) is the default because that is what the pages on this template ship, and for
    // /hire-va-isolated-form/ it is deliberate: production's own 1500px break leaves its booking
    // card overflowing off-screen at 1440, which is a production bug, not a design. A page with no
    // booking card can opt into '2xl' (1536px) and reproduce production's centred 1440px hero.
    //
    // The wide set is written as min-[1536px]: rather than 2xl: on purpose. This theme's Tailwind
    // build emits no 2xl: variant at all — verified by compiling a probe file carrying both
    // spellings. The arbitrary variant resolves to the same 96rem media query.
    $vaSplitAt = ($splitAt ?? 'xl') === '2xl' ? '2xl' : 'xl';
    $vaRow = $vaSplitAt === '2xl'
      ? 'min-[1536px]:flex-row min-[1536px]:items-center'
      : 'xl:flex-row xl:items-center';
    $vaCol = $vaSplitAt === '2xl'
      ? 'min-[1536px]:flex-1 min-[1536px]:text-left'
      : 'xl:flex-1 xl:text-left';
    $vaFlush = $vaSplitAt === '2xl' ? 'min-[1536px]:mx-0' : 'xl:mx-0';
    $vaCardCol = $vaSplitAt === '2xl'
      ? 'min-[1536px]:w-[492px] min-[1536px]:shrink-0'
      : 'xl:w-[492px] xl:shrink-0';

    // Production's hero tick: 22x22, #01FF00 (getComputedStyle fill on the Elementor icon).
    $vaCheck = '<svg class="h-[22px] w-[22px] shrink-0 text-[#01FF00]" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true"><path d="M173.9 439.4l-166.4-166.4c-10-10-10-26.2 0-36.2l36.2-36.2c10-10 26.2-10 36.2 0L192 312.7 432.1 72.6c10-10 26.2-10 36.2 0l36.2 36.2c10 10 10 26.2 0 36.2l-294.4 294.4c-10 10-26.2 10-36.2 0z"/></svg>';
  @endphp

  <section class="relative flex min-h-[780px] items-center overflow-hidden bg-[linear-gradient(135deg,#6200A4_0%,#6E1686_100%)] py-16">
    @if (! empty($backgroundImage))
      {{-- `backgroundImageClass` optionally hides the art below a breakpoint. Empty (the default)
           shows it at every width, which is what /1monthonus/ does; /hire-va/ passes
           `hidden min-[1280px]:block`. The full literal lives in the page config rather than being
           assembled here, for the reason given at the top of this file. --}}
      <img src="{{ $backgroundImage }}" alt="" aria-hidden="true"
           class="alignfull pointer-events-none absolute inset-0 h-full w-full max-w-none select-none object-cover object-right {{ $backgroundImageClass ?? '' }}" />
    @endif

    {{-- 1380px, not the 1400px the pattern used to ask for: inside the wp:group this section
         replaced, WordPress's constrained-layout rule capped the inner container at the group's
         1380px contentSize. Measured at 1440px before and after the move — the effective width
         was, and stays, 1380. --}}
    <div class="relative z-10 w-full max-w-[1380px] mx-auto px-5 sm:px-6 lg:px-8">
      <div class="mx-auto flex w-full max-w-[1280px] flex-col items-center gap-5 {{ $vaRow }}">

        {{-- Left: headline, intro, tick list, optional CTA --}}
        <div class="w-full text-center {{ $vaCol }}">
          <h2 class="font-display font-bold {{ $vaHeadline }} text-white">
            {!! $headline !!}<br />
            <span class="bg-[linear-gradient(120deg,#FFA51E_20%,#FFDC10_70%)] bg-clip-text text-transparent">
              {!! $headlineGradient ?? '' !!}
            </span>
          </h2>

          @if (! empty($intro))
            <p class="mx-auto mt-6 max-w-[670px] font-display text-[18px] leading-[27px] text-white sm:text-[22px] sm:leading-[33px] {{ $vaFlush }}">
              {!! $intro !!}
            </p>
          @endif

          @if (! empty($checklist))
            <div class="mx-auto mt-8 flex w-fit flex-col gap-x-14 gap-y-1 sm:grid sm:grid-flow-col sm:grid-rows-3 {{ $vaFlush }}">
              @foreach ($checklist as $item)
                <span class="flex items-center gap-2 text-left font-display text-[18px] font-semibold leading-10 text-white sm:text-[24px]">
                  {!! $vaCheck !!}{!! $item !!}
                </span>
              @endforeach
            </div>
          @endif

          @if (! empty($ctaText))
            <div class="mt-10">
              <a href="{{ esc_url($ctaUrl ?? '#booking-footer') }}" class="inline-block rounded-[5px] bg-[#68B93D] px-10 py-4 font-display text-[20px] sm:text-[24px] font-bold leading-6 text-white transition hover:opacity-90">{!! $ctaText !!}</a>
            </div>
          @endif
        </div>

        {{-- Right: booking card --}}
        @if (! empty($bookingTitle))
          <div class="w-full {{ $vaCardCol }}">
            <div class="mx-auto w-full max-w-[472px] rounded-[10px] bg-white p-[30px] shadow-[0_4px_150px_0_rgba(138,43,226,0.7)]">
              <h3 class="text-center font-display text-[27px] font-bold leading-[35px] text-black">
                {!! $bookingTitle !!}
              </h3>
              {{-- The wizard itself lives in the #booking-footer section below. acf/booking exposes
                   only a `skin` field, so it cannot be embedded here in production's isolated
                   single-email first step; this card carries the same field and button and hands
                   off to the real wizard. --}}
              <form action="{{ esc_url($ctaUrl ?? '#booking-footer') }}" method="get" class="mt-5 px-5">
                <label for="hero-business-email" class="block font-display text-[15px] leading-5 text-black">Business Email<span aria-hidden="true">*</span></label>
                <input id="hero-business-email" name="email" type="email" autocomplete="email"
                       class="mt-1.5 w-full rounded-[4px] bg-[#F4F6FC] px-4 py-3.5 font-display text-[16px] leading-[38px] text-[#1A1A1A] outline-none focus:ring-2 focus:ring-[#F8248A]/40" />
                <button type="submit"
                        class="mt-4 flex h-[46px] w-full items-center justify-center gap-2 rounded-[100px] bg-[#F8248A] px-7 font-display text-[16px] font-bold leading-4 text-white transition hover:opacity-90">
                  {{ $formButtonText }}
                  <span aria-hidden="true">&rarr;</span>
                </button>
              </form>
            </div>
          </div>
        @endif

      </div>
    </div>
  </section>
@else
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
@endif
