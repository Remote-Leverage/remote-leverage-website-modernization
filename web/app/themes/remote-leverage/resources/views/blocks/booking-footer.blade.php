{{-- Production's booking footer: flat #250D4A band over the world map, oversized heading and a
     short description on the left, the booking wizard on the right. Type from the shared
     `hero` / `lead` tokens.

     Two fields let the 2026 homepage draw its own version of the same band without forking the
     block, each defaulting to what every page already shipping this block was measured against:

       · `background` — 'map' (default) or 'gradient'. The homepage comp paints a violet glow
         over #250D4B rather than the map: sampled across the band, the ground is #250D4B with a
         bright #4B219A bloom around 46%/42% and a second, deeper one off the right edge.
       · `skin` — 'glass' (default) is the dark card; 'light' is the comp's white one.
       · `form_title` — the light card's heading. Production's is "Your Contact Information".

     Three more for /become-a-partner/, off the Partner LP Figma frame at 1366px, each defaulting
     to what every other page ships:

       · `layout` — 'split' (default) or 'stacked': the heading and description centred at the
         comp's 48/53 over a centred 548px form, 40px between them, 100px above and below.
       · `form` — 'booking' (default, the wizard) or 'partnership', the partnership-prospect form.
         Prospects are not leads, so that form never touches the booking wizard or the lead
         pipeline; see App\Application\Livewire\Partner.
       · `background: map-glow` — the map over a violet bloom, the comp's mix of the two
         existing grounds: #250D4A lifting to #381075 along the top and a #4D1FD5 bloom right of
         centre, sampled across the frame.

     `revenue-first` is deliberately tied to the skin rather than exposed. The wizard renders the
     revenue field three ways: a vertical radio list when revenueFirst is set, a select when
     compactFields is, and pills otherwise. The glass card's step 1 is built around the radio
     list, so it keeps it; the light card takes the pills the comp draws. Nothing about how the
     form behaves changes — it is the same single step with every field visible either way. --}}
@php
  $useGradient = ($background ?? 'map') === 'gradient';
  $useGlow = ($background ?? 'map') === 'map-glow';
  $skin = $skin ?? 'glass';
  $isLight = $skin === 'light';
  $isStacked = ($layout ?? 'split') === 'stacked';
  $isPartnership = ($form ?? 'booking') === 'partnership';

  // Field ORDER only — the reveal is gated by enableIsolatedFields, which stays off, so every
  // field is visible exactly as before. The comp puts the revenue pills directly under the
  // email field; the wizard's own default trails them after name and phone.
  $lightFieldOrder = [
    ['step_label' => 'Details', 'step_fields' => ['email', 'monthly_revenue', 'name', 'phone', 'consent']],
  ];

  $bandStyle = match (true) {
    $useGradient => 'background-color:#250D4B;background-image:'.implode(',', [
      'radial-gradient(46% 58% at 46% 42%, #5827AE 0%, rgba(88,39,174,0) 72%)',
      'radial-gradient(38% 62% at 99% 46%, #400F93 0%, rgba(64,15,147,0) 78%)',
    ]),
    // The map is the first layer so it paints over the blooms, as the comp stacks them.
    $useGlow => 'background-image:'.implode(',', array_filter([
      $mapImage ? "url('".$mapImage."')" : '',
      'radial-gradient(40% 36% at 72% 44%, #4D1FD5 0%, rgba(77,31,213,0) 74%)',
      'radial-gradient(70% 30% at 50% 0%, #381075 0%, rgba(56,16,117,0) 80%)',
    ])),
    default => $mapImage ? "background-image:url('".$mapImage."')" : '',
  };
