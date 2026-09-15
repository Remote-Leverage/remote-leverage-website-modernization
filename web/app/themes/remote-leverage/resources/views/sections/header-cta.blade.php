{{-- CTA-only header for the hire-va landing pages.

     Production serves /hire-va-4/, /hire-for-less/ and /spanish/ with no site nav at all —
     a conversion page deliberately offers no way out. Measured off production: a 90px bar
     overlaying the dark hero artwork (transparent background, white logo) with a single
     #F90066 pill on the right.

     layouts/app.blade.php picks this over sections.header via App\Support\PageChrome.
     It is absolutely positioned so the hero keeps the full viewport height beneath it. --}}
<header class="absolute inset-x-0 top-0 z-30 w-full">
  <div class="w-full px-4 sm:px-6 lg:px-8">
    <div class="rl-container">
      <div class="flex items-center justify-between h-[90px]">
        <a href="{{ home_url('/') }}"
           class="inline-flex items-center gap-2 group focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:ring-offset-0 rounded-sm"
           aria-label="{{ get_bloginfo('name', 'display') ?: 'Remote Leverage' }}">
          <img src="{{ Vite::asset('resources/images/logo.svg') }}"
               alt="{{ get_bloginfo('name', 'display') ?: 'Remote Leverage' }}"
               class="h-6 sm:h-7 w-auto brightness-0 invert transition-opacity group-hover:opacity-90" />
        </a>

        <a href="#booking-footer"
           class="inline-flex items-center justify-center px-6 sm:px-7 py-2.5 sm:py-3 rounded-full bg-[#F90066] hover:bg-[#d60057] text-white text-xs sm:text-sm font-bold tracking-wider uppercase shadow-md transition-all duration-150">
          {{ __('Get Started', 'remote-leverage') }}
        </a>
      </div>
    </div>
  </div>
</header>
