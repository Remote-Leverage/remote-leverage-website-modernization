{{-- CTA-only header for the hire-va landing pages.

     Production serves /hire-va-4/, /hire-for-less/ and /spanish/ with no site nav at all —
     a conversion page deliberately offers no way out. Measured off production: a 90px bar
     overlaying the dark hero artwork (transparent background, white logo) with a single
     #F90066 pill on the right.

     layouts/app.blade.php picks this over sections.header via App\Support\PageChrome.

     Positioned `fixed`, not `sticky`: the bar has to stay out of flow so the hero keeps the
     full viewport height beneath it (hire-va-hero is `min-h-dvh`), and `sticky` would claim
     90px of that. Fixed gives the same overlay at scroll 0 and follows the page after it.

     On scroll, resources/js/app.js sets `data-stuck` on this element and the bar resolves to
     a white one — the logo is the white knockout of a dark SVG, so it un-inverts in the same
     step or it vanishes against the new background.

     The knockout is only right over a dark hero. A page given this header from the sidebar
     setting can open on a pale one (the homepage does), so there the logo starts dark.
     PageChrome::ctaHeaderIsOverDarkHero() decides. --}}
@php
  $overDark = \App\Support\PageChrome::ctaHeaderIsOverDarkHero();
@endphp
<header data-rl-cta-header
        class="group fixed inset-x-0 top-0 z-40 w-full transition duration-200 data-[stuck]:bg-white data-[stuck]:shadow-[0_1px_3px_0_rgba(15,23,42,0.10)]">
  <div class="w-full px-4 sm:px-6 lg:px-8">
    <div class="rl-container">
      <div class="flex items-center justify-between gap-3 h-[90px]">
        {{-- Deliberately self-referential. These pages offer no way out, and a logo linking
             home is the one remaining exit. --}}
        <a href="#"
           class="inline-flex min-w-0 items-center gap-2 group/logo focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-0 rounded-sm {{ $overDark ? 'focus-visible:ring-white/60 group-data-[stuck]:focus-visible:ring-brand-purple' : 'focus-visible:ring-brand-purple' }}"
           aria-label="{{ get_bloginfo('name', 'display') ?: 'Remote Leverage' }}">
          <img src="{{ Vite::asset('resources/images/logo.svg') }}"
               alt="{{ get_bloginfo('name', 'display') ?: 'Remote Leverage' }}"
               width="154" height="18"
               class="h-6 sm:h-7 w-auto max-w-full object-contain object-left transition duration-200 group-hover/logo:opacity-90 {{ $overDark ? 'brightness-0 invert group-data-[stuck]:brightness-100 group-data-[stuck]:invert-0' : '' }}" />
        </a>

        <a href="#booking-footer"
           class="inline-flex shrink-0 items-center justify-center px-6 sm:px-7 py-2.5 sm:py-3 rounded-full bg-[#F90066] hover:bg-[#d60057] text-white text-xs sm:text-sm font-bold tracking-wider uppercase shadow-md transition-all duration-150">
          {{ __('Get Started', 'remote-leverage') }}
        </a>
      </div>
    </div>
  </div>
</header>
