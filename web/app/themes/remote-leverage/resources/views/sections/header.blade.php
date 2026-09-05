<header x-data="{ mobileMenuOpen: false, rolesDropdownOpen: false }" class="sticky top-0 z-40 w-full bg-white/90 backdrop-blur-md border-b border-slate-200/70 transition-all duration-200">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-20">
      
      <!-- Brand Logo -->
      <div class="shrink-0">
        <a href="{{ home_url('/') }}" class="flex items-center gap-3 group focus:outline-none focus:ring-2 focus:ring-brand-purple focus:ring-offset-2 rounded-lg">
          <img 
            src="{{ Vite::asset('resources/images/logo.svg') }}" 
            alt="{{ $siteName ?? 'Remote Leverage' }}" 
            class="h-5 sm:h-6 w-auto transition-transform group-hover:scale-[1.02]"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
          />
          <span class="hidden text-xl font-display font-bold text-brand-navy">
            {!! $siteName ?? 'Remote Leverage' !!}
          </span>
        </a>
      </div>

      <!-- Desktop Navigation -->
      <nav class="hidden lg:flex items-center gap-8" aria-label="{{ __('Primary Navigation', 'remote-leverage') }}">
        @if (has_nav_menu('primary_navigation'))
          {!! wp_nav_menu([
            'theme_location' => 'primary_navigation',
            'menu_class' => 'flex items-center gap-7 text-sm font-medium text-slate-700',
            'container' => false,
            'echo' => false,
            'fallback_cb' => false
          ]) !!}
        @else
          <!-- Curated Navigation Fallback -->
          <div class="relative" @click.outside="rolesDropdownOpen = false">
            <button 
              type="button" 
              @click="rolesDropdownOpen = !rolesDropdownOpen"
              class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-700 hover:text-brand-purple transition-colors py-2 focus:outline-none"
              :class="{ 'text-brand-purple': rolesDropdownOpen }"
              aria-expanded="false"
            >
              <span>{{ __('VA Roles', 'remote-leverage') }}</span>
              <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': rolesDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Mega Dropdown -->
            <div 
              x-show="rolesDropdownOpen" 
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 translate-y-2"
              x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 translate-y-2"
              class="absolute left-1/2 -translate-x-1/2 mt-3 w-80 sm:w-96 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-slate-900/5 z-50 grid grid-cols-2 gap-2"
              style="display: none;"
            >
              <a href="{{ home_url('/admin-virtual-assistants/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Admin Assistants</span>
                <span class="text-xs text-slate-500">Inbox & operations</span>
              </a>
              <a href="{{ home_url('/executive-virtual-assistants/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Executive Assistants</span>
                <span class="text-xs text-slate-500">High-level calendar & travel</span>
              </a>
              <a href="{{ home_url('/customer-support-virtual-assistants/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Customer Support</span>
                <span class="text-xs text-slate-500">24/7 client care</span>
              </a>
              <a href="{{ home_url('/sales-virtual-assistants/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Sales & Outbound</span>
                <span class="text-xs text-slate-500">SDRs & pipeline growth</span>
              </a>
              <a href="{{ home_url('/bookkeeping-accounting-virtual-assistants/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Bookkeeping</span>
                <span class="text-xs text-slate-500">Financial reconciliation</span>
              </a>
              <a href="{{ home_url('/marketing-assistants-legacy/') }}" class="flex flex-col p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
                <span class="text-sm font-semibold text-slate-900">Marketing & Social</span>
                <span class="text-xs text-slate-500">Growth & campaigns</span>
              </a>
            </div>
          </div>

          <a href="{{ home_url('/reviews') }}" class="text-sm font-semibold text-slate-700 hover:text-brand-purple transition-colors">
            {{ __('Reviews', 'remote-leverage') }}
          </a>

          <a href="{{ home_url('/case-study/') }}" class="text-sm font-semibold text-slate-700 hover:text-brand-purple transition-colors">
            {{ __('Case Studies', 'remote-leverage') }}
          </a>

          <a href="{{ home_url('/vapricing') }}" class="text-sm font-semibold text-slate-700 hover:text-brand-purple transition-colors">
            {{ __('Pricing', 'remote-leverage') }}
          </a>

          <a href="{{ home_url('/samples') }}" class="text-sm font-semibold text-slate-700 hover:text-brand-purple transition-colors">
            {{ __('Sample Candidates', 'remote-leverage') }}
          </a>
        @endif
      </nav>

      <!-- Right Header Actions -->
      <div class="hidden sm:flex items-center gap-4">
        <!-- Live Status Presence Pill -->
        <div class="hidden xl:inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 text-xs font-semibold border border-emerald-200/70">
          <span class="live-dot"></span>
          <span>{{ __('Consultants Online', 'remote-leverage') }}</span>
        </div>

        <!-- Primary CTA Button -->
        <a href="{{ home_url('/vacalendar') }}" class="btn-primary py-2.5! px-6! text-sm! shadow-md hover:shadow-lg">
          <span>{{ __('Book a Call', 'remote-leverage') }}</span>
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </a>
      </div>

      <!-- Mobile Menu Hamburger Button -->
      <div class="flex lg:hidden items-center gap-2">
        <a href="{{ home_url('/vacalendar') }}" class="btn-primary py-2! px-4! text-xs! sm:hidden">
          {{ __('Book', 'remote-leverage') }}
        </a>

        <button 
          type="button" 
          @click="mobileMenuOpen = !mobileMenuOpen"
          class="p-2 rounded-xl text-slate-600 hover:text-brand-purple hover:bg-slate-100 transition-colors focus:outline-none"
          aria-label="{{ __('Toggle navigation menu', 'remote-leverage') }}"
        >
          <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
          <svg x-show="mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

    </div>
  </div>

  <!-- Mobile Menu Slide-Over Drawer -->
  <div 
    x-show="mobileMenuOpen" 
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 -translate-y-4"
    class="lg:hidden border-b border-slate-200 bg-white px-4 pt-2 pb-6 space-y-3 shadow-2xl"
    style="display: none;"
  >
    <div class="flex flex-col space-y-1">
      <a href="{{ home_url('/admin-virtual-assistants/') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Administrative Virtual Assistants', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/executive-virtual-assistants/') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Executive Virtual Assistants', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/customer-support-virtual-assistants/') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Customer Support Assistants', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/sales-virtual-assistants/') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Sales & SDR Assistants', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/reviews') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Client Reviews & Testimonials', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/case-study/') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Case Studies', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/vapricing') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Pricing & Plans', 'remote-leverage') }}
      </a>
      <a href="{{ home_url('/samples') }}" class="px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-800 hover:bg-slate-50 hover:text-brand-purple">
        {{ __('Candidate Audio Samples', 'remote-leverage') }}
      </a>
    </div>

    <div class="pt-4 border-t border-slate-100 flex flex-col gap-3">
      <div class="flex items-center gap-2 px-3 py-1 text-xs font-semibold text-emerald-700">
        <span class="live-dot"></span>
        <span>{{ __('Senior Consultants Online Now', 'remote-leverage') }}</span>
      </div>
      <a href="{{ home_url('/vacalendar') }}" class="btn-primary w-full text-center">
        {{ __('Book a Free Consultation', 'remote-leverage') }}
      </a>
    </div>
  </div>
</header>
