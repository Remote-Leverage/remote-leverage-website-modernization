{{-- Production's booking footer: flat #250D4A band, oversized heading and a short
     description on the left, the booking wizard on the right. Type from the shared
     `hero` / `lead` tokens. --}}
<section id="booking-footer"
  class="w-full bg-roles-surface bg-cover bg-center bg-no-repeat text-white py-16 sm:py-20 lg:py-24"
  @if ($mapImage) style="background-image:url('{{ $mapImage }}')" @endif>
  <div class="w-full px-4 sm:px-6 lg:px-8">
    <div class="rl-container">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 lg:items-center">

        <div class="flex flex-col">
          <h2 class="font-display font-bold text-bg-light text-4xl sm:text-5xl lg:text-hero mb-6">
            {!! $headline !!}
          </h2>

          @if (! empty($description))
            <p class="max-w-[574px] text-white text-lg lg:text-lead">
              {!! $description !!}
            </p>
          @endif
        </div>

        <div>
          {{-- lazy:false to match blocks/booking.blade.php — Livewire is injected after
               DOM ready by the island loader, so a #[Lazy] placeholder never gets
               hydrated and the form renders empty. --}}
          <livewire:booking.multistep-booking-wizard skin="glass" :lazy="false" />
        </div>

      </div>
    </div>
  </div>
</section>
