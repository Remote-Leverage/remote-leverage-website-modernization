{{-- The 2026 site footer: logo and contact details over a hairline, then copyright and legal
     links. This is the default footer site-wide as of 2026-09-16; the previous four-column
     footer is still available per page — see sections/footer.blade.php and PageChrome.

     Measured off Homepage V1.png at 1366px: black ground, 73px above the logo, a 1px white/15
     rule 48px under it, the legal row 47px below that, and 98px to the foot of the band.

     Address and links are the site's own, not the comp's: the comp draws a San Jose address
     (the business is in Miami), misspells "Privacy", and drops Referrer Portal. Per direction
     on 2026-09-16 the live address stands, the spelling is corrected, and all four links
     ship. --}}
<footer class="content-info bg-black text-slate-300">
  <div class="w-full max-w-[1380px] mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-10 sm:pt-[73px] sm:pb-[98px]">

    <div class="flex flex-col gap-10 sm:flex-row sm:items-start sm:justify-between sm:gap-8">
      <a href="{{ home_url('/') }}" class="inline-block shrink-0 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-purple">
        {{-- The stacked lockup, not the single-line logo.svg the header uses: the comp sets the
             footer mark on two lines. brightness-0 invert paints the purple artwork white. --}}
        <img
          src="{{ Vite::asset('resources/images/rl-logo-5.png') }}"
          alt="{{ $siteName ?? 'Remote Leverage' }}"
          width="1286"
          height="387"
          loading="lazy"
          decoding="async"
          class="h-12 w-auto max-w-full object-contain object-left brightness-0 invert sm:h-[67px]"
          onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
        />
        <span class="hidden font-display text-2xl font-bold tracking-tight text-white">
          {{ $siteName ?? 'Remote Leverage' }}
        </span>
      </a>

      <address class="not-italic text-[15px] leading-[30px] text-slate-300 sm:text-right sm:text-base">
        <a href="mailto:contact@remoteleverage.com" class="block transition-colors hover:text-white">
          contact@remoteleverage.com
        </a>
        <span class="block">1395 Brickell Avenue, Suite 800,</span>
        <span class="block">Miami, FL 33131</span>
      </address>
    </div>

    <hr class="my-12 border-0 border-t border-white/15 sm:mt-12 sm:mb-[47px]">

    <div class="flex flex-col items-start gap-6 text-[15px] text-slate-300 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
      <p>{{ date('Y') }} {{ $siteName ?? 'Remote Leverage' }} {{ __('All Rights Reserved', 'remote-leverage') }}</p>

      <nav class="flex flex-wrap items-center gap-x-8 gap-y-3" aria-label="{{ __('Legal', 'remote-leverage') }}">
        <a href="{{ home_url('/terms-of-use/') }}" class="transition-colors hover:text-white">
          {{ __('Terms of Service', 'remote-leverage') }}
        </a>
        <a href="{{ home_url('/privacy-policy') }}" class="transition-colors hover:text-white">
          {{ __('Privacy Policy', 'remote-leverage') }}
        </a>
        {{-- Hands off to whatever consent manager is mounted; harmless if none is. --}}
        <button type="button" class="rl-cookie-settings transition-colors hover:text-white">
          {{ __('Cookie Settings', 'remote-leverage') }}
        </button>
        <a href="{{ home_url('/referrer-portal') }}" class="transition-colors hover:text-white">
          {{ __('Referrer Portal', 'remote-leverage') }}
        </a>
      </nav>
    </div>

  </div>
</footer>
