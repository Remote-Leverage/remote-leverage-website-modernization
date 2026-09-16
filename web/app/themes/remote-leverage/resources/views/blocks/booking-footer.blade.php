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

     `revenue-first` is deliberately tied to the skin rather than exposed. The wizard renders the
     revenue field three ways: a vertical radio list when revenueFirst is set, a select when
     compactFields is, and pills otherwise. The glass card's step 1 is built around the radio
     list, so it keeps it; the light card takes the pills the comp draws. Nothing about how the
     form behaves changes — it is the same single step with every field visible either way. --}}
@php
  $useGradient = ($background ?? 'map') === 'gradient';
  $skin = $skin ?? 'glass';
  $isLight = $skin === 'light';

  // Field ORDER only — the reveal is gated by enableIsolatedFields, which stays off, so every
  // field is visible exactly as before. The comp puts the revenue pills directly under the
  // email field; the wizard's own default trails them after name and phone.
  $lightFieldOrder = [
    ['step_label' => 'Details', 'step_fields' => ['email', 'monthly_revenue', 'name', 'phone', 'consent']],
  ];

  $bandStyle = $useGradient
    ? 'background-color:#250D4B;background-image:'.implode(',', [
        'radial-gradient(46% 58% at 46% 42%, #5827AE 0%, rgba(88,39,174,0) 72%)',
        'radial-gradient(38% 62% at 99% 46%, #400F93 0%, rgba(64,15,147,0) 78%)',
      ])
    : ($mapImage ? "background-image:url('".$mapImage."')" : '');
@endphp
<section id="booking-footer"
  @class([
    'w-full text-white py-16 sm:py-20 lg:py-24',
    'bg-roles-surface bg-cover bg-center bg-no-repeat' => ! $useGradient,
  ])
  @if ($bandStyle) style="{{ $bandStyle }}" @endif>
  <div class="w-full px-4 sm:px-6 lg:px-8">
    <div class="rl-container">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 lg:items-center">

        <div class="flex flex-col">
          <h2 class="font-display font-bold text-bg-light text-4xl sm:text-5xl lg:text-hero mb-6" style="color: #ffffff !important;">
            {!! $headline !!}
          </h2>

          @if (! empty($description))
            <p class="max-w-[574px] text-white text-lg lg:text-lead" style="color: #ffffff !important;">
              {!! $description !!}
            </p>
          @endif
        </div>

        <div>
          {{-- lazy:false to match blocks/booking.blade.php — Livewire is injected after
               DOM ready by the island loader, so a #[Lazy] placeholder never gets
               hydrated and the form renders empty. --}}
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
        </div>

      </div>
    </div>
  </div>
</section>
