<footer class="content-info bg-black text-slate-300">
  <div class="w-full px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="rl-container">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">

      <!-- Brand & Mission Column -->
      <div class="space-y-6">
        <a href="{{ home_url('/') }}" class="inline-block focus:outline-none focus:ring-2 focus:ring-brand-purple rounded-lg">
          <div class="flex items-center gap-3">
            <img
              src="{{ Vite::asset('resources/images/logo.svg') }}"
              alt="{{ $siteName ?? 'Remote Leverage' }}"
              width="180"
              height="40"
              loading="lazy"
              decoding="async"
              class="h-9 w-auto brightness-0 invert"
              onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            />
            <span class="hidden text-xl font-display font-bold text-white tracking-tight">
              Remote Leverage
            </span>
          </div>
        </a>

        <p class="text-sm text-slate-400 leading-relaxed">
          {{ __('Remote Leverage is the world leader in staffing solutions. We help companies create operational efficiency by providing exceptional talent from Latin America, the Caribbean, and globally.', 'remote-leverage') }}
        </p>

        <p class="text-sm text-slate-400 leading-relaxed">
          {{ __("We offer direct-hire recruiting, and source exceptional English-fluent virtual assistants, vetted and ready to onboard. Clients save 70% compared to US hires, and pay no ongoing fees or subscriptions. All hires are backed by a 12-month guarantee. Over specialized 50 roles across all industries – construction and trades, medical and healthcare, real estate, law, IT, Ecommerce, and more.", 'remote-leverage') }}
        </p>

        <p class="text-sm text-slate-400 leading-relaxed">
          {{ __('Our goal is to impact professionals, businesses, and communities worldwide through smarter growth solutions. We believe that great talent changes everything.', 'remote-leverage') }}
        </p>
      </div>

      <!-- Talents / Products -->
      <div class="space-y-10">
        <div>
          <h3 class="text-base font-bold text-white mb-4 font-display">
            {{ __('Talents', 'remote-leverage') }}
          </h3>
          <ul class="space-y-2.5 text-sm">
            <li><a href="{{ home_url('/marketing-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Marketing', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/sales-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Sales', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/executive-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Executive Assistant', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/healthcare-virtual-assistants/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Healthcare', 'remote-leverage') }}</a></li>
          </ul>
        </div>

        <div>
          <h3 class="text-base font-bold text-white mb-4 font-display">
            {{ __('Products', 'remote-leverage') }}
          </h3>
          <ul class="space-y-2.5 text-sm">
            <li><a href="{{ home_url('/contractor-of-record/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Contractor of Record', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/contractor-management/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Contractor Management', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/contractor-payments/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Contractor Payments', 'remote-leverage') }}</a></li>
          </ul>
        </div>
      </div>

      <!-- Careers / Resources -->
      <div class="space-y-10">
        <div>
          <h3 class="text-base font-bold text-white mb-4 font-display">
            {{ __('Careers', 'remote-leverage') }}
          </h3>
          <ul class="space-y-2.5 text-sm">
            <li><a href="{{ home_url('/careers/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('All Jobs', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/quick-application/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Quick Application', 'remote-leverage') }}</a></li>
          </ul>
        </div>

        <div>
          <h3 class="text-base font-bold text-white mb-4 font-display">
            {{ __('Resources', 'remote-leverage') }}
          </h3>
          <ul class="space-y-2.5 text-sm">
            <li><a href="{{ home_url('/case-study/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Case Studies', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/reviews/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Client Testimonials', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/blog/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Remote Leverage Blog', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/impact-report-2026/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('Impact Report 2026', 'remote-leverage') }}</a></li>
            <li><a href="{{ home_url('/about-us/') }}" class="text-slate-400 hover:text-white transition-colors">{{ __('About Us', 'remote-leverage') }}</a></li>
          </ul>
        </div>
      </div>

      <!-- Contact -->
      <div>
        <h3 class="text-base font-bold text-white mb-4 font-display">
          {{ __('Contact', 'remote-leverage') }}
        </h3>

        <div class="flex items-center gap-3 mb-6">
          <a href="https://www.linkedin.com/company/remote-leverage" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"
            class="w-9 h-9 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-white hover:text-black transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 11.001-4.124 2.062 2.062 0 01-.001 4.124zM7.114 20.452H3.558V9h3.556v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
          </a>
          <a href="https://www.facebook.com/remoteleverage" target="_blank" rel="noopener noreferrer" aria-label="Facebook"
            class="w-9 h-9 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-white hover:text-black transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.891h-2.33v6.987C18.343 21.128 22 16.991 22 12z"/></svg>
          </a>
          <a href="https://www.instagram.com/remoteleverage" target="_blank" rel="noopener noreferrer" aria-label="Instagram"
            class="w-9 h-9 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-white hover:text-black transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
          </a>
          <a href="https://x.com/remote_leverage" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"
            class="w-9 h-9 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-white hover:text-black transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <a href="https://www.youtube.com/@remoteleverage" target="_blank" rel="noopener noreferrer" aria-label="YouTube"
            class="w-9 h-9 rounded-full border border-white/40 flex items-center justify-center text-white hover:bg-white hover:text-black transition-colors">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
          </a>
        </div>

        <a href="mailto:contact@remoteleverage.com" class="block text-sm text-slate-400 hover:text-white transition-colors mb-4">
          contact@remoteleverage.com
        </a>

        <p class="text-sm text-slate-400 leading-relaxed">
          1395 Brickell Avenue, Suite 800, Miami, FL 33131
        </p>
      </div>

    </div>

    <!-- Bottom Legal Bar -->
    <div class="mt-16 pt-8 border-t border-neutral-900 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
      <p>
        &copy; {{ date('Y') }} {{ $siteName ?? 'Remote Leverage' }}. {{ __('All rights reserved.', 'remote-leverage') }}
      </p>

      <div class="flex items-center gap-6">
        <a href="{{ home_url('/terms-of-use/') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Terms of Service', 'remote-leverage') }}
        </a>
        <a href="{{ home_url('/privacy-policy') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Privacy Policy', 'remote-leverage') }}
        </a>
        <a href="{{ home_url('/referrer-portal') }}" class="hover:text-slate-300 transition-colors">
          {{ __('Referrer Portal', 'remote-leverage') }}
        </a>
      </div>
    </div>

    </div>
  </div>
</footer>