@endphp
<section id="booking-footer"
  @class([
    'w-full text-white',
    'bg-roles-surface bg-cover bg-center bg-no-repeat' => ! $useGradient,
    // The map-glow layers share one size, so the map spans the band rather than tiling.
  ])
  @if ($bandStyle) style="{{ $bandStyle }}" @endif>
  {{-- The band's own padding lives here rather than on <section>, so the black trust strip
       below can bleed the full width without having to escape it. Pages that leave showTrust
       off render exactly what they did before: this wrapper and nothing after it. --}}
  <div @class(['py-16 sm:py-20 lg:py-24' => ! $isStacked, 'py-16 sm:py-20 lg:py-[100px]' => $isStacked])>
    <div class="w-full px-4 sm:px-6 lg:px-8">
      <div class="rl-container">
      <div @class([
        'grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 lg:items-center' => ! $isStacked,
        'mx-auto flex max-w-[548px] flex-col items-center gap-10' => $isStacked,
      ])>

        <div @class(['flex flex-col', 'max-w-[519px] items-center text-center' => $isStacked])>
          <h2 @class([
            'font-display font-bold text-bg-light',
            'text-4xl sm:text-5xl lg:text-hero mb-6' => ! $isStacked,
            'text-[34px] leading-[1.14] sm:text-5xl lg:text-section mb-5' => $isStacked,
          ]) style="color: #ffffff !important;">
            {!! $headline !!}
          </h2>

          @if (! empty($description))
            <p @class([
              'text-white text-lg',
              'lg:text-lead max-w-[574px]' => ! $isStacked,
              'max-w-[480px] font-display lg:leading-[25px] lg:tracking-[-0.36px]' => $isStacked,
            ]) style="color: #ffffff !important;">
              {!! $description !!}
            </p>
          @endif

          {{-- Role pages only. The rating pill is a white chip because this band is dark; the
               ticks are the same #10B981 the role comps use in the hero. --}}
          @if ($showTrust)
            {{-- Centred above the form on mobile, left under the description on desktop. --}}
            <div class="mt-8 inline-flex w-fit items-center gap-2.5 self-center rounded-lg bg-white px-3 py-2 lg:self-start">
              <img src="{{ $ratingLogo }}" alt="Google" width="66" height="22" class="h-[22px] w-auto" decoding="async">
              <span class="font-display text-[15px] font-bold text-brand-hero">{{ $ratingScore }}</span>
              <span class="flex items-center gap-0.5 text-[#FFB400]" role="img" aria-label="{{ $ratingScore }} out of 5 stars">
                @for ($s = 0; $s < 5; $s++)
                  <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                  </svg>
                @endfor
              </span>
            </div>

            {{-- Desktop only. Below lg the same six move into the black strip under the form,
                 which is where both comps put them. Rendered twice rather than reordered
                 because the two sit in different full-bleed bands; `hidden` keeps the copy that
                 is not showing out of the accessibility tree, so neither is announced twice. --}}
            @if ($checklist)
              <ul class="mt-6 hidden grid-cols-1 gap-y-[14px] lg:grid">
                @foreach ($checklist as $item)
                  <li class="flex items-center gap-3">
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#10B981]">
                      <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </span>
                    <span class="font-display text-[15px] leading-tight text-white">{{ $item }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          @endif
        </div>

        <div @class(['w-full' => $isStacked])>
          {{-- lazy:false to match blocks/booking.blade.php — Livewire is injected after
               DOM ready by the island loader, so a #[Lazy] placeholder never gets
               hydrated and the form renders empty. --}}
          @if ($isPartnership)
          <livewire:partner.partnership-prospect-form
            :button-text="$formButtonText ?? 'Book a Partnership Call'"
            :buttonText="$formButtonText ?? 'Book a Partnership Call'"
            :lazy="false"
          />
          @else
          <livewire:booking.multistep-booking-wizard
            :skin="$skin"
            :revenue-first="! $isLight"
            :revenueFirst="! $isLight"
            :isolated-steps="$isLight ? $lightFieldOrder : []"
            :isolatedSteps="$isLight ? $lightFieldOrder : []"
            :card-title="$formTitle ?? 'Your Contact Information'"
            :cardTitle="$formTitle ?? 'Your Contact Information'"
            :card-subtitle="$isLight ? '' : 'Provide your contact details so our advisor can review your company requirements.'"
            :cardSubtitle="$isLight ? '' : 'Provide your contact details so our advisor can review your company requirements.'"
            :button-text="$formButtonText ?? 'Book a Consultation'"
            :buttonText="$formButtonText ?? 'Book a Consultation'"
            :lazy="false"
          />
          @endif
        </div>

      </div>
      </div>
    </div>
  </div>

  {{-- The mobile trust strip. Full bleed, bg-black, and deliberately the last thing in the
       section so it meets the site footer — also bg-black — with no seam between them: the two
       read as one black block, which is what both role comps draw. Desktop keeps these six
       beside the form instead, so this is lg:hidden. --}}
  @if ($showTrust && $checklist)
    <div class="bg-black lg:hidden">
      <div class="w-full px-4 sm:px-6 lg:px-8 py-10">
        <ul class="mx-auto grid w-fit grid-cols-1 gap-y-[18px]">
          @foreach ($checklist as $item)
            <li class="flex items-center gap-3">
              <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#10B981]">
                <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </span>
              <span class="font-display text-[15px] leading-tight text-white">{{ $item }}</span>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif
</section>
